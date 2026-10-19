<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\AccountTransaction;
use App\Models\Accounting\BankAccount;
use App\Models\Accounting\ChartAccount;
use App\Models\ActivityLog;
use App\Models\Payment;
use App\Services\AccountBalanceService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BankBalanceController extends Controller
{
    public function index(Request $request, AccountBalanceService $balances)
    {
        $request->validate([
            'account' => ['nullable', 'regex:/^(cash|unassigned|[1-9][0-9]*)$/'],
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
            'direction' => ['nullable', 'in:in,out'], 'search' => ['nullable', 'string', 'max:100'],
        ]);
        $banks = BankAccount::with('chartAccount')->orderBy('bank_name')->get();
        $account = (string) $request->input('account', $banks->first()?->id ?? 'cash');
        $bank = in_array($account, ['cash', 'unassigned'], true) ? null : $banks->firstWhere('id', (int) $account);
        if (! $bank && ! in_array($account, ['cash', 'unassigned'], true)) {
            abort(404);
        }
        $today = today()->toDateString();
        $from = $request->has('from') ? $request->input('from') : ($request->boolean('all_dates') ? null : now()->startOfMonth()->toDateString());
        $to = $request->has('to') ? $request->input('to') : $today;
        $cards = $banks->map(fn ($item) => ['bank' => $item, 'balance' => $balances->balance($item, $today)]);
        $cashBalance = $balances->balance(null, $today);
        $unassignedCount = $balances->unassigned()->count();
        $query = $account === 'unassigned' ? $balances->unassigned() : $balances->movements($bank);
        $opening = $account === 'unassigned' ? 0 : $balances->opening($bank);
        if ($from) {
            $opening += $balances->signedTotal((clone $query)->whereDate('transaction_date', '<', $from));
        }
        $rows = $query->with(['user', 'bankAccount'])
            ->when($from, fn ($q) => $q->whereDate('transaction_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('transaction_date', '<=', $to))
            ->orderBy('transaction_date')->orderBy('id')->get();
        $totals = ['opening' => round($opening, 2), 'in' => 0.0, 'out' => 0.0];
        $running = $opening;
        foreach ($rows as $row) {
            $totals[$row->direction] += (float) $row->amount;
            $running = round($running + ($row->direction === 'in' ? 1 : -1) * (float) $row->amount, 2);
            $row->running_balance = $running;
        }
        $totals['closing'] = $running;
        $rows = $rows->filter(function ($row) use ($request) {
            if ($request->filled('direction') && $row->direction !== $request->direction) {
                return false;
            }
            if ($request->filled('search')) {
                return str_contains(mb_strtolower($row->description.' '.$row->reference_no.' '.$row->cheque_number), mb_strtolower($request->search));
            }

            return true;
        })->values();
        $perPage = $request->attributes->get('business_export') ? max(1, $rows->count()) : 50;
        $page = $request->attributes->get('business_export') ? 1 : LengthAwarePaginator::resolveCurrentPage();
        $history = new LengthAwarePaginator($rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page, ['path' => $request->url(), 'query' => $request->query()]);
        $payments = Payment::whereIn('id', $history->getCollection()->where('source_type', 'payment')->pluck('source_id'))->get()->keyBy('id');
        foreach ($history as $row) {
            $row->detail_url = match ($row->source_type) {
                'expense' => route('expenses.show', $row->source_id),
                'payment' => $this->paymentUrl($payments->get($row->source_id)),
                'cheque_payment' => route('cheque-payments.index', ['search' => $row->cheque_number]),
                default => null,
            };
        }
        $effectiveOpening = $account !== 'unassigned' ? $balances->opening($bank) + $balances->signedTotal($balances->movements($bank)->where('source_type', $bank ? 'bank_opening_balance' : 'cash_opening_balance')) : 0;

        return view('accounting.bank-balances', compact('banks', 'cards', 'account', 'bank', 'cashBalance', 'unassignedCount', 'from', 'to', 'totals', 'history', 'effectiveOpening'));
    }

    private function paymentUrl(?Payment $payment): ?string
    {
        if (! $payment) {
            return null;
        }
        if ($payment->sale_id) {
            return route('sales.show', $payment->sale_id);
        }
        if ($payment->purchase_id) {
            return route('purchases.show', $payment->purchase_id);
        }
        if ($payment->pre_order_id) {
            return route('preorders.show', $payment->pre_order_id);
        }
        if ($payment->customer_id) {
            return route('customers.show', $payment->customer_id);
        }
        if ($payment->supplier_id) {
            return route('suppliers.show', $payment->supplier_id);
        }

        return null;
    }

    public function transfer(Request $request, AccountBalanceService $balances)
    {
        $data = $request->validate([
            'from_account' => ['required', 'regex:/^(cash|[1-9][0-9]*)$/'],
            'to_account' => ['required', 'different:from_account', 'regex:/^(cash|[1-9][0-9]*)$/'],
            'amount' => ['required', 'numeric', 'min:0.01', 'decimal:0,2', 'max:9999999999999.99'],
            'transfer_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'note' => ['required', 'string', 'max:255'], 'request_token' => ['required', 'uuid'],
        ]);
        $balances->transfer($data, $request->user()->id);

        return back()->with('success', 'Transfer recorded. Both account balances and histories have been updated.');
    }

    public function opening(Request $request, AccountBalanceService $balances, ?BankAccount $bankAccount = null)
    {
        $data = $request->validate([
            'opening_balance' => ['required', 'numeric', 'decimal:0,2'],
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'reason' => ['required', 'string', 'max:200'],
        ]);
        $balances->correctOpening($bankAccount, (float) $data['opening_balance'], $data['date'], $data['reason'], $request->user()->id);

        return back()->with('success', 'Opening balance updated. The correction and reason are saved in account history.');
    }

    public function assign(Request $request, AccountTransaction $accountTransaction, AccountBalanceService $balances)
    {
        $data = $request->validate(['bank_account_id' => ['required', 'integer', 'exists:bank_accounts,id'], 'reason' => ['required', 'string', 'max:200']]);
        DB::transaction(function () use ($data, $accountTransaction, $balances) {
            $row = $balances->unassigned()->whereKey($accountTransaction->id)->lockForUpdate()->first();
            if (! $row) {
                throw ValidationException::withMessages(['bank_account_id' => 'This entry is already assigned or does not need assignment.']);
            }
            $bank = BankAccount::where('is_active', true)->findOrFail($data['bank_account_id']);
            $old = $row->toArray();
            $signed = ($row->direction === 'in' ? 1 : -1) * (float) $row->amount;
            if ($row->account_id !== $bank->chart_account_id) {
                ChartAccount::whereKey($row->account_id)->decrement('current_balance', $signed);
                ChartAccount::whereKey($bank->chart_account_id)->increment('current_balance', $signed);
            }
            $row->update(['bank_account_id' => $bank->id, 'account_id' => $bank->chart_account_id]);
            ActivityLog::log('update', 'Bank entry assigned: '.$data['reason'], $row, ['old' => $old, 'new' => $row->toArray()]);
        });

        return back()->with('success', 'Bank entry assigned. The selected account balance is updated.');
    }
}
