<?php

namespace App\Services;

use App\Models\Accounting\AccountTransaction;
use App\Models\Accounting\ChartAccount;
use App\Models\ChequePayment;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class DailyLedgerReportService
{
    public const SECTIONS = [
        'main' => 'Main Account (Cash) Transactions',
        'cheques' => 'Customer Receivable (Cheques)',
        'petty' => 'Petty Cash Transactions',
        'sales' => 'Sales Transactions',
    ];

    public function build(Request $request): array
    {
        $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
            'sections' => [Rule::requiredIf($request->boolean('section_selection')), 'array', 'min:1'],
            'sections.*' => ['string', Rule::in(array_keys(self::SECTIONS))],
        ], ['sections.required' => 'Select at least one section to export.', 'sections.min' => 'Select at least one section to export.']);
        $from = $request->input('from') ?: null;
        $to = $request->input('to') ?: max($from ?? today()->toDateString(), today()->toDateString());
        if (! $from) {
            // The All Time quick filter submits empty dates; keep its full history.
            $from = $request->has('from') || $request->filled('to') ? $this->firstActivityDate($to) : $to;
        }
        $selectedSections = array_values(array_intersect(array_keys(self::SECTIONS), $request->input('sections', array_keys(self::SECTIONS))));
        $balances = app(AccountBalanceService::class);
        $mainQuery = $balances->movements(null);
        $mainOpening = $balances->opening(null) + $balances->signedTotal((clone $mainQuery)->whereDate('transaction_date', '<', $from));
        $main = $this->ledger($mainQuery->with(['account', 'relatedAccount'])->whereDate('transaction_date', '>=', $from)->whereDate('transaction_date', '<=', $to)->get(), $mainOpening, $from, $to);
        $receivable = ChartAccount::where('code', '1300')->first();
        $cheques = $this->chequeMovements($receivable);
        $cheques = $this->ledger($cheques, (float) ($receivable?->opening_balance ?? 0), $from, $to);
        $pettyData = app(PettyCashStatementService::class)->build(Request::create('/', 'GET', ['from' => $from, 'to' => $to]));
        $petty = ['opening' => $pettyData['opening'], 'closing' => $pettyData['closing'], 'in' => $pettyData['moneyIn'], 'out' => $pettyData['moneyOut'],
            'rows' => $pettyData['rows']->map(fn ($row) => [
                'date' => $row['date'], 'type' => $row['details'], 'account' => $row['fund'], 'related' => '',
                'reference' => $row['reference'], 'description' => $row['description'],
                'in' => $row['direction'] === 'in' ? $row['amount'] : 0,
                'out' => $row['direction'] === 'out' ? $row['amount'] : 0, 'balance' => $row['balance'],
            ])];
        $pettyAccountIds = \App\Models\Accounting\PettyCashFund::where('is_active', true)->pluck('chart_account_id');
        $pettyPeriod = AccountTransaction::whereIn('account_id', $pettyAccountIds)->whereDate('transaction_date', '>=', $from)->whereDate('transaction_date', '<=', $to);
        $petty['revenue'] = (float) (clone $pettyPeriod)->where('direction', 'in')->whereHas('relatedAccount', fn ($query) => $query->whereIn('code', ['4000', '4100']))->sum('amount');
        $petty['expenses'] = (float) (clone $pettyPeriod)->where('direction', 'out')->whereHas('relatedAccount', fn ($query) => $query->where('type', 'expense'))->sum('amount');
        $ledgerSections = ['main' => $main, 'cheques' => $cheques, 'petty' => $petty];
        $salesReceipts = collect($ledgerSections)->sum('revenue');
        $periodExpenses = collect($ledgerSections)->sum('expenses');
        [$salesRows, $salesTotals] = $this->sales($from, $to);
        $sectionLabels = self::SECTIONS;
        $currency = trim(config('app.currency', 'Rs'));

        return compact('from', 'to', 'selectedSections', 'sectionLabels', 'ledgerSections', 'salesRows', 'salesTotals', 'currency', 'salesReceipts', 'periodExpenses');
    }

    private function firstActivityDate(string $to): string
    {
        $fundIds = \App\Models\Accounting\PettyCashFund::where('is_active', true)->pluck('chart_account_id');
        $accountIds = ChartAccount::whereIn('code', ['1100', '1300'])->pluck('id')->merge($fundIds);
        $cashId = ChartAccount::where('code', '1100')->value('id');
        $dates = collect([
            AccountTransaction::where(function ($query) use ($accountIds, $cashId) {
                $query->whereIn('account_id', $accountIds);
                if ($cashId) {
                    $query->orWhere(fn ($query) => $query->where('related_account_id', $cashId)->where('source_type', 'manual'));
                }
            })->whereDate('transaction_date', '<=', $to)->min('transaction_date'),
            Sale::where('sale_type', 'sale')->whereDate('sale_date', '<=', $to)->min('sale_date'),
            ChequePayment::where('type', '!=', 'own')->whereDate('created_at', '<=', $to)->min('created_at'),
            \App\Models\Accounting\PettyCashFund::where('is_active', true)->whereDate('created_at', '<=', $to)->min('created_at'),
        ])->filter()->map(fn ($date) => substr((string) $date, 0, 10));

        return $dates->min() ?: $to;
    }

    private function ledger(Collection $transactions, float $base, string $from, string $to): array
    {
        $opening = (int) round($base * 100);
        $running = $opening;
        $moneyIn = 0;
        $moneyOut = 0;
        $revenue = 0;
        $expenses = 0;
        $rows = collect();
        $paymentCheques = ChequePayment::whereIn('payment_id', $transactions->where('source_type', 'payment')->pluck('source_id'))->get()->keyBy('payment_id');
        foreach ($transactions->sort(fn ($a, $b) => ($a->transaction_date <=> $b->transaction_date) ?: ($a->id <=> $b->id)) as $transaction) {
            $date = $transaction->transaction_date->toDateString();
            if ($date > $to) {
                continue;
            }
            $cents = (int) round((float) $transaction->amount * 100);
            $signed = $transaction->direction === 'in' ? $cents : -$cents;
            $running += $signed;
            if ($date < $from) {
                $opening += $signed;

                continue;
            }
            if ($transaction->direction === 'in') {
                $moneyIn += $cents;
            } else {
                $moneyOut += $cents;
            }
            if ($transaction->direction === 'in' && in_array((string) $transaction->relatedAccount?->code, ['4000', '4100'], true)) {
                $revenue += $cents;
            }
            if ($transaction->direction === 'out' && $transaction->relatedAccount?->type === 'expense') {
                $expenses += $cents;
            }
            $description = $transaction->description ?? '';
            $cheque = $transaction->source_type === 'payment' ? $paymentCheques->get($transaction->source_id) : null;
            if ($cheque) {
                $description .= ' | Cheque: '.$cheque->bank_name.' / '.$cheque->cheque_number.' / '.$cheque->cheque_date?->toDateString();
            } elseif ($transaction->cheque_number) {
                $description .= ' | Cheque: '.$transaction->cheque_number;
            }
            $rows->push([
                'date' => $date, 'type' => ucwords(str_replace('_', ' ', $transaction->source_type ?: 'Transaction')),
                'account' => $transaction->account?->name ?? 'Customer cheques', 'related' => $transaction->relatedAccount?->name ?? '',
                'reference' => $transaction->reference_no ?: 'TXN-'.$transaction->id, 'description' => $description,
                'in' => $transaction->direction === 'in' ? $cents / 100 : 0,
                'out' => $transaction->direction === 'out' ? $cents / 100 : 0, 'balance' => $running / 100,
            ]);
        }

        return ['opening' => $opening / 100, 'closing' => $running / 100, 'in' => $moneyIn / 100, 'out' => $moneyOut / 100, 'rows' => $rows, 'revenue' => $revenue / 100, 'expenses' => $expenses / 100];
    }

    private function chequeMovements(?ChartAccount $account): Collection
    {
        $rows = $account ? AccountTransaction::with(['account', 'relatedAccount'])->where('account_id', $account->id)->get() : collect();
        if ($account) {
            // Clearing is booked on the bank/supplier side; include its receivable side in this report once.
            $passes = AccountTransaction::with('account')->where('related_account_id', $account->id)->where('account_id', '!=', $account->id)->where('source_type', 'cheque_payment')->get();
            foreach ($passes as $pass) {
                if ($rows->contains(fn ($row) => $row->source_type === 'cheque_payment' && $row->source_id === $pass->source_id)) {
                    continue;
                }
                $copy = clone $pass;
                $copy->direction = 'out';
                $copy->setRelation('relatedAccount', $pass->account);
                $copy->setRelation('account', $account);
                $rows->push($copy);
            }
        }
        // Advance cheques have no sale-side hold entry; show receipt and clearance/return without writing new ledger records.
        foreach (ChequePayment::with('customer')->where('type', '!=', 'own')->whereNull('sale_id')->whereNull('pre_order_id')->get() as $cheque) {
            if ($rows->contains(fn ($row) => $row->source_type === 'cheque_payment_hold' && (int) $row->source_id === $cheque->id)) {
                continue;
            }
            $attributes = ['id' => -$cheque->id, 'transaction_date' => $cheque->created_at->toDateString(), 'direction' => 'in', 'amount' => $cheque->amount,
                'source_type' => 'advance_cheque_received', 'cheque_number' => $cheque->cheque_number, 'reference_no' => $cheque->cheque_number,
                'description' => 'Advance cheque received from '.($cheque->customer?->name ?? 'Customer').' / '.$cheque->bank_name];
            $rows->push((new AccountTransaction)->forceFill($attributes));
            if ($cheque->status !== 'pending' && $cheque->processed_at) {
                $rows->push((new AccountTransaction)->forceFill(array_replace($attributes, ['id' => $cheque->id, 'transaction_date' => $cheque->processed_at->toDateString(), 'direction' => 'out',
                    'source_type' => 'advance_cheque_'.$cheque->status, 'description' => 'Advance cheque '.$cheque->status.' / '.$cheque->bank_name])));
            }
        }

        return $rows;
    }

    private function sales(string $from, string $to): array
    {
        $sales = Sale::with(['customer', 'payments', 'chequePayments'])->where('sale_type', 'sale')
            ->whereDate('sale_date', '>=', $from)->whereDate('sale_date', '<=', $to)->orderBy('sale_date')->orderBy('id')->get();
        $rows = collect();
        $totals = ['cash' => 0, 'cheque' => 0, 'other' => 0, 'credit' => 0, 'total' => 0, 'count' => $sales->count()];
        foreach ($sales as $sale) {
            $amount = max(0, (int) round((float) $sale->total_amount * 100));
            $parts = ['cash' => 0, 'cheque' => 0, 'other' => 0];
            $linkedPaymentIds = $sale->chequePayments->pluck('payment_id')->filter();
            foreach ($sale->payments as $payment) {
                if ($payment->payment_date?->toDateString() > $to || $payment->payment_method === 'advance_deduction' || $linkedPaymentIds->contains($payment->id)) {
                    continue;
                }
                $key = match ($payment->payment_method) {
                    'cash' => 'cash', 'cheque' => 'cheque', default => 'other'
                };
                $parts[$key] += (int) round((float) $payment->amount * 100);
            }
            foreach ($sale->chequePayments as $cheque) {
                if ($cheque->created_at->toDateString() > $to || ($cheque->status === 'returned' && $cheque->processed_at?->toDateString() <= $to)) {
                    continue;
                }
                $parts['cheque'] += (int) round((float) $cheque->amount * 100);
            }
            // Older sales may only store the payment on the invoice itself.
            if ($sale->payments->isEmpty() && $sale->chequePayments->isEmpty()) {
                $key = match ($sale->payment_method) {
                    'cash' => 'cash', 'cheque' => 'cheque', default => 'other'
                };
                $parts[$key] = (int) round((float) $sale->paid_amount * 100);
                $parts['cheque'] += (int) round((float) $sale->held_cheque_amount * 100);
            }
            $remaining = $amount;
            foreach ($parts as $key => $value) {
                $parts[$key] = min($remaining, max(0, $value));
                $remaining -= $parts[$key];
            }
            $row = ['date' => $sale->sale_date->toDateString(), 'invoice' => $sale->sale_no, 'customer' => $sale->customer?->name ?? 'Walk-in Customer',
                'cash' => $parts['cash'] / 100, 'cheque' => $parts['cheque'] / 100, 'other' => $parts['other'] / 100, 'credit' => $remaining / 100, 'total' => $amount / 100,
                'notes' => $sale->notes ?? '', 'url' => route('sales.show', $sale)];
            $rows->push($row);
            foreach (['cash', 'cheque', 'other', 'credit'] as $key) {
                $totals[$key] += $key === 'credit' ? $remaining : $parts[$key];
            }
            $totals['total'] += $amount;
        }
        foreach (['cash', 'cheque', 'other', 'credit', 'total'] as $key) {
            $totals[$key] /= 100;
        }

        return [$rows, $totals];
    }
}
