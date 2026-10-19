<?php

namespace App\Services;

use App\Models\Accounting\AccountTransaction;
use App\Models\Accounting\AccountTransfer;
use App\Models\Accounting\BankAccount;
use App\Models\Accounting\ChartAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AccountBalanceService
{
    public function cashAccount(): ChartAccount
    {
        return ChartAccount::firstOrCreate(['code' => '1100'], ['name' => 'Cash', 'type' => 'asset', 'subtype' => 'cash', 'opening_balance' => 0, 'current_balance' => 0, 'is_active' => true, 'is_system' => true]);
    }

    public function movements(?BankAccount $bank): Builder
    {
        $query = AccountTransaction::query();
        if (! $bank) {
            $cashId = $this->cashAccount()->id;
            // Manual journal entries have one row; their cash counterpart is still a cash movement.
            // Payment, expense and transfer services already create the cash-side row themselves.
            $columns = Schema::getColumnListing('account_transactions');
            $cashSide = AccountTransaction::where('related_account_id', $cashId)->where('account_id', '!=', $cashId)
                ->where('source_type', 'manual')->selectRaw(collect($columns)->map(fn ($column) => match ($column) {
                    'direction' => "CASE WHEN direction = 'in' THEN 'out' ELSE 'in' END AS direction",
                    'account_id' => $cashId.' AS account_id',
                    'related_account_id' => 'account_id AS related_account_id',
                    default => '`'.$column.'`',
                })->implode(', '));
            $direct = $query->where('account_id', $cashId)->select($columns)->unionAll($cashSide);

            return AccountTransaction::query()->fromSub($direct, 'account_transactions');
        }

        // Older entries can only be attributed automatically when exactly one bank uses the account.
        $uniqueBank = BankAccount::where('chart_account_id', $bank->chart_account_id)->count() === 1;

        return $query->where(function ($query) use ($bank, $uniqueBank) {
            $query->where('bank_account_id', $bank->id);
            if ($uniqueBank) {
                $query->orWhere(fn ($q) => $q->whereNull('bank_account_id')->where('account_id', $bank->chart_account_id));
            }
        });
    }

    public function opening(?BankAccount $bank): float
    {
        return (float) ($bank?->opening_balance ?? $this->cashAccount()->opening_balance);
    }

    public function signedTotal(Builder $query): float
    {
        return round((float) $query->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE -amount END), 0) as movement")->value('movement'), 2);
    }

    public function balance(?BankAccount $bank, ?string $date = null): float
    {
        $query = $this->movements($bank)->when($date, fn ($q) => $q->whereDate('transaction_date', '<=', $date));

        return round($this->opening($bank) + $this->signedTotal($query), 2);
    }

    public function unassigned(): Builder
    {
        return AccountTransaction::whereNull('bank_account_id')->whereIn('account_id',
            BankAccount::select('chart_account_id')->groupBy('chart_account_id')->havingRaw('COUNT(*) > 1')
        );
    }

    public function transfer(array $data, int $userId): AccountTransfer
    {
        return DB::transaction(function () use ($data, $userId) {
            $from = (string) $data['from_account'];
            $to = (string) $data['to_account'];
            if ($from === $to) {
                throw ValidationException::withMessages(['to_account' => 'Choose a different destination account.']);
            }
            $banks = BankAccount::with('chartAccount')->whereIn('id', array_filter([$from === 'cash' ? null : $from, $to === 'cash' ? null : $to]))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ([$from, $to] as $key) {
                if ($key !== 'cash' && (! isset($banks[$key]) || ! $banks[$key]->is_active || ! $banks[$key]->chartAccount?->is_active)) {
                    throw ValidationException::withMessages(['from_account' => 'Select active accounts for this transfer.']);
                }
            }
            $cash = $this->cashAccount();
            $fromChart = $from === 'cash' ? $cash : $banks[$from]->chartAccount;
            $toChart = $to === 'cash' ? $cash : $banks[$to]->chartAccount;
            ChartAccount::whereIn('id', [$fromChart->id, $toChart->id])->orderBy('id')->lockForUpdate()->get();
            $existing = AccountTransfer::where('request_token', $data['request_token'])->first();
            if ($existing) {
                if ($existing->user_id !== $userId || (string) ($existing->from_bank_id ?? 'cash') !== (string) $from || (string) ($existing->to_bank_id ?? 'cash') !== (string) $to || round((float) $existing->amount, 2) !== round((float) $data['amount'], 2) || $existing->note !== $data['note'] || $existing->transfer_date->toDateString() !== $data['transfer_date']) {
                    throw ValidationException::withMessages(['request_token' => 'This transfer reference has already been used. Refresh the form.']);
                }

                return $existing;
            }
            $amount = round((float) $data['amount'], 2);
            $transfer = AccountTransfer::create([
                'request_token' => $data['request_token'], 'from_bank_id' => $from === 'cash' ? null : $from,
                'to_bank_id' => $to === 'cash' ? null : $to, 'user_id' => $userId,
                'transfer_date' => $data['transfer_date'], 'amount' => $amount, 'note' => $data['note'],
            ]);
            $fromLabel = $from === 'cash' ? 'Cash' : $banks[$from]->bank_name.' / '.$banks[$from]->account_number;
            $toLabel = $to === 'cash' ? 'Cash' : $banks[$to]->bank_name.' / '.$banks[$to]->account_number;
            foreach ([['out', $from, $fromChart, $toChart], ['in', $to, $toChart, $fromChart]] as [$direction, $key, $account, $other]) {
                AccountTransaction::create([
                    'account_id' => $account->id, 'related_account_id' => $other->id,
                    'bank_account_id' => $key === 'cash' ? null : $key, 'user_id' => $userId,
                    'transaction_date' => $data['transfer_date'], 'direction' => $direction,
                    'payment_method' => 'bank_transfer', 'amount' => $amount,
                    'reference_no' => 'TRF-'.str_pad((string) $transfer->id, 6, '0', STR_PAD_LEFT),
                    'source_type' => 'account_transfer', 'source_id' => $transfer->id,
                    'description' => $fromLabel.' → '.$toLabel.': '.$data['note'],
                ]);
            }
            $fromChart->decrement('current_balance', $amount);
            $toChart->increment('current_balance', $amount);

            return $transfer;
        }, 3);
    }

    public function correctOpening(?BankAccount $bank, float $target, string $date, string $reason, int $userId): void
    {
        DB::transaction(function () use ($bank, $target, $date, $reason, $userId) {
            $bank = $bank ? BankAccount::lockForUpdate()->findOrFail($bank->id) : null;
            $chart = $bank ? $bank->chartAccount : $this->cashAccount();
            ChartAccount::whereKey($chart->id)->lockForUpdate()->firstOrFail();
            $type = $bank ? 'bank_opening_balance' : 'cash_opening_balance';
            $previous = $this->opening($bank) + $this->signedTotal($this->movements($bank)->where('source_type', $type));
            $delta = round($target - $previous, 2);
            if ($delta === 0.0) {
                return;
            }
            AccountTransaction::create([
                'account_id' => $chart->id, 'bank_account_id' => $bank?->id, 'user_id' => $userId,
                'transaction_date' => $date, 'direction' => $delta > 0 ? 'in' : 'out', 'amount' => abs($delta),
                'payment_method' => $bank ? 'bank_transfer' : 'cash', 'source_type' => $type, 'source_id' => $bank?->id ?? $chart->id,
                'reference_no' => 'OPENING-'.($bank?->id ?? 'CASH'),
                'description' => 'Opening balance corrected from '.number_format($previous, 2).' to '.number_format($target, 2).': '.$reason,
            ]);
            ChartAccount::whereKey($chart->id)->increment('current_balance', $delta);
        });
    }
}
