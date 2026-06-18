<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\AccountTransaction;
use App\Models\Accounting\BankAccount;
use App\Models\Accounting\BankReconciliation;
use App\Models\Accounting\ChartAccount;
use App\Models\Accounting\OwnerEquityMovement;
use App\Models\Accounting\PettyCashExpense;
use App\Models\Accounting\PettyCashFund;
use App\Models\ChequePayment;
use App\Models\ExpenseCategory;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AccountingController extends Controller
{
    public function index(Request $request)
    {
        return $this->renderSection($request, 'overview');
    }

    public function accounts(Request $request)
    {
        return $this->renderSection($request, 'accounts');
    }

    public function transactions(Request $request)
    {
        return $this->renderSection($request, 'transactions');
    }

    public function banks(Request $request)
    {
        return $this->renderSection($request, 'banks');
    }

    public function pettyCash(Request $request)
    {
        return $this->renderSection($request, 'petty-cash');
    }

    public function ledger(Request $request)
    {
        return $this->renderSection($request, 'ledger');
    }

    public function tAccounts(Request $request)
    {
        return $this->renderSection($request, 't-accounts');
    }

    public function trialBalance(Request $request)
    {
        return $this->renderSection($request, 'trial-balance');
    }

    public function balanceSheet(Request $request)
    {
        return $this->renderSection($request, 'balance-sheet');
    }

    public function cashBook(Request $request)
    {
        return $this->renderSection($request, 'cash-book');
    }

    public function bankBook(Request $request)
    {
        return $this->renderSection($request, 'bank-book');
    }

    public function ownerEquity(Request $request)
    {
        return $this->renderSection($request, 'owner-equity');
    }

    private function renderSection(Request $request, string $section)
    {
        $accounts = $this->accountsQuery($request)->orderBy('code')->get();
        $allAccounts = ChartAccount::orderBy('code')->get(['id', 'code', 'name', 'type']);
        $transactions = $this->transactionsQuery($request)
            ->take($section === 'ledger' ? 100 : 15)
            ->get();
        $banks = BankAccount::with('chartAccount')
            ->where('is_active', true)
            ->latest()
            ->get();
        $pettyFunds = PettyCashFund::with('chartAccount')->where('is_active', true)->get();
        $categories = ExpenseCategory::where('is_active', true)->orderBy('name')->get();
        $reconciliations = $this->reconciliationsQuery($request)->take(50)->get();
        $pettyExpenses = $this->pettyExpensesQuery($request)->take(100)->get();
        $tAccounts = $this->tAccountsData($request);
        $trialBalanceRows = $this->trialBalanceRows($request);
        $trialBalanceTotals = [
            'debit' => (float) $trialBalanceRows->sum('debit_balance'),
            'credit' => (float) $trialBalanceRows->sum('credit_balance'),
        ];
        $trialBalanceTotals['difference'] = round($trialBalanceTotals['debit'] - $trialBalanceTotals['credit'], 2);
        $balanceSheet = $this->balanceSheetData();
        $cashBookTransactions = $this->cashBookTransactions($request)->get();
        $cashBookTotals = $this->bookTotals($cashBookTransactions);
        $bankBookTransactions = $this->bankBookTransactions($request)->get();
        $bankBookTotals = $this->bookTotals($bankBookTransactions);
        $ownerEquityMovements = $this->ownerEquityQuery($request)->get();
        $ownerEquityTotals = [
            'investment' => (float) $this->ownerEquityQuery($request)->where('type', 'investment')->sum('amount'),
            'withdrawal' => (float) $this->ownerEquityQuery($request)->where('type', 'withdrawal')->sum('amount'),
        ];
        $ownerEquityTotals['net'] = round($ownerEquityTotals['investment'] - $ownerEquityTotals['withdrawal'], 2);
        $canViewChequePayments = $request->user()?->hasPermission('cheque_payments.view') ?? false;
        $pendingCheques = collect();
        $chequeSummary = ['pending_count' => 0, 'pending_amount' => 0.0, 'passed_amount' => 0.0];
        if ($canViewChequePayments) {
            $pendingCheques = ChequePayment::with(['sale', 'customer'])
                ->where('status', 'pending')
                ->orderBy('cheque_date')
                ->take(5)
                ->get();
            $chequeSummary = [
                'pending_count' => ChequePayment::where('status', 'pending')->count(),
                'pending_amount' => (float) ChequePayment::where('status', 'pending')->sum('amount'),
                'passed_amount' => (float) ChequePayment::where('status', 'passed')
                    ->when($request->filled('from'), fn ($query) => $query->whereDate('processed_at', '>=', $request->input('from')))
                    ->when($request->filled('to'), fn ($query) => $query->whereDate('processed_at', '<=', $request->input('to')))
                    ->sum('amount'),
            ];
        }

        $balanceTotals = [
            'assets' => ChartAccount::where('type', 'asset')->sum('current_balance'),
            'liabilities' => ChartAccount::where('type', 'liability')->sum('current_balance'),
            'income' => ChartAccount::where('type', 'income')->sum('current_balance'),
            'expenses' => ChartAccount::where('type', 'expense')->sum('current_balance'),
        ];
        $overviewPeriodActive = $section === 'overview';
        $periodTotals = $this->overviewPeriodTotals($request);
        $totals = [
            'assets' => $balanceTotals['assets'],
            'liabilities' => $balanceTotals['liabilities'],
            'income' => $section === 'overview' ? $periodTotals['income'] : $balanceTotals['income'],
            'expenses' => $section === 'overview' ? $periodTotals['expense'] : $balanceTotals['expenses'],
        ];

        return view('accounting.section', compact(
            'section',
            'accounts',
            'allAccounts',
            'transactions',
            'banks',
            'pettyFunds',
            'pettyExpenses',
            'categories',
            'reconciliations',
            'totals',
            'overviewPeriodActive',
            'pendingCheques',
            'chequeSummary',
            'canViewChequePayments',
            'tAccounts',
            'trialBalanceRows',
            'trialBalanceTotals',
            'balanceSheet',
            'cashBookTransactions',
            'cashBookTotals',
            'bankBookTransactions',
            'bankBookTotals',
            'ownerEquityMovements',
            'ownerEquityTotals'
        ));
    }

    private function balanceSheetData(): array
    {
        $groups = ChartAccount::orderBy('code')
            ->get()
            ->groupBy('type');

        $rows = [
            'assets' => $groups->get('asset', collect())->values(),
            'liabilities' => $groups->get('liability', collect())->values(),
            'equity' => $groups->get('equity', collect())->values(),
        ];

        $totals = [
            'assets' => (float) $rows['assets']->sum('current_balance'),
            'liabilities' => (float) $rows['liabilities']->sum('current_balance'),
            'equity' => (float) $rows['equity']->sum('current_balance'),
        ];
        $totals['liabilities_equity'] = round($totals['liabilities'] + $totals['equity'], 2);
        $totals['difference'] = round($totals['assets'] - $totals['liabilities_equity'], 2);

        return ['rows' => $rows, 'totals' => $totals];
    }

    private function cashBookTransactions(Request $request)
    {
        return AccountTransaction::with(['account', 'relatedAccount', 'bankAccount'])
            ->where(function ($query) {
                $query->whereHas('account', fn ($q) => $q->where('code', '1100')->orWhere('subtype', 'cash'))
                    ->orWhere('payment_method', 'cash');
            })
            ->when($request->filled('from'), fn ($query) => $query->whereDate('transaction_date', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('transaction_date', '<=', $request->input('to')))
            ->when($request->filled('direction'), fn ($query) => $query->where('direction', $request->input('direction')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.$request->input('search').'%';
                $query->where(fn ($q) => $q->where('reference_no', 'like', $term)->orWhere('description', 'like', $term));
            })
            ->latest('transaction_date')
            ->latest()
            ->take(250);
    }

    private function bankBookTransactions(Request $request)
    {
        return AccountTransaction::with(['account', 'relatedAccount'])
            ->where(function ($query) {
                $query->whereHas('account', fn ($q) => $q->where('code', '1200')->orWhere('subtype', 'bank'))
                    ->orWhereIn('payment_method', ['bank_deposit', 'bank_transfer', 'card', 'mobile_payment']);
            })
            ->when($request->filled('from'), fn ($query) => $query->whereDate('transaction_date', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('transaction_date', '<=', $request->input('to')))
            ->when($request->filled('direction'), fn ($query) => $query->where('direction', $request->input('direction')))
            ->when($request->filled('payment_method'), fn ($query) => $query->where('payment_method', $request->input('payment_method')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.$request->input('search').'%';
                $query->where(fn ($q) => $q->where('reference_no', 'like', $term)->orWhere('cheque_number', 'like', $term)->orWhere('description', 'like', $term));
            })
            ->latest('transaction_date')
            ->latest()
            ->take(250);
    }

    private function bookTotals($transactions): array
    {
        $cashIn = (float) $transactions->where('direction', 'in')->sum('amount');
        $cashOut = (float) $transactions->where('direction', 'out')->sum('amount');

        return [
            'in' => round($cashIn, 2),
            'out' => round($cashOut, 2),
            'net' => round($cashIn - $cashOut, 2),
        ];
    }

    private function ownerEquityQuery(Request $request)
    {
        return OwnerEquityMovement::with(['assetAccount', 'equityAccount', 'user'])
            ->when($request->filled('from'), fn ($query) => $query->whereDate('movement_date', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('movement_date', '<=', $request->input('to')))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->input('type')))
            ->when($request->filled('payment_account'), fn ($query) => $query->where('payment_account', $request->input('payment_account')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.$request->input('search').'%';
                $query->where(fn ($q) => $q->where('owner_name', 'like', $term)->orWhere('reference_no', 'like', $term)->orWhere('note', 'like', $term));
            })
            ->latest('movement_date')
            ->latest();
    }

    private function accountingAccountsForReports(Request $request)
    {
        return ChartAccount::query()
            ->when($request->filled('account_id'), fn ($query) => $query->whereKey($request->integer('account_id')))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->input('type')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.$request->input('search').'%';
                $query->where(fn ($q) => $q->where('code', 'like', $term)->orWhere('name', 'like', $term));
            })
            ->orderBy('code');
    }

    private function tAccountsData(Request $request)
    {
        $hasDateFilter = $request->filled('from') || $request->filled('to');
        $reportAccounts = $this->accountingAccountsForReports($request)
            ->with(['transactions' => function ($query) use ($request) {
                $query->when($request->filled('from'), fn ($q) => $q->whereDate('transaction_date', '>=', $request->input('from')))
                    ->when($request->filled('to'), fn ($q) => $q->whereDate('transaction_date', '<=', $request->input('to')))
                    ->latest('transaction_date')
                    ->latest();
            }])
            ->get();

        return $reportAccounts->map(function ($account) use ($hasDateFilter) {
            $debits = $account->transactions
                ->where('direction', 'in')
                ->map(fn (AccountTransaction $transaction) => $this->decorateTAccountTransaction($transaction))
                ->values();
            $credits = $account->transactions
                ->where('direction', 'out')
                ->map(fn (AccountTransaction $transaction) => $this->decorateTAccountTransaction($transaction))
                ->values();
            $debitTotal = (float) $debits->sum('amount');
            $creditTotal = (float) $credits->sum('amount');
            $activityBalance = round($debitTotal - $creditTotal, 2);
            $balance = $hasDateFilter ? $activityBalance : (float) $account->current_balance;
            $normalCredit = in_array($account->type, ['liability', 'equity', 'income'], true);
            $balanceSide = ($normalCredit ? $balance >= 0 : $balance < 0) ? 'Cr' : 'Dr';

            return (object) [
                'account' => $account,
                'debits' => $debits,
                'credits' => $credits,
                'debit_total' => $debitTotal,
                'credit_total' => $creditTotal,
                'balance' => round($balance, 2),
                'balance_side' => $balanceSide,
            ];
        })->values();
    }

    private function decorateTAccountTransaction(AccountTransaction $transaction): AccountTransaction
    {
        $referenceNo = trim((string) $transaction->reference_no);
        $description = trim((string) $transaction->description);
        $paymentMethod = str_replace('_', ' ', ucfirst((string) $transaction->payment_method));

        if ($referenceNo !== '' && in_array($transaction->source_type, ['payment', 'cheque_payment_hold', 'cheque_payment'], true)) {
            $transaction->t_account_title = 'Bill '.$referenceNo;
        } elseif ($description !== '') {
            $transaction->t_account_title = $description;
        } elseif ($referenceNo !== '') {
            $transaction->t_account_title = $referenceNo;
        } else {
            $transaction->t_account_title = $paymentMethod;
        }

        $meta = collect([
            $paymentMethod,
            $transaction->cheque_number ? 'Cheque '.$transaction->cheque_number : null,
        ])->filter()->implode(' · ');

        $transaction->t_account_meta = $meta;

        return $transaction;
    }

    private function trialBalanceRows(Request $request)
    {
        return $this->accountingAccountsForReports($request)->get()->map(function ($account) use ($request) {
            $hasDateFilter = $request->filled('from') || $request->filled('to');
            $balance = $hasDateFilter
                ? round(
                    (float) AccountTransaction::where('account_id', $account->id)
                        ->when($request->filled('from'), fn ($query) => $query->whereDate('transaction_date', '>=', $request->input('from')))
                        ->when($request->filled('to'), fn ($query) => $query->whereDate('transaction_date', '<=', $request->input('to')))
                        ->selectRaw("SUM(CASE WHEN direction = 'in' THEN amount ELSE -amount END) as balance")
                        ->value('balance'),
                    2
                )
                : (float) $account->current_balance;
            $normalCredit = in_array($account->type, ['liability', 'equity', 'income'], true);
            $debitBalance = $normalCredit ? max(0, -1 * $balance) : max(0, $balance);
            $creditBalance = $normalCredit ? max(0, $balance) : max(0, -1 * $balance);

            return (object) [
                'account' => $account,
                'debit_balance' => round($debitBalance, 2),
                'credit_balance' => round($creditBalance, 2),
            ];
        })->filter(fn ($row) => $row->debit_balance > 0 || $row->credit_balance > 0)->values();
    }

    private function overviewPeriodTotals(Request $request): array
    {
        $from = $request->input('from') ?: now()->toDateString();
        $to = $request->input('to') ?: now()->toDateString();

        $rows = AccountTransaction::query()
            ->join('chart_accounts', 'account_transactions.account_id', '=', 'chart_accounts.id')
            ->whereDate('account_transactions.transaction_date', '>=', $from)
            ->whereDate('account_transactions.transaction_date', '<=', $to)
            ->whereIn('chart_accounts.type', ['income', 'expense'])
            ->selectRaw("chart_accounts.type, SUM(CASE WHEN account_transactions.direction = 'in' THEN account_transactions.amount ELSE -account_transactions.amount END) as total")
            ->groupBy('chart_accounts.type')
            ->pluck('total', 'type');

        return [
            'income' => (float) ($rows['income'] ?? 0),
            'expense' => (float) ($rows['expense'] ?? 0),
        ];
    }

    public function export(Request $request, string $section, string $format)
    {
        abort_unless(in_array($section, ['accounts', 'transactions', 'banks', 'petty-cash', 'ledger'], true), 404);
        abort_unless(in_array($format, ['pdf', 'excel'], true), 404);

        [$title, $headers, $rows] = $this->exportRows($request, $section);

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('accounting.export', compact('title', 'headers', 'rows'))->setPaper('a4', 'landscape');

            return $pdf->download(str_replace(' ', '-', strtolower($title)).'.pdf');
        }

        $csv = fopen('php://temp', 'r+');
        fputcsv($csv, $headers);
        foreach ($rows as $row) {
            fputcsv($csv, $row);
        }
        rewind($csv);

        return response(stream_get_contents($csv), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.str_replace(' ', '-', strtolower($title)).'.csv"',
        ]);
    }

    private function accountsQuery(Request $request)
    {
        return ChartAccount::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.$request->input('search').'%';
                $query->where(fn ($q) => $q->where('code', 'like', $term)->orWhere('name', 'like', $term)->orWhere('subtype', 'like', $term));
            })
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->input('type')))
            ->when($request->filled('subtype'), fn ($query) => $query->where('subtype', 'like', '%'.$request->input('subtype').'%'));
    }

    private function transactionsQuery(Request $request)
    {
        return AccountTransaction::with(['account', 'relatedAccount'])
            ->when($request->filled('from'), fn ($query) => $query->whereDate('transaction_date', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('transaction_date', '<=', $request->input('to')))
            ->when($request->filled('account_id'), fn ($query) => $query->where('account_id', $request->integer('account_id')))
            ->when($request->filled('payment_method'), fn ($query) => $query->where('payment_method', $request->input('payment_method')))
            ->when($request->filled('direction'), fn ($query) => $query->where('direction', $request->input('direction')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.$request->input('search').'%';
                $query->where(fn ($q) => $q->where('reference_no', 'like', $term)->orWhere('cheque_number', 'like', $term)->orWhere('description', 'like', $term));
            })
            ->latest('transaction_date')
            ->latest();
    }

    private function reconciliationsQuery(Request $request)
    {
        return BankReconciliation::with('bankAccount')
            ->when($request->filled('from'), fn ($query) => $query->whereDate('statement_date', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('statement_date', '<=', $request->input('to')))
            ->when($request->filled('bank_account_id'), fn ($query) => $query->where('bank_account_id', $request->integer('bank_account_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->latest('statement_date')
            ->latest();
    }

    private function pettyExpensesQuery(Request $request)
    {
        return PettyCashExpense::with(['fund', 'category'])
            ->when($request->filled('from'), fn ($query) => $query->whereDate('expense_date', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('expense_date', '<=', $request->input('to')))
            ->when($request->filled('petty_cash_fund_id'), fn ($query) => $query->where('petty_cash_fund_id', $request->integer('petty_cash_fund_id')))
            ->when($request->filled('expense_category_id'), fn ($query) => $query->where('expense_category_id', $request->integer('expense_category_id')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.$request->input('search').'%';
                $query->where(fn ($q) => $q->where('voucher_no', 'like', $term)->orWhere('description', 'like', $term));
            })
            ->latest('expense_date')
            ->latest();
    }

    private function exportRows(Request $request, string $section): array
    {
        if ($section === 'accounts') {
            $rows = $this->accountsQuery($request)->orderBy('code')->get()->map(fn ($account) => [
                $account->code,
                $account->name,
                ucfirst($account->type),
                $account->subtype,
                number_format((float) $account->opening_balance, 2, '.', ''),
                number_format((float) $account->current_balance, 2, '.', ''),
            ])->all();

            return ['Chart of Accounts', ['Code', 'Account', 'Type', 'Subtype', 'Opening', 'Balance'], $rows];
        }

        if (in_array($section, ['transactions', 'ledger'], true)) {
            $rows = $this->transactionsQuery($request)->get()->map(fn ($transaction) => [
                $transaction->transaction_date?->format('Y-m-d'),
                $transaction->account?->name,
                ucfirst($transaction->direction),
                str_replace('_', ' ', $transaction->payment_method),
                $transaction->cheque_number,
                $transaction->reference_no,
                $transaction->description,
                number_format((float) $transaction->amount, 2, '.', ''),
            ])->all();

            return [$section === 'ledger' ? 'Ledger' : 'Accounting Transactions', ['Date', 'Account', 'Direction', 'Method', 'Cheque No', 'Reference', 'Description', 'Amount'], $rows];
        }

        if ($section === 'banks') {
            $rows = $this->reconciliationsQuery($request)->get()->map(fn ($rec) => [
                $rec->statement_date?->format('Y-m-d'),
                $rec->bankAccount?->bank_name,
                $rec->bankAccount?->account_name,
                number_format((float) $rec->statement_balance, 2, '.', ''),
                number_format((float) $rec->system_balance, 2, '.', ''),
                number_format((float) $rec->difference, 2, '.', ''),
                ucfirst($rec->status),
                $rec->notes,
            ])->all();

            return ['Bank Reconciliation', ['Date', 'Bank', 'Account', 'Statement', 'System', 'Difference', 'Status', 'Notes'], $rows];
        }

        $rows = $this->pettyExpensesQuery($request)->get()->map(fn ($expense) => [
            $expense->expense_date?->format('Y-m-d'),
            $expense->fund?->name,
            $expense->category?->name,
            $expense->voucher_no,
            $expense->description,
            number_format((float) $expense->amount, 2, '.', ''),
        ])->all();

        return ['Petty Cash Expenses', ['Date', 'Fund', 'Category', 'Voucher', 'Description', 'Amount'], $rows];
    }

    public function storeAccount(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:chart_accounts,code',
            'name' => 'required|string|max:255',
            'type' => 'required|in:asset,liability,equity,income,expense',
            'subtype' => 'nullable|string|max:100',
            'opening_balance' => 'nullable|numeric',
        ]);

        $openingBalance = (float) ($validated['opening_balance'] ?? 0);
        $validated['current_balance'] = $openingBalance;
        $validated['opening_balance'] = $openingBalance;

        ChartAccount::create($validated);

        return back()->with('success', 'Account created successfully.');
    }

    public function storeTransaction(Request $request)
    {
        $validated = $request->validate([
            'account_id' => 'required|exists:chart_accounts,id',
            'related_account_id' => 'nullable|different:account_id|exists:chart_accounts,id',
            'bank_account_id' => [
                'nullable',
                'exists:bank_accounts,id',
                Rule::requiredIf(fn () => in_array($request->input('payment_method'), $this->bankPaymentMethods(), true)
                    && BankAccount::query()->where('is_active', true)->exists()),
            ],
            'transaction_date' => 'required|date',
            'direction' => 'required|in:in,out',
            'payment_method' => 'required|in:cash,credit,cheque,bank_deposit,bank_transfer,card,mobile_payment',
            'amount' => 'required|numeric|min:0.01',
            'cheque_number' => 'nullable|required_if:payment_method,cheque|string|max:100',
            'reference_no' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($validated, $request) {
            if (! in_array($validated['payment_method'], $this->bankPaymentMethods(), true)) {
                $validated['bank_account_id'] = null;
            }

            $transaction = AccountTransaction::create($validated + [
                'user_id' => $request->user()?->id,
                'source_type' => 'manual',
            ]);

            $this->applyAccountBalance((int) $validated['account_id'], $validated['direction'], (float) $validated['amount']);

            if (! empty($validated['related_account_id'])) {
                $opposite = $validated['direction'] === 'in' ? 'out' : 'in';
                $this->applyAccountBalance((int) $validated['related_account_id'], $opposite, (float) $validated['amount']);
                $transaction->relatedAccount()->associate($validated['related_account_id']);
                $transaction->save();
            }
        });

        return back()->with('success', 'Transaction recorded.');
    }

    public function storeBank(Request $request)
    {
        $validated = $request->validate([
            'bank_name' => 'required|string|max:255',
            'account_name' => 'required|string|max:255',
            'account_number' => 'nullable|string|max:100',
            'opening_balance' => 'nullable|numeric',
        ]);

        DB::transaction(function () use ($validated) {
            $openingBalance = (float) ($validated['opening_balance'] ?? 0);
            $account = ChartAccount::firstOrCreate(
                ['code' => '1200'],
                [
                    'name' => 'Bank',
                    'type' => 'asset',
                    'subtype' => 'bank',
                    'opening_balance' => 0,
                    'current_balance' => 0,
                    'is_system' => true,
                    'is_active' => true,
                ]
            );
            $account->increment('current_balance', $openingBalance);

            BankAccount::create($validated + [
                'chart_account_id' => $account->id,
                'opening_balance' => $openingBalance,
                'statement_balance' => $openingBalance,
            ]);
        });

        return back()->with('success', 'Bank account added.');
    }

    public function reconcile(Request $request, BankAccount $bankAccount)
    {
        $validated = $request->validate([
            'bank_account_id' => 'nullable|integer|exists:bank_accounts,id',
            'statement_month' => 'required|date_format:Y-m',
            'statement_balance' => 'required|numeric',
            'transaction_ids' => 'nullable|array',
            'transaction_ids.*' => 'integer|exists:account_transactions,id',
            'notes' => 'nullable|string',
        ]);

        DB::transaction(function () use ($validated, $bankAccount, $request) {
            if (! empty($validated['bank_account_id']) && (int) $validated['bank_account_id'] !== (int) $bankAccount->id) {
                $bankAccount = BankAccount::findOrFail($validated['bank_account_id']);
            }

            $statementDate = Carbon::createFromFormat('Y-m', $validated['statement_month'])->endOfMonth();
            $systemBalance = $this->bankSystemBalanceAt($bankAccount, $statementDate);
            $statementBalance = (float) $validated['statement_balance'];
            $difference = round($statementBalance - $systemBalance, 2);

            BankReconciliation::create([
                'bank_account_id' => $bankAccount->id,
                'user_id' => $request->user()?->id,
                'statement_date' => $statementDate->toDateString(),
                'statement_balance' => $statementBalance,
                'system_balance' => $systemBalance,
                'difference' => $difference,
                'status' => abs($difference) < 0.01 ? 'tallied' : 'difference',
                'notes' => $validated['notes'] ?? null,
            ]);

            $bankAccount->update(['statement_balance' => $statementBalance]);

            if (! empty($validated['transaction_ids'])) {
                AccountTransaction::whereIn('id', $validated['transaction_ids'])
                    ->where('account_id', $bankAccount->chart_account_id)
                    ->update(['is_reconciled' => true, 'reconciled_at' => now()]);
            }
        });

        return back()->with('success', 'Bank reconciliation saved.');
    }

    public function bankSystemBalance(Request $request, BankAccount $bankAccount)
    {
        $validated = $request->validate([
            'month' => 'required|date_format:Y-m',
        ]);

        $statementDate = Carbon::createFromFormat('Y-m', $validated['month'])->endOfMonth();
        $systemBalance = $this->bankSystemBalanceAt($bankAccount, $statementDate);

        return response()->json([
            'bank_account_id' => $bankAccount->id,
            'statement_month' => $validated['month'],
            'statement_date' => $statementDate->toDateString(),
            'system_balance' => $systemBalance,
        ]);
    }

    private function bankSystemBalanceAt(BankAccount $bankAccount, Carbon $statementDate): float
    {
        $movementQuery = AccountTransaction::query()
            ->whereDate('transaction_date', '<=', $statementDate->toDateString())
            ->where('bank_account_id', $bankAccount->id);

        if (! $movementQuery->exists()) {
            $movementQuery = AccountTransaction::query()
                ->whereDate('transaction_date', '<=', $statementDate->toDateString())
                ->where('account_id', $bankAccount->chart_account_id)
                ->whereNull('bank_account_id');
        }

        $movementBalance = $movementQuery
            ->selectRaw("SUM(CASE WHEN direction = 'in' THEN amount ELSE -amount END) as balance")
            ->value('balance');

        return round((float) $bankAccount->opening_balance + (float) $movementBalance, 2);
    }

    private function bankPaymentMethods(): array
    {
        return ['bank_deposit', 'bank_transfer', 'card', 'mobile_payment'];
    }

    public function storePettyFund(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'opening_balance' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($validated) {
            $account = ChartAccount::firstOrCreate(
                ['code' => '1100'],
                [
                    'name' => 'Cash',
                    'type' => 'asset',
                    'subtype' => 'cash',
                    'opening_balance' => 0,
                    'current_balance' => 0,
                    'is_system' => true,
                    'is_active' => true,
                ]
            );
            $account->increment('current_balance', (float) $validated['opening_balance']);

            PettyCashFund::create([
                'chart_account_id' => $account->id,
                'name' => $validated['name'],
                'opening_balance' => $validated['opening_balance'],
                'current_balance' => $validated['opening_balance'],
            ]);
        });

        return back()->with('success', 'Petty cash fund created.');
    }

    public function storePettyExpense(Request $request)
    {
        $validated = $request->validate([
            'petty_cash_fund_id' => 'required|exists:petty_cash_funds,id',
            'expense_category_id' => 'required|exists:expense_categories,id',
            'expense_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'voucher_no' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($validated, $request) {
            $fund = PettyCashFund::lockForUpdate()->findOrFail($validated['petty_cash_fund_id']);
            if ((float) $fund->current_balance < (float) $validated['amount']) {
                abort(422, 'Petty cash balance is not enough for this expense.');
            }

            PettyCashExpense::create($validated + ['user_id' => $request->user()?->id]);
            $fund->decrement('current_balance', (float) $validated['amount']);
            $this->applyAccountBalance((int) $fund->chart_account_id, 'out', (float) $validated['amount']);

            $expenseAccount = ChartAccount::firstOrCreate(
                ['code' => '5000'],
                [
                    'name' => 'Expense',
                    'type' => 'expense',
                    'subtype' => 'main',
                    'opening_balance' => 0,
                    'current_balance' => 0,
                    'is_system' => true,
                    'is_active' => true,
                ]
            );
            $this->applyAccountBalance((int) $expenseAccount->id, 'in', (float) $validated['amount']);
        });

        return back()->with('success', 'Petty cash expense recorded.');
    }

    public function storeOwnerEquity(Request $request)
    {
        $validated = $this->validateOwnerEquity($request);

        DB::transaction(function () use ($validated, $request) {
            $accounts = $this->ownerEquityAccounts($validated['type'], $validated['payment_account']);
            $movement = OwnerEquityMovement::create($validated + [
                'asset_account_id' => $accounts['asset']->id,
                'equity_account_id' => $accounts['equity']->id,
                'user_id' => $request->user()?->id,
            ]);

            $this->applyOwnerEquityMovement($movement);
        });

        return back()->with('success', 'Owner capital/drawing saved.');
    }

    public function updateOwnerEquity(Request $request, OwnerEquityMovement $ownerEquity)
    {
        $validated = $this->validateOwnerEquity($request);

        DB::transaction(function () use ($validated, $request, $ownerEquity) {
            $this->reverseOwnerEquityMovement($ownerEquity);
            $accounts = $this->ownerEquityAccounts($validated['type'], $validated['payment_account']);
            $ownerEquity->update($validated + [
                'asset_account_id' => $accounts['asset']->id,
                'equity_account_id' => $accounts['equity']->id,
                'asset_transaction_id' => null,
                'equity_transaction_id' => null,
                'user_id' => $request->user()?->id,
            ]);

            $this->applyOwnerEquityMovement($ownerEquity->fresh());
        });

        return back()->with('success', 'Owner capital/drawing updated.');
    }

    public function destroyOwnerEquity(OwnerEquityMovement $ownerEquity)
    {
        DB::transaction(function () use ($ownerEquity) {
            $this->reverseOwnerEquityMovement($ownerEquity);
            $ownerEquity->delete();
        });

        return back()->with('success', 'Owner capital/drawing deleted.');
    }

    private function validateOwnerEquity(Request $request): array
    {
        return $request->validate([
            'type' => 'required|in:investment,withdrawal',
            'owner_name' => 'nullable|string|max:255',
            'movement_date' => 'required|date',
            'payment_account' => 'required|in:cash,bank',
            'amount' => 'required|numeric|min:0.01',
            'reference_no' => 'nullable|string|max:100',
            'note' => 'nullable|string|max:1000',
        ]);
    }

    private function ownerEquityAccounts(string $type, string $paymentAccount): array
    {
        $asset = ChartAccount::firstOrCreate(
            ['code' => $paymentAccount === 'cash' ? '1100' : '1200'],
            [
                'name' => $paymentAccount === 'cash' ? 'Cash' : 'Bank',
                'type' => 'asset',
                'subtype' => $paymentAccount,
                'opening_balance' => 0,
                'current_balance' => 0,
                'is_system' => true,
                'is_active' => true,
            ]
        );

        $equity = ChartAccount::firstOrCreate(
            ['code' => $type === 'investment' ? '3100' : '3200'],
            [
                'name' => $type === 'investment' ? 'Owner Capital' : 'Owner Drawings',
                'type' => 'equity',
                'subtype' => $type === 'investment' ? 'owner_capital' : 'owner_drawings',
                'opening_balance' => 0,
                'current_balance' => 0,
                'is_system' => true,
                'is_active' => true,
            ]
        );

        return ['asset' => $asset, 'equity' => $equity];
    }

    private function applyOwnerEquityMovement(OwnerEquityMovement $movement): void
    {
        $amount = (float) $movement->amount;
        $assetDirection = $movement->type === 'investment' ? 'in' : 'out';
        $equityDirection = $movement->type === 'investment' ? 'in' : 'out';
        $assetMethod = $movement->payment_account === 'cash'
            ? 'cash'
            : ($movement->type === 'investment' ? 'bank_deposit' : 'bank_transfer');
        $label = $movement->type === 'investment' ? 'Owner investment' : 'Owner withdrawal';

        $assetTransaction = AccountTransaction::create([
            'account_id' => $movement->asset_account_id,
            'related_account_id' => $movement->equity_account_id,
            'user_id' => $movement->user_id,
            'transaction_date' => $movement->movement_date,
            'direction' => $assetDirection,
            'payment_method' => $assetMethod,
            'amount' => $amount,
            'reference_no' => $movement->reference_no,
            'source_type' => 'owner_equity_movement',
            'source_id' => $movement->id,
            'description' => trim($label.($movement->owner_name ? ' - '.$movement->owner_name : '').($movement->note ? ' - '.$movement->note : '')),
        ]);

        $equityTransaction = AccountTransaction::create([
            'account_id' => $movement->equity_account_id,
            'related_account_id' => $movement->asset_account_id,
            'user_id' => $movement->user_id,
            'transaction_date' => $movement->movement_date,
            'direction' => $equityDirection,
            'payment_method' => 'credit',
            'amount' => $amount,
            'reference_no' => $movement->reference_no,
            'source_type' => 'owner_equity_movement',
            'source_id' => $movement->id,
            'description' => trim($label.' equity effect'.($movement->owner_name ? ' - '.$movement->owner_name : '')),
        ]);

        $this->applyAccountBalance((int) $movement->asset_account_id, $assetDirection, $amount);
        $this->applyAccountBalance((int) $movement->equity_account_id, $equityDirection, $amount);

        $movement->update([
            'asset_transaction_id' => $assetTransaction->id,
            'equity_transaction_id' => $equityTransaction->id,
        ]);
    }

    private function reverseOwnerEquityMovement(OwnerEquityMovement $movement): void
    {
        $amount = (float) $movement->amount;
        $assetReverse = $movement->type === 'investment' ? 'out' : 'in';
        $equityReverse = $movement->type === 'investment' ? 'out' : 'in';

        $this->applyAccountBalance((int) $movement->asset_account_id, $assetReverse, $amount);
        $this->applyAccountBalance((int) $movement->equity_account_id, $equityReverse, $amount);

        AccountTransaction::where('source_type', 'owner_equity_movement')
            ->where('source_id', $movement->id)
            ->delete();
    }

    private function applyAccountBalance(int $accountId, string $direction, float $amount): void
    {
        $account = ChartAccount::lockForUpdate()->findOrFail($accountId);
        $signed = $direction === 'in' ? $amount : -$amount;
        $account->current_balance = round((float) $account->current_balance + $signed, 2);
        $account->save();
    }
}
