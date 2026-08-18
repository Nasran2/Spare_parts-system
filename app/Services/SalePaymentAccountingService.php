<?php

namespace App\Services;

use App\Models\Accounting\AccountTransaction;
use App\Models\Accounting\ChartAccount;
use App\Models\ChequePayment;
use App\Models\Payment;
use App\Models\Sale;

class SalePaymentAccountingService
{
    public function recordSalePayment(Payment $payment, Sale $sale, ?int $userId = null): void
    {
        $amount = round((float) $payment->amount, 2);
        if ($amount <= 0) {
            return;
        }

        if ($this->transactionExists('payment', (int) $payment->id)) {
            return;
        }

        $method = (string) ($payment->payment_method ?: 'cash');
        $assetAccount = $this->assetAccountForPaymentMethod($method);

        AccountTransaction::create([
            'account_id' => $assetAccount->id,
            'related_account_id' => $this->salesRevenueAccount()->id,
            'user_id' => $userId,
            'transaction_date' => $payment->payment_date?->toDateString() ?? now()->toDateString(),
            'direction' => 'in',
            'payment_method' => $method,
            'amount' => $amount,
            'reference_no' => $sale->sale_no,
            'source_type' => 'payment',
            'source_id' => $payment->id,
            'description' => 'Payment received for sale '.$sale->sale_no.' via '.$this->paymentMethodLabel($method),
        ]);

        $assetAccount->increment('current_balance', $amount);
    }

    public function recordChequeHold(ChequePayment $cheque, Sale $sale, ?int $userId = null): void
    {
        $amount = round((float) $cheque->amount, 2);
        if ($amount <= 0) {
            return;
        }

        if ($this->transactionExists('cheque_payment_hold', (int) $cheque->id)) {
            return;
        }

        $receivableAccount = $this->customerReceivableAccount();

        AccountTransaction::create([
            'account_id' => $receivableAccount->id,
            'related_account_id' => $this->salesRevenueAccount()->id,
            'user_id' => $userId,
            'transaction_date' => now()->toDateString(),
            'direction' => 'in',
            'payment_method' => 'cheque',
            'amount' => $amount,
            'cheque_number' => $cheque->cheque_number,
            'reference_no' => $sale->sale_no,
            'source_type' => 'cheque_payment_hold',
            'source_id' => $cheque->id,
            'description' => 'Cheque held for sale '.$sale->sale_no,
        ]);

        $receivableAccount->increment('current_balance', $amount);
    }

    public function recordChequePass(ChequePayment $cheque, Sale $sale, ?int $userId = null): void
    {
        $amount = round((float) $cheque->amount, 2);
        if ($amount <= 0) {
            return;
        }

        if ($this->transactionExists('cheque_payment', (int) $cheque->id)) {
            return;
        }

        $bankAccount = $this->bankAccount();
        $receivableAccount = $this->customerReceivableAccount();

        AccountTransaction::create([
            'account_id' => $bankAccount->id,
            'related_account_id' => $receivableAccount->id,
            'user_id' => $userId,
            'transaction_date' => now()->toDateString(),
            'direction' => 'in',
            'payment_method' => 'cheque',
            'amount' => $amount,
            'cheque_number' => $cheque->cheque_number,
            'reference_no' => $sale->sale_no,
            'source_type' => 'cheque_payment',
            'source_id' => $cheque->id,
            'description' => 'Cheque passed for sale '.$sale->sale_no,
        ]);

        $bankAccount->increment('current_balance', $amount);
        $receivableAccount->decrement('current_balance', $amount);
    }

    public function reversePayment(Payment $payment, Sale $sale, ?int $userId = null, string $reason = 'Payment reversed'): void
    {
        if ($this->transactionExists('payment_reversal', (int) $payment->id)) {
            return;
        }

        $source = AccountTransaction::query()
            ->where('source_type', 'payment')
            ->where('source_id', $payment->id)
            ->first();
        if (! $source) {
            return;
        }

        AccountTransaction::create([
            'account_id' => $source->account_id,
            'related_account_id' => $source->related_account_id,
            'user_id' => $userId,
            'transaction_date' => now()->toDateString(),
            'direction' => 'out',
            'payment_method' => $source->payment_method,
            'amount' => $source->amount,
            'reference_no' => $sale->sale_no,
            'source_type' => 'payment_reversal',
            'source_id' => $payment->id,
            'description' => $reason.' for sale '.$sale->sale_no,
        ]);
        ChartAccount::whereKey($source->account_id)->decrement('current_balance', $source->amount);
    }

    public function reverseCheque(ChequePayment $cheque, Sale $sale, ?int $userId = null, string $reason = 'Cheque reversed'): void
    {
        $pass = AccountTransaction::query()
            ->where('source_type', 'cheque_payment')
            ->where('source_id', $cheque->id)
            ->first();
        if ($pass && ! $this->transactionExists('cheque_payment_reversal', (int) $cheque->id)) {
            AccountTransaction::create([
                'account_id' => $pass->account_id,
                'related_account_id' => $pass->related_account_id,
                'user_id' => $userId,
                'transaction_date' => now()->toDateString(),
                'direction' => 'out',
                'payment_method' => 'cheque',
                'amount' => $pass->amount,
                'cheque_number' => $cheque->cheque_number,
                'reference_no' => $sale->sale_no,
                'source_type' => 'cheque_payment_reversal',
                'source_id' => $cheque->id,
                'description' => $reason.' (bank) for sale '.$sale->sale_no,
            ]);
            ChartAccount::whereKey($pass->account_id)->decrement('current_balance', $pass->amount);

            if ($pass->related_account_id) {
                AccountTransaction::create([
                    'account_id' => $pass->related_account_id,
                    'related_account_id' => $pass->account_id,
                    'user_id' => $userId,
                    'transaction_date' => now()->toDateString(),
                    'direction' => 'in',
                    'payment_method' => 'cheque',
                    'amount' => $pass->amount,
                    'cheque_number' => $cheque->cheque_number,
                    'reference_no' => $sale->sale_no,
                    'source_type' => 'cheque_payment_receivable_reversal',
                    'source_id' => $cheque->id,
                    'description' => $reason.' (receivable) for sale '.$sale->sale_no,
                ]);
                ChartAccount::whereKey($pass->related_account_id)->increment('current_balance', $pass->amount);
            }
        }

        $this->reverseChequeHold($cheque, $sale, $userId, $reason);
    }

    public function reverseChequeHold(ChequePayment $cheque, Sale $sale, ?int $userId = null, string $reason = 'Cheque hold reversed'): void
    {
        if ($this->transactionExists('cheque_payment_hold_reversal', (int) $cheque->id)) {
            return;
        }
        $hold = AccountTransaction::query()
            ->where('source_type', 'cheque_payment_hold')
            ->where('source_id', $cheque->id)
            ->first();
        if (! $hold) {
            return;
        }

        AccountTransaction::create([
            'account_id' => $hold->account_id,
            'related_account_id' => $hold->related_account_id,
            'user_id' => $userId,
            'transaction_date' => now()->toDateString(),
            'direction' => 'out',
            'payment_method' => 'cheque',
            'amount' => $hold->amount,
            'cheque_number' => $cheque->cheque_number,
            'reference_no' => $sale->sale_no,
            'source_type' => 'cheque_payment_hold_reversal',
            'source_id' => $cheque->id,
            'description' => $reason.' for sale '.$sale->sale_no,
        ]);
        ChartAccount::whereKey($hold->account_id)->decrement('current_balance', $hold->amount);
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
