<?php

namespace App\Services;

use App\Models\Accounting\AccountTransaction;
use App\Models\Accounting\PettyCashExpense;
use App\Models\Accounting\PettyCashFund;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;

class PettyCashStatementService
{
    public function build(Request $request): array
    {
        $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
            'petty_cash_fund_id' => ['nullable', 'integer', 'exists:petty_cash_funds,id'],
            'expense_category_id' => ['nullable', 'integer', 'exists:expense_categories,id'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);
        $from = $request->input('from') ?: null;
        $to = $request->input('to') ?: null;
        $funds = PettyCashFund::with('chartAccount')
            ->when($request->filled('petty_cash_fund_id'), fn ($q) => $q->whereKey($request->integer('petty_cash_fund_id')), fn ($q) => $q->where('is_active', true))
            ->get()->unique('chart_account_id')->keyBy('chart_account_id');
        $transactions = AccountTransaction::with('relatedAccount')->whereIn('account_id', $funds->keys())->get();
        $pettyExpenses = PettyCashExpense::with('category')->whereIn('id', $transactions->where('source_type', 'petty_cash_expense')->pluck('source_id'))->get()->keyBy('id');
        $expenses = Expense::with('category')->whereIn('id', $transactions->where('source_type', 'expense')->pluck('source_id'))->get()->keyBy('id');

        $entries = $transactions->map(function ($transaction) use ($funds, $pettyExpenses, $expenses) {
            $expense = match ($transaction->source_type) {
                'petty_cash_expense' => $pettyExpenses->get($transaction->source_id),
                'expense' => $expenses->get($transaction->source_id),
                default => null,
            };
            $kind = match ($transaction->source_type) {
                'expense', 'petty_cash_expense' => 'Expense',
                'petty_cash_transfer' => $transaction->related_account_id ? 'Transfer' : 'Opening balance',
                default => ucwords(str_replace('_', ' ', $transaction->source_type ?: 'Transaction')),
            };

            return [
                'date' => $transaction->transaction_date->toDateString(),
                'sort_id' => $transaction->id,
                'fund' => $funds->get($transaction->account_id)->name,
                'reference' => $transaction->reference_no ?: ($expense?->voucher_no ?: 'TXN-'.$transaction->id),
                'description' => $transaction->description ?: $expense?->description ?: $kind,
                'details' => $kind.($expense?->category ? ' / '.$expense->category->name : ($transaction->relatedAccount ? ' / '.$transaction->relatedAccount->name : '')),
                'category_id' => $expense?->expense_category_id,
                'direction' => $transaction->direction,
                'amount' => (int) round((float) $transaction->amount * 100),
            ];
        });

        // Fund creation already writes its opening deposit to the ledger. Only
        // synthesize it for older funds without that entry, never add it twice.
        foreach ($funds as $accountId => $fund) {
            $fundTransactions = $transactions->where('account_id', $accountId);
            $hasOpeningEntry = $fundTransactions->contains(fn ($row) => $row->source_type === 'petty_cash_transfer'
                && (int) $row->source_id === $fund->id && $row->direction === 'in'
                && ! $row->related_account_id && strtolower(trim($row->description ?? '')) === 'opening balance');
            if (! $hasOpeningEntry && (float) $fund->opening_balance != 0) {
                $firstMovement = $fundTransactions->min(fn ($row) => $row->transaction_date->toDateString());
                $openingDate = $fund->created_at->toDateString();
                $openingDate = $firstMovement ? min($openingDate, $firstMovement) : $openingDate;
                $entries->push([
                    'date' => $openingDate, 'sort_id' => -$fund->id, 'fund' => $fund->name,
                    'reference' => 'OPENING-'.$fund->id, 'description' => 'Opening balance', 'details' => 'Initial fund balance',
                    'category_id' => null, 'direction' => (float) $fund->opening_balance >= 0 ? 'in' : 'out',
                    'amount' => (int) round(abs((float) $fund->opening_balance) * 100),
                ]);
            }
        }
        $entries = $entries->sort(fn ($a, $b) => ($a['date'] <=> $b['date']) ?: ($a['sort_id'] <=> $b['sort_id']));
        // Always print concrete report dates, even when the filters are blank.
        $to ??= max(today()->toDateString(), $from ?? today()->toDateString());
        $from ??= $entries->where('date', '<=', $to)->min('date') ?? $to;
        $opening = 0;
        $running = 0;
        $moneyIn = 0;
        $moneyOut = 0;
        $rows = collect();
        foreach ($entries as $entry) {
            if ($to && $entry['date'] > $to) {
                continue;
            }
            $signed = $entry['direction'] === 'in' ? $entry['amount'] : -$entry['amount'];
            $running += $signed;
            if ($from && $entry['date'] < $from) {
                $opening += $signed;

                continue;
            }
            if ($entry['direction'] === 'in') {
                $moneyIn += $entry['amount'];
            } else {
                $moneyOut += $entry['amount'];
            }
            $entry['balance'] = $running / 100;
            $entry['amount'] /= 100;
            // Display filters do not remove money from actual account balances.
            if ($request->filled('expense_category_id') && (int) $entry['category_id'] !== $request->integer('expense_category_id')) {
                continue;
            }
            if ($request->filled('search') && ! str_contains(mb_strtolower($entry['reference'].' '.$entry['description']), mb_strtolower(trim($request->input('search'))))) {
                continue;
            }
            $rows->push($entry);
        }

        return [
            'title' => 'Petty Cash Statement', 'from' => $from, 'to' => $to,
            'fundLabel' => $request->filled('petty_cash_fund_id') ? $funds->first()?->name : 'All active petty cash funds',
            'rows' => $rows, 'opening' => $opening / 100, 'closing' => $running / 100,
            'moneyIn' => $moneyIn / 100, 'moneyOut' => $moneyOut / 100,
            'currency' => trim(config('app.currency', 'Rs')),
            'search' => $request->input('search'),
            'category' => $request->filled('expense_category_id') ? ExpenseCategory::find($request->integer('expense_category_id'))?->name : null,
        ];
    }
}
