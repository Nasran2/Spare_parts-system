<?php

namespace App\Support;

use App\Models\Accounting\BankAccount;
use Illuminate\Validation\ValidationException;

class PaymentBankAccount
{
    public const METHODS = ['bank_deposit', 'bank_transfer', 'card', 'mobile_payment', 'own_cheque'];

    public static function resolve(string $method, mixed $id, string $field = 'bank_account_id'): ?BankAccount
    {
        if (! in_array($method, [...self::METHODS, 'cheque'], true)) {
            return null;
        }

        if (! $id) {
            if (in_array($method, self::METHODS, true) && BankAccount::where('is_active', true)->exists()) {
                throw ValidationException::withMessages([$field => 'Select the bank account for this payment.']);
            }

            return null;
        }

        if (! is_scalar($id) || ! filter_var($id, FILTER_VALIDATE_INT) || (int) $id < 1) {
            throw ValidationException::withMessages([$field => 'Select an active bank account.']);
        }
        $bank = BankAccount::with('chartAccount')->where('is_active', true)->find((int) $id);
        if (! $bank || ! $bank->chartAccount?->is_active) {
            throw ValidationException::withMessages([$field => 'Select an active bank account.']);
        }

        return $bank;
    }
}
