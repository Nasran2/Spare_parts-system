<?php

namespace App\Services;

use App\Models\Accounting\AccountTransaction;
use App\Models\Accounting\ChartAccount;
use App\Models\PurchaseReturn;
use App\Models\SaleReturn;
use App\Models\TaxLedgerEntry;
use App\Models\TransactionTaxLine;

class TaxReturnService
{
    public function recordSaleReturn(SaleReturn $return): void
    {
        $return->loadMissing(['sale', 'items']);
        $this->record(
            'sale',
            'sale_return',
            $return->id,
            $return->return_date?->toDateString() ?? now()->toDateString(),
            (string) ($return->sale?->sale_no ?? ('RETURN-'.$return->id)),
            'output',
            $return->items->map(fn ($item) => [
                'source_line_id' => $item->sale_item_id,
                'return_line_id' => $item->id,
                'quantity' => $item->quantity,
            ])->all(),
            $return->sale?->store_id
        );
    }

    public function recordPurchaseReturn(PurchaseReturn $return): void
    {
        $return->loadMissing(['purchase', 'items']);
        $this->record(
            'purchase',
            'purchase_return',
            $return->id,
            $return->return_date?->toDateString() ?? now()->toDateString(),
            (string) ($return->purchase?->purchase_no ?? ('PUR-RETURN-'.$return->id)),
            'input',
            $return->items->map(fn ($item) => [
                'source_line_id' => $item->purchase_item_id,
                'return_line_id' => $item->id,
                'quantity' => $item->quantity,
            ])->all(),
            $return->purchase?->store_id
        );
    }

    private function record(
        string $sourceType,
        string $returnType,
        int $returnId,
        string $date,
        string $invoiceNumber,
        string $direction,
        array $returnLines,
        ?int $storeId
    ): void {
        $taxable = 0;
        $vat = 0;
        foreach ($returnLines as $line) {
            $source = TransactionTaxLine::query()
                ->where('transaction_type', $sourceType)
                ->where('transaction_line_id', $line['source_line_id'])
                ->first();
            if (! $source) {
                continue;
            }
            $sourceQty = DecimalMath::parse($source->quantity);
            $returnQty = DecimalMath::parse((string) $line['quantity']);
            if ($sourceQty <= 0 || $returnQty <= 0) {
                continue;
            }
            $ratio = fn (string $value) => DecimalMath::roundDiv(
                DecimalMath::parse($value) * $returnQty,
                $sourceQty
            );
            $gross = $ratio($source->gross_amount);
            $discount = $ratio($source->discount_amount);
            $lineTaxable = $ratio($source->taxable_amount);
            $lineVat = $ratio($source->vat_amount);
            $total = $ratio($source->total_amount);
            if (in_array($source->tax_status, ['standard', 'zero_rated'], true)) {
                $taxable += $lineTaxable;
            }
            if ($direction === 'output' || $source->input_vat_claimable) {
                $vat += $lineVat;
            }

            TransactionTaxLine::updateOrCreate(
                [
                    'transaction_type' => $returnType,
                    'transaction_id' => $returnId,
                    'transaction_line_id' => $line['return_line_id'],
                    'tax_type' => 'VAT',
                ],
                [
                    'tax_status' => $source->tax_status,
                    'tax_rate' => $source->tax_rate,
                    'price_mode' => $source->price_mode,
                    'original_unit_price' => $source->original_unit_price,
                    'quantity' => DecimalMath::format($returnQty),
                    'gross_amount' => DecimalMath::format(-$gross),
                    'discount_amount' => DecimalMath::format(-$discount),
                    'taxable_amount' => DecimalMath::format(-$lineTaxable),
                    'vat_amount' => DecimalMath::format(-$lineVat),
                    'total_amount' => DecimalMath::format(-$total),
                    'rounding_adjustment' => 0,
                    'input_vat_claimable' => $source->input_vat_claimable,
                    'tax_period' => substr($date, 0, 7),
                    'setting_snapshot' => $source->setting_snapshot,
                ]
            );
        }

        TaxLedgerEntry::updateOrCreate(
            [
                'transaction_type' => $returnType,
                'transaction_id' => $returnId,
                'direction' => $direction,
            ],
            [
                'entry_date' => $date,
                'tax_period' => substr($date, 0, 7),
                'invoice_number' => $invoiceNumber,
                'taxable_amount' => DecimalMath::format(-$taxable),
                'tax_amount' => DecimalMath::format(-$vat),
                'adjustment_amount' => 0,
                'status' => 'posted',
                'store_id' => $storeId,
                'created_by' => auth()->id(),
            ]
        );

        if ($vat > 0 && ! AccountTransaction::query()
            ->where('source_type', 'tax_'.$direction.'_return')
            ->where('source_id', $returnId)
            ->exists()) {
            $account = $direction === 'output'
                ? $this->account('2100', 'Output VAT Payable', 'liability', 'output_vat')
                : $this->account('1400', 'Input VAT Receivable', 'asset', 'input_vat');
            AccountTransaction::create([
                'account_id' => $account->id,
                'user_id' => auth()->id(),
                'transaction_date' => $date,
                'direction' => 'out',
                'payment_method' => 'credit',
                'amount' => DecimalMath::currency($vat),
                'reference_no' => $invoiceNumber,
                'source_type' => 'tax_'.$direction.'_return',
                'source_id' => $returnId,
                'description' => ucfirst(str_replace('_', ' ', $returnType)).' VAT reversal',
            ]);
            $account->decrement('current_balance', DecimalMath::currency($vat));
        }
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
