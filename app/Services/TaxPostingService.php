<?php

namespace App\Services;

use App\Models\Accounting\AccountTransaction;
use App\Models\Accounting\ChartAccount;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\TaxLedgerEntry;
use App\Models\TaxSetting;
use App\Models\TransactionTaxLine;
use Illuminate\Database\Eloquent\Model;

class TaxPostingService
{
    public function snapshotQuotation(Sale $quotation, array $linePairs, TaxSetting $settings): void
    {
        $this->postDocument(
            'quotation',
            $quotation,
            $linePairs,
            $settings,
            'output',
            (string) $quotation->sale_no,
            $quotation->sale_date?->toDateString() ?? now()->toDateString(),
            false,
            false
        );
    }

    public function postSale(Sale $sale, array $linePairs, TaxSetting $settings): void
    {
        $this->postDocument(
            'sale',
            $sale,
            $linePairs,
            $settings,
            'output',
            (string) $sale->sale_no,
            $sale->sale_date?->toDateString() ?? now()->toDateString(),
            false
        );
    }

    public function postPurchase(Purchase $purchase, array $linePairs, TaxSetting $settings): void
    {
        $this->postDocument(
            'purchase',
            $purchase,
            $linePairs,
            $settings,
            'input',
            (string) ($purchase->supplier_tax_invoice_number ?: $purchase->purchase_no),
            $purchase->purchase_date?->toDateString() ?? now()->toDateString(),
            (bool) $purchase->input_vat_claimable
        );
    }

    public function reverse(string $type, int $transactionId, string $reason = 'Transaction reversed'): void
    {
        TaxLedgerEntry::query()
            ->where('transaction_type', $type)
            ->where('transaction_id', $transactionId)
            ->update(['status' => 'reversed']);

        $lines = TransactionTaxLine::query()
            ->where('transaction_type', $type)
            ->where('transaction_id', $transactionId)
            ->get();
        $taxable = 0;
        $vat = 0;
        foreach ($lines as $line) {
            if (in_array($line->tax_status, ['standard', 'zero_rated'], true)) {
                $taxable += DecimalMath::parse($line->taxable_amount);
            }
            if (! str_starts_with($type, 'purchase') || $line->input_vat_claimable) {
                $vat += DecimalMath::parse($line->vat_amount);
            }
        }
        TaxLedgerEntry::updateOrCreate(
            [
                'transaction_type' => $type.'_reversal',
                'transaction_id' => $transactionId,
                'direction' => str_starts_with($type, 'purchase') ? 'input' : 'output',
            ],
            [
                'entry_date' => now()->toDateString(),
                'tax_period' => now()->format('Y-m'),
                'invoice_number' => null,
                'taxable_amount' => DecimalMath::format(-$taxable),
                'tax_amount' => DecimalMath::format(-$vat),
                'adjustment_amount' => 0,
                'status' => 'posted',
                'created_by' => auth()->id(),
            ]
        );

        $direction = str_starts_with($type, 'purchase') ? 'input' : 'output';
        $source = AccountTransaction::query()
            ->where('source_type', 'tax_'.$direction)
            ->where('source_id', $transactionId)
            ->first();
        if ($source && $vat > 0 && ! AccountTransaction::query()
            ->where('source_type', 'tax_'.$direction.'_reversal')
            ->where('source_id', $transactionId)
            ->exists()) {
            AccountTransaction::create([
                'account_id' => $source->account_id,
                'user_id' => auth()->id(),
                'transaction_date' => now()->toDateString(),
                'direction' => 'out',
                'payment_method' => 'credit',
                'amount' => DecimalMath::currency($vat),
                'reference_no' => $source->reference_no,
                'source_type' => 'tax_'.$direction.'_reversal',
                'source_id' => $transactionId,
                'description' => $reason,
            ]);
            ChartAccount::whereKey($source->account_id)->decrement(
                'current_balance',
                DecimalMath::currency($vat)
            );
        }
    }

    private function postDocument(
        string $transactionType,
        Model $document,
        array $linePairs,
        TaxSetting $settings,
        string $direction,
        string $invoiceNumber,
        string $date,
        bool $inputClaimable,
        bool $postLedger = true
    ): void {
        $taxable = 0;
        $vat = 0;
        $snapshot = array_merge($settings->snapshot(), [
            'business_tin' => $settings->supplier_tin,
            'customer_tin' => $document instanceof Sale ? $document->customer?->tin : null,
            'supplier_tin' => $document instanceof Purchase ? $document->supplier?->tin : null,
        ]);

        foreach ($linePairs as $pair) {
            $lineModel = $pair['model'];
            $result = $pair['tax'];
            if (in_array($result['tax_status'], ['standard', 'zero_rated'], true)) {
                $taxable += DecimalMath::parse($result['taxable_amount']);
            }
            $vat += DecimalMath::parse($result['vat_amount']);

            TransactionTaxLine::updateOrCreate(
                [
                    'transaction_type' => $transactionType,
                    'transaction_id' => $document->getKey(),
                    'transaction_line_id' => $lineModel->getKey(),
                    'tax_type' => 'VAT',
                ],
                [
                    'tax_status' => $result['tax_status'],
                    'tax_rate' => $result['vat_rate'],
                    'price_mode' => $result['price_mode'],
                    'original_unit_price' => $result['original_unit_price'],
                    'quantity' => $result['quantity'],
                    'gross_amount' => $result['gross_amount'],
                    'discount_amount' => $result['discount_amount'],
                    'taxable_amount' => $result['taxable_amount'],
                    'vat_amount' => $result['vat_amount'],
                    'total_amount' => $result['total_amount'],
                    'rounding_adjustment' => $result['rounding_adjustment'],
                    'input_vat_claimable' => $inputClaimable,
                    'tax_period' => substr($date, 0, 7),
                    'setting_snapshot' => $snapshot,
                ]
            );
        }
        $ledgerVat = $direction === 'input' && ! $inputClaimable ? 0 : $vat;

        if ($postLedger) {
            TaxLedgerEntry::updateOrCreate(
                [
                    'transaction_type' => $transactionType,
                    'transaction_id' => $document->getKey(),
                    'direction' => $direction,
                ],
                [
                    'entry_date' => $date,
                    'tax_period' => substr($date, 0, 7),
                    'invoice_number' => $invoiceNumber,
                    'taxable_amount' => DecimalMath::format($taxable),
                    'tax_amount' => DecimalMath::format($ledgerVat),
                    'adjustment_amount' => 0,
                    'status' => 'posted',
                    'store_id' => $document->store_id ?? null,
                    'created_by' => auth()->id(),
                ]
            );
        }

        $document->forceFill([
            'tax_snapshot' => $snapshot,
        ])->saveQuietly();

        if ($postLedger && $ledgerVat > 0) {
            $this->postAccountingTax(
                $direction,
                $document,
                DecimalMath::currency($ledgerVat),
                $invoiceNumber,
                $date
            );
        }
    }

    private function postAccountingTax(
        string $direction,
        Model $document,
        string $amount,
        string $reference,
        string $date
    ): void {
        $sourceType = 'tax_'.$direction;
        if (AccountTransaction::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $document->getKey())
            ->exists()) {
            return;
        }

        $account = $direction === 'output'
            ? $this->account('2100', 'Output VAT Payable', 'liability', 'output_vat')
            : $this->account('1400', 'Input VAT Receivable', 'asset', 'input_vat');

        AccountTransaction::create([
            'account_id' => $account->id,
            'user_id' => auth()->id(),
            'transaction_date' => $date,
            'direction' => 'in',
            'payment_method' => 'credit',
            'amount' => $amount,
            'reference_no' => $reference,
            'source_type' => $sourceType,
            'source_id' => $document->getKey(),
            'description' => ($direction === 'output' ? 'Output VAT for ' : 'Input VAT for ').$reference,
        ]);
        $account->increment('current_balance', $amount);
    }

    private function account(string $code, string $name, string $type, string $subtype): ChartAccount
    {
        return ChartAccount::firstOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'type' => $type,
                'subtype' => $subtype,
                'opening_balance' => 0,
                'current_balance' => 0,
                'is_system' => true,
                'is_active' => true,
            ]
        );
    }
}
