<?php

namespace App\Services;

use App\Models\Accounting\AccountTransaction;
use App\Models\Accounting\ChartAccount;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Sale;

class BulkPaymentAccountingService
{
    public function recordCustomerPayment(Payment $payment, ?int $userId = null): void
    {
        $amount = round((float) $payment->amount, 2);
        if ($amount <= 0) return;

        if ($this->transactionExists('payment', (int) $payment->id)) {
            return;
        }

        $method = (string) ($payment->payment_method ?: 'cash');
        $assetAccount = $this->assetAccountForPaymentMethod($method);
        
        $relatedAccount = $this->customerReceivableAccount(); // Opening balance or general AR
        $description = 'Customer payment received';
        if ($payment->sale_id) {
            $relatedAccount = $this->salesRevenueAccount();
            $sale = Sale::find($payment->sale_id);
            $description = 'Payment received for sale ' . ($sale ? $sale->sale_no : '') . ' via ' . $this->paymentMethodLabel($method);
        }

        AccountTransaction::create([
            'account_id' => $assetAccount->id,
            'related_account_id' => $relatedAccount->id,
            'user_id' => $userId,
            'transaction_date' => $payment->payment_date?->toDateString() ?? now()->toDateString(),
            'direction' => 'in',
            'payment_method' => $method,
            'amount' => $amount,
            'reference_no' => $payment->sale_id ? ($sale->sale_no ?? '') : 'OP-BAL',
            'source_type' => 'payment',
            'source_id' => $payment->id,
            'description' => $description,
        ]);

        $assetAccount->increment('current_balance', $amount);
    }

    public function recordSupplierPayment(Payment $payment, ?int $userId = null): void
    {
        $amount = round((float) $payment->amount, 2);
        if ($amount <= 0) return;

        if ($this->transactionExists('payment', (int) $payment->id)) {
            return;
        }

        $method = (string) ($payment->payment_method ?: 'cash');
        $assetAccount = $this->assetAccountForPaymentMethod($method);
        
        $relatedAccount = $this->supplierPayableAccount(); // Opening balance or general AP
        $description = 'Supplier payment made';
        if ($payment->purchase_id) {
            $relatedAccount = $this->inventoryOrExpenseAccount();
            $purchase = Purchase::find($payment->purchase_id);
            $description = 'Payment made for purchase ' . ($purchase ? $purchase->purchase_no : '') . ' via ' . $this->paymentMethodLabel($method);
        }

        AccountTransaction::create([
            'account_id' => $assetAccount->id,
            'related_account_id' => $relatedAccount->id,
            'user_id' => $userId,
            'transaction_date' => $payment->payment_date?->toDateString() ?? now()->toDateString(),
            'direction' => 'out',
            'payment_method' => $method,
            'amount' => $amount,
            'reference_no' => $payment->purchase_id ? ($purchase->purchase_no ?? '') : 'OP-BAL',
            'source_type' => 'payment',
            'source_id' => $payment->id,
            'description' => $description,
        ]);

        $assetAccount->decrement('current_balance', $amount);
    }

    private function assetAccountForPaymentMethod(string $method): ChartAccount
    {
        return match ($method) {
            'bank_deposit', 'bank_transfer', 'card', 'mobile_payment' => $this->bankAccount(),
            default => $this->cashAccount(),
        };
    }

    private function cashAccount(): ChartAccount
    {
        return $this->account('1100', 'Cash', 'asset', 'cash');
    }

    private function bankAccount(): ChartAccount
    {
        return $this->account('1200', 'Bank', 'asset', 'bank');
    }

    private function customerReceivableAccount(): ChartAccount
    {
        return $this->account('1300', 'Customer Receivable', 'asset', 'customer_receivable');
    }

    private function salesRevenueAccount(): ChartAccount
    {
        return $this->account('4000', 'Sales Revenue', 'income', 'sales');
    }

    private function supplierPayableAccount(): ChartAccount
    {
        return $this->account('2000', 'Supplier Payable', 'liability', 'supplier_payable');
    }

    private function inventoryOrExpenseAccount(): ChartAccount
    {
        return $this->account('5000', 'Expense', 'expense', 'expense');
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

    private function transactionExists(string $sourceType, int $sourceId): bool
    {
        return AccountTransaction::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->exists();
    }

    private function paymentMethodLabel(string $method): string
    {
        return str_replace('_', ' ', ucfirst($method));
    }
}
