<?php

namespace App\Services;

use App\Models\Accounting\AccountTransaction;
use App\Models\Accounting\ChartAccount;
use App\Models\Expense;
use Illuminate\Support\Facades\DB;

class ExpenseAccountingService
{
    public function sync(Expense $expense, ?int $userId = null): void
    {
        DB::transaction(function () use ($expense, $userId) {
            $this->reverse($expense);

            $amount = round((float) $expense->amount, 2);
            if ($amount <= 0) {
                return;
            }

            $method = $this->paymentMethod($expense);
            $assetAccount = $method === 'bank_transfer' ? $this->bankAccount() : $this->cashAccount();
            $expenseAccount = $this->expenseAccount();
            $description = trim('Expense'.($expense->category?->name ? ' - '.$expense->category->name : '').($expense->description ? ' - '.$expense->description : ''));
            $referenceNo = 'EXP-'.$expense->id;

            AccountTransaction::create([
                'account_id' => $assetAccount->id,
                'related_account_id' => $expenseAccount->id,
                'user_id' => $userId ?? $expense->user_id,
                'transaction_date' => $expense->expense_date?->toDateString() ?? now()->toDateString(),
                'direction' => 'out',
                'payment_method' => $method,
                'amount' => $amount,
                'reference_no' => $referenceNo,
                'source_type' => 'expense',
                'source_id' => $expense->id,
                'description' => $description,
            ]);

            AccountTransaction::create([
                'account_id' => $expenseAccount->id,
                'related_account_id' => $assetAccount->id,
                'user_id' => $userId ?? $expense->user_id,
                'transaction_date' => $expense->expense_date?->toDateString() ?? now()->toDateString(),
                'direction' => 'in',
                'payment_method' => $method,
                'amount' => $amount,
                'reference_no' => $referenceNo,
                'source_type' => 'expense',
                'source_id' => $expense->id,
                'description' => $description,
            ]);

            $assetAccount->decrement('current_balance', $amount);
            $expenseAccount->increment('current_balance', $amount);
        });
    }

    public function reverse(Expense $expense): void
    {
        AccountTransaction::query()
            ->where('source_type', 'expense')
            ->where('source_id', $expense->id)
            ->get()
            ->each(function (AccountTransaction $transaction) {
                $account = ChartAccount::lockForUpdate()->find($transaction->account_id);
                if ($account) {
                    $signed = $transaction->direction === 'in'
                        ? -1 * (float) $transaction->amount
                        : (float) $transaction->amount;
                    $account->current_balance = round((float) $account->current_balance + $signed, 2);
                    $account->save();
                }

                $transaction->delete();
            });
    }

    private function paymentMethod(Expense $expense): string
    {
        return (string) ($expense->payment_method === 'bank_transfer' ? 'bank_transfer' : 'cash');
    }

    private function cashAccount(): ChartAccount
    {
        return $this->account('1100', 'Cash', 'asset', 'cash');
    }

    private function bankAccount(): ChartAccount
    {
        return $this->account('1200', 'Bank', 'asset', 'bank');
    }

    private function expenseAccount(): ChartAccount
    {
        return $this->account('5000', 'Expense', 'expense', 'main');
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
