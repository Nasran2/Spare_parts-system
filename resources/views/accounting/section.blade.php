@extends('layouts.app')

@php
    $titles = [
        'overview' => 'Accounting Overview',
        'accounts' => 'Chart of Accounts',
        'transactions' => 'Cash / Credit / Cheque / Bank',
        'banks' => 'Bank Reconciliation',
        'petty-cash' => 'Petty Cash',
        'ledger' => 'Ledger',
        't-accounts' => 'T Accounts',
        'trial-balance' => 'Trial Balance',
        'balance-sheet' => 'Balance Sheet',
        'cash-book' => 'Cash Book',
        'bank-book' => 'Bank Book',
        'owner-equity' => 'Owner Capital / Drawings',
    ];
@endphp

@section('title', $titles[$section] ?? 'Accounting')
@section('page-title', $titles[$section] ?? 'Accounting')

@section('content')
<div class="space-y-6">
    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">{{ $errors->first() }}</div>
    @endif

    @if($section === 'overview')
        @php
            $today = \Carbon\Carbon::today()->format('Y-m-d');
            $yesterday = \Carbon\Carbon::yesterday()->format('Y-m-d');
            $end = request('to') ?: $today;
            $start = request('from') ?: $today;
            $endC = \Carbon\Carbon::parse($end);
            $startC = \Carbon\Carbon::parse($start);
            $isToday = ($start === $today && $end === $today);
            $isYesterday = ($start === $yesterday && $end === $yesterday);
            $isThisWeek = ($endC->isSameDay(\Carbon\Carbon::today()) && $startC->isSameDay($endC->copy()->startOfWeek()));
            $isThisMonth = ($endC->isSameDay(\Carbon\Carbon::today()) && $startC->isSameDay($endC->copy()->startOfMonth()));
            $periodLabel = ($start === $end) ? $start : $start . ' to ' . $end;
        @endphp

        <div class="bg-white rounded-xl shadow-sm p-4">
            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                            <i class="fas fa-calendar-alt"></i>
                        </span>
                        <div>
                            <div class="text-sm font-semibold text-gray-500">Filter Period</div>
                            <div class="text-base font-bold text-gray-800">{{ $periodLabel }}</div>
                        </div>
                    </div>
                    <p id="accounting_custom_error" class="hidden text-sm font-semibold text-red-600">Select start and end dates.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" data-accounting-range="today" class="px-4 py-2 {{ $isToday ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }} rounded-lg text-sm hover:bg-blue-700 hover:text-white transition">
                        <i class="fas fa-calendar-day mr-1"></i> Today
                    </button>
                    <button type="button" data-accounting-range="yesterday" class="px-4 py-2 {{ $isYesterday ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }} rounded-lg text-sm hover:bg-blue-700 hover:text-white transition">
                        <i class="fas fa-history mr-1"></i> Yesterday
                    </button>
                    <button type="button" data-accounting-range="this_week" class="px-4 py-2 {{ $isThisWeek ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }} rounded-lg text-sm hover:bg-blue-700 hover:text-white transition">
                        <i class="fas fa-calendar-week mr-1"></i> This Week
                    </button>
                    <button type="button" data-accounting-range="this_month" class="px-4 py-2 {{ $isThisMonth ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }} rounded-lg text-sm hover:bg-blue-700 hover:text-white transition">
                        <i class="fas fa-calendar-alt mr-1"></i> This Month
                    </button>
                    <div class="flex flex-wrap items-center gap-2">
                        <input type="date" id="accounting_custom_start" value="{{ request('from') }}" class="px-3 py-2 border rounded text-sm">
                        <input type="date" id="accounting_custom_end" value="{{ request('to') }}" class="px-3 py-2 border rounded text-sm">
                        <button type="button" data-accounting-range="custom" class="px-4 py-2 {{ !($isToday || $isYesterday || $isThisWeek || $isThisMonth) ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }} rounded-lg text-sm hover:bg-blue-700 hover:text-white transition">
                            <i class="fas fa-calendar mr-1"></i> Custom Range
                        </button>
                        @if($overviewPeriodActive ?? false)
                            <a href="{{ route('accounting.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm hover:bg-gray-200 transition">Reset</a>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 {{ ($canViewChequePayments ?? false) ? '2xl:grid-cols-5' : '2xl:grid-cols-4' }} gap-4 md:gap-6">
            <div class="stat-card bg-white rounded-xl shadow-md p-6 border-l-4 border-blue-500">
                <div class="flex items-center justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-sm text-gray-600 mb-1">Assets</p>
                        <h3 class="font-bold text-gray-800 leading-tight whitespace-nowrap" style="font-size: clamp(1.15rem, 1.25vw, 1.45rem);">{{ number_format($totals['assets'], 2) }}</h3>
                        <p class="text-xs text-blue-600 mt-2">Current balance</p>
                    </div>
                    <div class="w-12 h-12 md:w-14 md:h-14 shrink-0 gradient-blue rounded-full flex items-center justify-center shadow-lg">
                        <i class="fas fa-vault text-white text-xl"></i>
                    </div>
                </div>
            </div>
            <div class="stat-card bg-white rounded-xl shadow-md p-6 border-l-4 border-red-500">
                <div class="flex items-center justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-sm text-gray-600 mb-1">Liabilities</p>
                        <h3 class="font-bold text-gray-800 leading-tight whitespace-nowrap" style="font-size: clamp(1.15rem, 1.25vw, 1.45rem);">{{ number_format($totals['liabilities'], 2) }}</h3>
                        <p class="text-xs text-red-600 mt-2">Current balance</p>
                    </div>
                    <div class="w-12 h-12 md:w-14 md:h-14 shrink-0 gradient-red rounded-full flex items-center justify-center shadow-lg">
                        <i class="fas fa-file-invoice-dollar text-white text-xl"></i>
                    </div>
                </div>
            </div>
            <div class="stat-card bg-white rounded-xl shadow-md p-6 border-l-4 border-green-500">
                <div class="flex items-center justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-sm text-gray-600 mb-1">Income</p>
                        <h3 class="font-bold text-gray-800 leading-tight whitespace-nowrap" style="font-size: clamp(1.15rem, 1.25vw, 1.45rem);">{{ number_format($totals['income'], 2) }}</h3>
                        <p class="text-xs text-green-600 mt-2">Selected period</p>
                    </div>
                    <div class="w-12 h-12 md:w-14 md:h-14 shrink-0 gradient-green rounded-full flex items-center justify-center shadow-lg">
                        <i class="fas fa-arrow-trend-up text-white text-xl"></i>
                    </div>
                </div>
            </div>
            <div class="stat-card bg-white rounded-xl shadow-md p-6 border-l-4 border-orange-500">
                <div class="flex items-center justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-sm text-gray-600 mb-1">Expenses</p>
                        <h3 class="font-bold text-gray-800 leading-tight whitespace-nowrap" style="font-size: clamp(1.15rem, 1.25vw, 1.45rem);">{{ number_format($totals['expenses'], 2) }}</h3>
                        <p class="text-xs text-orange-600 mt-2">Selected period</p>
                    </div>
                    <div class="w-12 h-12 md:w-14 md:h-14 shrink-0 gradient-orange rounded-full flex items-center justify-center shadow-lg">
                        <i class="fas fa-wallet text-white text-xl"></i>
                    </div>
                </div>
            </div>
            @if($canViewChequePayments ?? false)
                <div class="stat-card bg-white rounded-xl shadow-md p-6 border-l-4 border-indigo-500">
                    <div class="flex items-center justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-sm text-gray-600 mb-1">Pending Cheques</p>
                            <h3 class="font-bold text-gray-800 leading-tight whitespace-nowrap" style="font-size: clamp(1.15rem, 1.25vw, 1.45rem);">{{ number_format((float) ($chequeSummary['pending_amount'] ?? 0), 2) }}</h3>
                            <p class="text-xs text-indigo-600 mt-2">{{ (int) ($chequeSummary['pending_count'] ?? 0) }} cheque{{ (int) ($chequeSummary['pending_count'] ?? 0) === 1 ? '' : 's' }} on hold</p>
                        </div>
                        <div class="w-12 h-12 md:w-14 md:h-14 shrink-0 gradient-indigo rounded-full flex items-center justify-center shadow-lg">
                            <i class="fas fa-money-check-alt text-white text-xl"></i>
                        </div>
                    </div>
                </div>
            @endif
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-8 gap-4">
            <a href="{{ route('accounting.balance-sheet') }}" class="bg-white rounded-lg shadow p-5 hover:bg-blue-50"><i class="fas fa-file-invoice-dollar text-blue-600 mb-3"></i><p class="font-semibold">Balance Sheet</p></a>
            <a href="{{ route('accounting.cash-book') }}" class="bg-white rounded-lg shadow p-5 hover:bg-green-50"><i class="fas fa-cash-register text-green-600 mb-3"></i><p class="font-semibold">Cash Book</p></a>
            <a href="{{ route('accounting.bank-book') }}" class="bg-white rounded-lg shadow p-5 hover:bg-indigo-50"><i class="fas fa-building-columns text-indigo-600 mb-3"></i><p class="font-semibold">Bank Book</p></a>
            <a href="{{ route('accounting.transactions') }}" class="bg-white rounded-lg shadow p-5 hover:bg-green-50"><i class="fas fa-money-bill-transfer text-green-600 mb-3"></i><p class="font-semibold">Transactions</p></a>
            <a href="{{ route('accounting.banks') }}" class="bg-white rounded-lg shadow p-5 hover:bg-indigo-50"><i class="fas fa-building-columns text-indigo-600 mb-3"></i><p class="font-semibold">Bank Reconcile</p></a>
            <a href="{{ route('accounting.petty-cash') }}" class="bg-white rounded-lg shadow p-5 hover:bg-orange-50"><i class="fas fa-wallet text-orange-600 mb-3"></i><p class="font-semibold">Petty Cash</p></a>
            <a href="{{ route('accounting.ledger') }}" class="bg-white rounded-lg shadow p-5 hover:bg-gray-50"><i class="fas fa-book text-gray-700 mb-3"></i><p class="font-semibold">Ledger</p></a>
            @if(auth()->user()?->hasPermission('accounting.t-accounts'))
                <a href="{{ route('accounting.t-accounts') }}" class="bg-white rounded-lg shadow p-5 hover:bg-blue-50"><i class="fas fa-table-columns text-blue-600 mb-3"></i><p class="font-semibold">T Accounts</p></a>
            @endif
            @if(auth()->user()?->hasPermission('accounting.trial-balance'))
                <a href="{{ route('accounting.trial-balance') }}" class="bg-white rounded-lg shadow p-5 hover:bg-green-50"><i class="fas fa-scale-balanced text-green-600 mb-3"></i><p class="font-semibold">Trial Balance</p></a>
            @endif
            @if(auth()->user()?->hasPermission('cheque_payments.view'))
                <a href="{{ route('cheque-payments.index') }}" class="bg-white rounded-lg shadow p-5 hover:bg-indigo-50"><i class="fas fa-money-check-alt text-indigo-600 mb-3"></i><p class="font-semibold">Cheque Management</p></a>
            @endif
            @if(auth()->user()?->hasPermission('accounting.owner-equity.view'))
                <a href="{{ route('accounting.owner-equity') }}" class="bg-white rounded-lg shadow p-5 hover:bg-purple-50"><i class="fas fa-user-tie text-purple-600 mb-3"></i><p class="font-semibold">Owner Capital / Drawings</p></a>
            @endif
        </div>

        <div class="grid grid-cols-1 {{ ($canViewChequePayments ?? false) ? 'xl:grid-cols-2' : '' }} gap-6">
            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="p-5 border-b flex items-center justify-between">
                    <h3 class="font-semibold text-gray-800"><i class="fas fa-clock-rotate-left text-blue-600 mr-2"></i>Recent Transactions</h3>
                    <a href="{{ route('accounting.transactions') }}" class="text-sm font-semibold text-blue-600 hover:underline">View All</a>
                </div>
                <div class="divide-y">
                    @forelse($transactions->take(5) as $transaction)
                        <div class="px-5 py-4 flex items-center justify-between gap-4">
                            <div class="min-w-0">
                                <div class="font-semibold text-gray-800 truncate">{{ $transaction->account->name ?? 'Account' }}</div>
                                <div class="text-xs text-gray-500">{{ $transaction->transaction_date?->format('M d, Y') }} · {{ str_replace('_', ' ', ucfirst($transaction->payment_method)) }}</div>
                            </div>
                            <div class="text-right font-bold {{ $transaction->direction === 'in' ? 'text-green-700' : 'text-red-700' }}">
                                {{ $transaction->direction === 'in' ? '+' : '-' }}{{ number_format((float) $transaction->amount, 2) }}
                            </div>
                        </div>
                    @empty
                        <div class="px-5 py-10 text-center text-gray-500">No transactions for this period.</div>
                    @endforelse
                </div>
            </div>

            @if($canViewChequePayments ?? false)
                <div class="bg-white rounded-xl shadow-md overflow-hidden">
                    <div class="p-5 border-b flex items-center justify-between">
                        <h3 class="font-semibold text-gray-800"><i class="fas fa-money-check-alt text-indigo-600 mr-2"></i>Cheque Management</h3>
                        <a href="{{ route('cheque-payments.index') }}" class="text-sm font-semibold text-blue-600 hover:underline">Open</a>
                    </div>
                    <div class="divide-y">
                        @forelse($pendingCheques as $cheque)
                            <div class="px-5 py-4 flex items-center justify-between gap-4">
                                <div class="min-w-0">
                                    <div class="font-semibold text-gray-800 truncate">{{ $cheque->customer?->name ?? $cheque->sale?->customer?->name ?? 'Walk-in Customer' }}</div>
                                    <div class="text-xs text-gray-500">No: {{ $cheque->cheque_number }} · {{ $cheque->cheque_date?->format('Y-m-d') }}</div>
                                </div>
                                <div class="text-right">
                                    <div class="font-bold text-indigo-700">{{ number_format((float) $cheque->amount, 2) }}</div>
                                    <div class="text-xs text-amber-600">Pending</div>
                                </div>
                            </div>
                        @empty
                            <div class="px-5 py-10 text-center text-gray-500">No pending cheques.</div>
                        @endforelse
                    </div>
                </div>
            @endif
        </div>
    @endif

    @if($section === 'accounts')
        <div class="bg-white rounded-lg shadow p-4">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-3">
                <input name="search" value="{{ request('search') }}" placeholder="Search code/account" class="border rounded-lg px-3 py-2">
                <select name="type" class="border rounded-lg px-3 py-2">
                    <option value="">All types</option>
                    @foreach(['asset','liability','equity','income','expense'] as $type)
                        <option value="{{ $type }}" {{ request('type') === $type ? 'selected' : '' }}>{{ ucfirst($type) }}</option>
                    @endforeach
                </select>
                <input name="subtype" value="{{ request('subtype') }}" placeholder="Subtype" class="border rounded-lg px-3 py-2">
                <button class="bg-gray-800 text-white rounded-lg px-4 py-2">Filter</button>
                <div class="flex gap-2">
                    <a href="{{ route('accounting.export', ['section' => 'accounts', 'format' => 'excel'] + request()->query()) }}" class="flex-1 text-center bg-emerald-600 text-white rounded-lg px-3 py-2"><i class="fas fa-file-excel mr-1"></i>Excel</a>
                    <a href="{{ route('accounting.export', ['section' => 'accounts', 'format' => 'pdf'] + request()->query()) }}" class="flex-1 text-center bg-blue-600 text-white rounded-lg px-3 py-2"><i class="fas fa-file-pdf mr-1"></i>PDF</a>
                </div>
            </form>
        </div>
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="p-5 border-b"><h3 class="font-semibold text-gray-800">QuickBooks Chart of Accounts</h3></div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-4 py-3 text-left">Code</th><th class="px-4 py-3 text-left">Account</th><th class="px-4 py-3 text-left">Type</th><th class="px-4 py-3 text-right">Opening</th><th class="px-4 py-3 text-right">Balance</th></tr></thead>
                    <tbody class="divide-y">
                        @foreach($accounts as $account)
                            <tr><td class="px-4 py-3 font-medium">{{ $account->code }}</td><td class="px-4 py-3">{{ $account->name }}</td><td class="px-4 py-3"><span class="px-2 py-1 bg-gray-100 rounded text-xs">{{ ucfirst($account->type) }} / {{ $account->subtype ?: 'General' }}</span></td><td class="px-4 py-3 text-right">{{ number_format($account->opening_balance, 2) }}</td><td class="px-4 py-3 text-right font-semibold">{{ number_format($account->current_balance, 2) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if($section === 'transactions')
        <div class="bg-white rounded-lg shadow p-4">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-3">
                <input type="date" name="from" value="{{ request('from') }}" class="border rounded-lg px-3 py-2">
                <input type="date" name="to" value="{{ request('to') }}" class="border rounded-lg px-3 py-2">
                <select name="account_id" class="border rounded-lg px-3 py-2"><option value="">All accounts</option>@foreach($accounts as $account)<option value="{{ $account->id }}" {{ (string) request('account_id') === (string) $account->id ? 'selected' : '' }}>{{ $account->name }}</option>@endforeach</select>
                <select name="payment_method" class="border rounded-lg px-3 py-2"><option value="">All methods</option>@foreach(['cash','credit','cheque','bank_deposit','bank_transfer','card','mobile_payment'] as $method)<option value="{{ $method }}" {{ request('payment_method') === $method ? 'selected' : '' }}>{{ str_replace('_', ' ', ucfirst($method)) }}</option>@endforeach</select>
                <input name="search" value="{{ request('search') }}" placeholder="Reference/cheque" class="border rounded-lg px-3 py-2">
                <div class="flex gap-2"><button class="flex-1 bg-gray-800 text-white rounded-lg px-3 py-2">Filter</button><a href="{{ route('accounting.export', ['section' => 'transactions', 'format' => 'excel'] + request()->query()) }}" class="flex-1 text-center bg-emerald-600 text-white rounded-lg px-3 py-2">Excel</a><a href="{{ route('accounting.export', ['section' => 'transactions', 'format' => 'pdf'] + request()->query()) }}" class="flex-1 text-center bg-blue-600 text-white rounded-lg px-3 py-2">PDF</a></div>
            </form>
        </div>
        <div class="bg-white rounded-lg shadow p-5">
            <h3 class="font-semibold text-gray-800 mb-4">Cash / Credit / Cheque / Bank Transaction</h3>
            <form method="POST" action="{{ route('accounting.transactions.store') }}" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @csrf
                <select name="account_id" class="border rounded-lg px-3 py-2" required><option value="">Main account</option>@foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->code }} - {{ $account->name }}</option>@endforeach</select>
                <select name="related_account_id" class="border rounded-lg px-3 py-2"><option value="">Related account</option>@foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->code }} - {{ $account->name }}</option>@endforeach</select>
                <input type="date" name="transaction_date" value="{{ date('Y-m-d') }}" class="border rounded-lg px-3 py-2" required>
                <select name="direction" class="border rounded-lg px-3 py-2" required><option value="in">Money In</option><option value="out">Money Out</option></select>
                <select name="payment_method" class="accounting-payment-method border rounded-lg px-3 py-2" required><option value="cash">Cash</option><option value="credit">Credit</option><option value="cheque">Cheque</option><option value="bank_deposit">Bank Deposit</option><option value="bank_transfer">Bank Transfer</option><option value="card">Card</option><option value="mobile_payment">Mobile Payment</option></select>
                <select name="bank_account_id" class="accounting-bank-select hidden border rounded-lg px-3 py-2">
                    <option value="">Select bank</option>
                    @foreach($banks as $bank)
                        <option value="{{ $bank->id }}">{{ $bank->bank_name }} - {{ $bank->account_name }}</option>
                    @endforeach
                </select>
                <input type="number" step="0.01" min="0.01" name="amount" placeholder="Amount" class="border rounded-lg px-3 py-2" required>
                <input name="cheque_number" placeholder="Cheque number" class="border rounded-lg px-3 py-2">
                <input name="reference_no" placeholder="Reference no" class="border rounded-lg px-3 py-2">
                <input name="description" placeholder="Description" class="md:col-span-2 border rounded-lg px-3 py-2">
                <button class="md:col-span-2 bg-green-600 text-white rounded-lg px-4 py-2">Record Transaction</button>
            </form>
        </div>
        <div class="bg-white rounded-lg shadow p-5">
            <h3 class="font-semibold text-gray-800 mb-4">Add Bank</h3>
            <form method="POST" action="{{ route('accounting.banks.store') }}" class="grid grid-cols-1 md:grid-cols-4 gap-3">
                @csrf
                <input name="bank_name" placeholder="Bank" class="border rounded-lg px-3 py-2" required>
                <input name="account_name" placeholder="Account name" class="border rounded-lg px-3 py-2" required>
                <input name="account_number" placeholder="Account no" class="border rounded-lg px-3 py-2">
                <input type="number" step="0.01" name="opening_balance" placeholder="Opening" class="border rounded-lg px-3 py-2">
                <button class="md:col-span-4 bg-blue-600 text-white rounded-lg px-4 py-2">Add Bank Account</button>
            </form>
        </div>
    @endif

    @if($section === 'banks')
        <div class="bg-white rounded-lg shadow p-4">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-3">
                <input type="date" name="from" value="{{ request('from') }}" class="border rounded-lg px-3 py-2">
                <input type="date" name="to" value="{{ request('to') }}" class="border rounded-lg px-3 py-2">
                <select name="bank_account_id" class="border rounded-lg px-3 py-2"><option value="">All banks</option>@foreach($banks as $bank)<option value="{{ $bank->id }}" {{ (string) request('bank_account_id') === (string) $bank->id ? 'selected' : '' }}>{{ $bank->bank_name }} - {{ $bank->account_name }}</option>@endforeach</select>
                <select name="status" class="border rounded-lg px-3 py-2"><option value="">All status</option><option value="tallied" {{ request('status') === 'tallied' ? 'selected' : '' }}>Tallied</option><option value="difference" {{ request('status') === 'difference' ? 'selected' : '' }}>Difference</option><option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Draft</option></select>
                <button class="bg-gray-800 text-white rounded-lg px-3 py-2">Filter</button>
                <div class="flex gap-2"><a href="{{ route('accounting.export', ['section' => 'banks', 'format' => 'excel'] + request()->query()) }}" class="flex-1 text-center bg-emerald-600 text-white rounded-lg px-3 py-2">Excel</a><a href="{{ route('accounting.export', ['section' => 'banks', 'format' => 'pdf'] + request()->query()) }}" class="flex-1 text-center bg-blue-600 text-white rounded-lg px-3 py-2">PDF</a></div>
            </form>
        </div>
        <div class="bg-white rounded-lg shadow p-5">
            <h3 class="font-semibold text-gray-800 mb-4">Bank Accounts & Reconciliation</h3>
            <form method="POST" action="{{ route('accounting.banks.store') }}" class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-5">@csrf<input name="bank_name" placeholder="Bank" class="border rounded-lg px-3 py-2" required><input name="account_name" placeholder="Account name" class="border rounded-lg px-3 py-2" required><input name="account_number" placeholder="Account no" class="border rounded-lg px-3 py-2"><input type="number" step="0.01" name="opening_balance" placeholder="Opening" class="border rounded-lg px-3 py-2"><button class="md:col-span-4 bg-blue-600 text-white rounded-lg px-4 py-2">Add Bank Account</button></form>
            <div class="border-t pt-5">
                <h4 class="font-semibold text-gray-800 mb-3">Add Monthly Bank Statement</h4>
                <form method="POST" action="{{ $banks->first() ? route('accounting.banks.reconcile', $banks->first()) : '#' }}" class="bank-reconcile-form rounded-lg border border-blue-100 bg-blue-50/40 p-4" data-action-template="{{ url('accounting/banks') }}/__BANK_ID__/reconcile" data-system-balance-template="{{ url('accounting/banks') }}/__BANK_ID__/system-balance">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-6 gap-3">
                        <select name="bank_account_id" class="bank-account-select border rounded-lg px-3 py-2" required>
                            <option value="">Select bank</option>
                            @foreach($banks as $bank)
                                <option value="{{ $bank->id }}" data-current-balance="{{ number_format((float) $bank->chartAccount->current_balance, 2, '.', '') }}" {{ $loop->first ? 'selected' : '' }}>{{ $bank->bank_name }} - {{ $bank->account_name }}</option>
                            @endforeach
                        </select>
                        <input type="month" name="statement_month" value="{{ date('Y-m') }}" class="bank-statement-month border rounded-lg px-3 py-2" required>
                        <input type="number" step="0.01" name="statement_balance" placeholder="Statement balance" class="bank-statement-balance border rounded-lg px-3 py-2" required>
                        <input type="text" class="bank-system-balance border rounded-lg px-3 py-2 bg-white text-gray-700" value="{{ $banks->first() ? number_format((float) $banks->first()->chartAccount->current_balance, 2, '.', '') : '0.00' }}" readonly>
                        <input type="text" class="bank-difference border rounded-lg px-3 py-2 bg-white text-gray-700" value="0.00" readonly>
                        <input name="notes" placeholder="Notes" class="border rounded-lg px-3 py-2">
                    </div>
                    <button class="mt-3 bg-gray-800 text-white rounded-lg px-4 py-2" {{ $banks->isEmpty() ? 'disabled' : '' }}>Add Monthly Statement</button>
                    @if($banks->isEmpty())
                        <p class="mt-2 text-sm text-amber-700">Add a bank account first, then add the monthly statement.</p>
                    @endif
                </form>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow overflow-hidden"><div class="p-5 border-b"><h3 class="font-semibold text-gray-800">Reconciliation History</h3></div><div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-4 py-3 text-left">Date</th><th class="px-4 py-3 text-left">Bank</th><th class="px-4 py-3 text-right">Statement</th><th class="px-4 py-3 text-right">System</th><th class="px-4 py-3 text-right">Difference</th><th class="px-4 py-3 text-left">Status</th></tr></thead><tbody class="divide-y">@forelse($reconciliations as $rec)<tr><td class="px-4 py-3">{{ $rec->statement_date->format('M d, Y') }}</td><td class="px-4 py-3">{{ $rec->bankAccount->bank_name }}</td><td class="px-4 py-3 text-right">{{ number_format($rec->statement_balance, 2) }}</td><td class="px-4 py-3 text-right">{{ number_format($rec->system_balance, 2) }}</td><td class="px-4 py-3 text-right">{{ number_format($rec->difference, 2) }}</td><td class="px-4 py-3">{{ ucfirst($rec->status) }}</td></tr>@empty<tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">No records found.</td></tr>@endforelse</tbody></table></div></div>
    @endif

    @if($section === 'petty-cash')
        <div class="bg-white rounded-lg shadow p-4">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-3">
                <input type="date" name="from" value="{{ request('from') }}" class="border rounded-lg px-3 py-2">
                <input type="date" name="to" value="{{ request('to') }}" class="border rounded-lg px-3 py-2">
                <select name="petty_cash_fund_id" class="border rounded-lg px-3 py-2"><option value="">All funds</option>@foreach($pettyFunds as $fund)<option value="{{ $fund->id }}" {{ (string) request('petty_cash_fund_id') === (string) $fund->id ? 'selected' : '' }}>{{ $fund->name }}</option>@endforeach</select>
                <select name="expense_category_id" class="border rounded-lg px-3 py-2"><option value="">All categories</option>@foreach($categories as $category)<option value="{{ $category->id }}" {{ (string) request('expense_category_id') === (string) $category->id ? 'selected' : '' }}>{{ $category->name }}</option>@endforeach</select>
                <input name="search" value="{{ request('search') }}" placeholder="Voucher/description" class="border rounded-lg px-3 py-2">
                <div class="flex gap-2"><button class="flex-1 bg-gray-800 text-white rounded-lg px-3 py-2">Filter</button><a href="{{ route('accounting.export', ['section' => 'petty-cash', 'format' => 'excel'] + request()->query()) }}" class="flex-1 text-center bg-emerald-600 text-white rounded-lg px-3 py-2">Excel</a><a href="{{ route('accounting.export', ['section' => 'petty-cash', 'format' => 'pdf'] + request()->query()) }}" class="flex-1 text-center bg-blue-600 text-white rounded-lg px-3 py-2">PDF</a></div>
            </form>
        </div>
        <div class="bg-white rounded-lg shadow p-5">
            <h3 class="font-semibold text-gray-800 mb-4">Petty Cash</h3>
            <form method="POST" action="{{ route('accounting.petty-cash.store') }}" class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-5">@csrf<input name="name" placeholder="Fund name" class="border rounded-lg px-3 py-2" required><input type="number" step="0.01" name="opening_balance" placeholder="Opening balance" class="border rounded-lg px-3 py-2" required><button class="bg-blue-600 text-white rounded-lg px-4 py-2">Create Fund</button></form>
            <form method="POST" action="{{ route('accounting.petty-cash.expenses.store') }}" class="grid grid-cols-1 md:grid-cols-2 gap-3">@csrf<select name="petty_cash_fund_id" class="border rounded-lg px-3 py-2" required><option value="">Petty cash fund</option>@foreach($pettyFunds as $fund)<option value="{{ $fund->id }}">{{ $fund->name }} - {{ number_format($fund->current_balance, 2) }}</option>@endforeach</select><select name="expense_category_id" class="border rounded-lg px-3 py-2" required><option value="">Expense category</option>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select><input type="date" name="expense_date" value="{{ date('Y-m-d') }}" class="border rounded-lg px-3 py-2" required><input type="number" step="0.01" name="amount" placeholder="Amount" class="border rounded-lg px-3 py-2" required><input name="voucher_no" placeholder="Voucher no" class="border rounded-lg px-3 py-2"><input name="description" placeholder="Description" class="border rounded-lg px-3 py-2"><button class="md:col-span-2 bg-orange-600 text-white rounded-lg px-4 py-2">Record Petty Cash Expense</button></form>
        </div>
        <div class="bg-white rounded-lg shadow overflow-hidden"><div class="p-5 border-b"><h3 class="font-semibold text-gray-800">Petty Cash Expense History</h3></div><div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-4 py-3 text-left">Date</th><th class="px-4 py-3 text-left">Fund</th><th class="px-4 py-3 text-left">Category</th><th class="px-4 py-3 text-left">Voucher</th><th class="px-4 py-3 text-left">Description</th><th class="px-4 py-3 text-right">Amount</th></tr></thead><tbody class="divide-y">@forelse($pettyExpenses as $expense)<tr><td class="px-4 py-3">{{ $expense->expense_date->format('M d, Y') }}</td><td class="px-4 py-3">{{ $expense->fund->name ?? '' }}</td><td class="px-4 py-3">{{ $expense->category->name ?? '' }}</td><td class="px-4 py-3">{{ $expense->voucher_no }}</td><td class="px-4 py-3">{{ $expense->description }}</td><td class="px-4 py-3 text-right">{{ number_format($expense->amount, 2) }}</td></tr>@empty<tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">No records found.</td></tr>@endforelse</tbody></table></div></div>
    @endif

    @if($section === 'ledger')
        <div class="bg-white rounded-lg shadow p-4">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-3">
                <input type="date" name="from" value="{{ request('from') }}" class="border rounded-lg px-3 py-2">
                <input type="date" name="to" value="{{ request('to') }}" class="border rounded-lg px-3 py-2">
                <select name="account_id" class="border rounded-lg px-3 py-2"><option value="">All accounts</option>@foreach($accounts as $account)<option value="{{ $account->id }}" {{ (string) request('account_id') === (string) $account->id ? 'selected' : '' }}>{{ $account->name }}</option>@endforeach</select>
                <select name="direction" class="border rounded-lg px-3 py-2"><option value="">In/Out</option><option value="in" {{ request('direction') === 'in' ? 'selected' : '' }}>Money In</option><option value="out" {{ request('direction') === 'out' ? 'selected' : '' }}>Money Out</option></select>
                <input name="search" value="{{ request('search') }}" placeholder="Reference/cheque" class="border rounded-lg px-3 py-2">
                <div class="flex gap-2"><button class="flex-1 bg-gray-800 text-white rounded-lg px-3 py-2">Filter</button><a href="{{ route('accounting.export', ['section' => 'ledger', 'format' => 'excel'] + request()->query()) }}" class="flex-1 text-center bg-emerald-600 text-white rounded-lg px-3 py-2">Excel</a><a href="{{ route('accounting.export', ['section' => 'ledger', 'format' => 'pdf'] + request()->query()) }}" class="flex-1 text-center bg-blue-600 text-white rounded-lg px-3 py-2">PDF</a></div>
            </form>
        </div>
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="p-5 border-b"><h3 class="font-semibold text-gray-800">Ledger</h3></div>
            <div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-4 py-3 text-left">Date</th><th class="px-4 py-3 text-left">Account</th><th class="px-4 py-3 text-left">Method</th><th class="px-4 py-3 text-left">Reference</th><th class="px-4 py-3 text-right">Amount</th></tr></thead><tbody class="divide-y">@foreach($transactions as $transaction)<tr><td class="px-4 py-3">{{ $transaction->transaction_date->format('M d, Y') }}</td><td class="px-4 py-3">{{ $transaction->account->name }}</td><td class="px-4 py-3">{{ str_replace('_', ' ', ucfirst($transaction->payment_method)) }} {{ $transaction->cheque_number ? '(' . $transaction->cheque_number . ')' : '' }}</td><td class="px-4 py-3">{{ $transaction->reference_no }}</td><td class="px-4 py-3 text-right {{ $transaction->direction === 'in' ? 'text-green-700' : 'text-red-700' }}">{{ $transaction->direction === 'in' ? '+' : '-' }}{{ number_format($transaction->amount, 2) }}</td></tr>@endforeach</tbody></table></div>
        </div>
    @endif

    @if(in_array($section, ['t-accounts', 'trial-balance'], true))
        <div class="bg-white rounded-lg shadow p-4">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-7 gap-3">
                <input type="date" name="from" value="{{ request('from') }}" class="border rounded-lg px-3 py-2">
                <input type="date" name="to" value="{{ request('to') }}" class="border rounded-lg px-3 py-2">
                <select name="account_id" class="border rounded-lg px-3 py-2">
                    <option value="">All accounts</option>
                    @foreach(($allAccounts ?? $accounts) as $account)
                        <option value="{{ $account->id }}" {{ (string) request('account_id') === (string) $account->id ? 'selected' : '' }}>{{ $account->code }} - {{ $account->name }}</option>
                    @endforeach
                </select>
                <select name="type" class="border rounded-lg px-3 py-2">
                    <option value="">All account types</option>
                    @foreach(['asset','liability','equity','income','expense'] as $type)
                        <option value="{{ $type }}" {{ request('type') === $type ? 'selected' : '' }}>{{ ucfirst($type) }}</option>
                    @endforeach
                </select>
                <input name="search" value="{{ request('search') }}" placeholder="Search account/code" class="border rounded-lg px-3 py-2">
                <button class="bg-gray-800 text-white rounded-lg px-3 py-2">Filter</button>
                <a href="{{ $section === 't-accounts' ? route('accounting.t-accounts') : route('accounting.trial-balance') }}" class="text-center bg-gray-100 text-gray-700 rounded-lg px-3 py-2">Reset</a>
            </form>
        </div>
    @endif

    @if($section === 't-accounts')
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            @forelse($tAccounts as $row)
                <div class="bg-white rounded-xl shadow-md p-5">
                    <div class="mb-3 text-center">
                        <h3 class="text-lg font-bold text-gray-800">{{ $row->account->name }}</h3>
                        <p class="text-xs text-gray-500">{{ $row->account->code }} · {{ ucfirst($row->account->type) }}</p>
                    </div>
                    <div class="grid grid-cols-2 border-t-2 border-gray-800">
                        <div class="border-r border-gray-800 p-3">
                            <div class="mb-2 text-center font-semibold text-gray-700">Debit (Dr)</div>
                            <div class="space-y-2">
                                @forelse($row->debits as $transaction)
                                    <div class="flex items-start justify-between gap-3 text-sm">
                                        <span class="min-w-0">
                                            <span class="block truncate font-semibold text-gray-700">{{ $transaction->t_account_title ?? $transaction->reference_no ?? $transaction->description ?? '-' }}</span>
                                            @if(!empty($transaction->t_account_meta))
                                                <span class="block truncate text-xs text-gray-400">{{ $transaction->t_account_meta }}</span>
                                            @endif
                                        </span>
                                        <span class="shrink-0 font-semibold text-gray-800">{{ number_format((float) $transaction->amount, 2) }}</span>
                                    </div>
                                @empty
                                    <div class="text-center text-sm text-gray-400">No debit entries</div>
                                @endforelse
                            </div>
                        </div>
                        <div class="p-3">
                            <div class="mb-2 text-center font-semibold text-gray-700">Credit (Cr)</div>
                            <div class="space-y-2">
                                @forelse($row->credits as $transaction)
                                    <div class="flex items-start justify-between gap-3 text-sm">
                                        <span class="shrink-0 font-semibold text-gray-800">{{ number_format((float) $transaction->amount, 2) }}</span>
                                        <span class="min-w-0 text-right">
                                            <span class="block truncate font-semibold text-gray-700">{{ $transaction->t_account_title ?? $transaction->reference_no ?? $transaction->description ?? '-' }}</span>
                                            @if(!empty($transaction->t_account_meta))
                                                <span class="block truncate text-xs text-gray-400">{{ $transaction->t_account_meta }}</span>
                                            @endif
                                        </span>
                                    </div>
                                @empty
                                    <div class="text-center text-sm text-gray-400">No credit entries</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                    <div class="mt-3 border-t border-gray-300 pt-3 text-right font-bold {{ ($row->balance_side ?? 'Dr') === 'Dr' ? 'text-green-700' : 'text-red-700' }}">
                        Balance: {{ number_format(abs((float) $row->balance), 2) }} {{ $row->balance_side ?? 'Dr' }}
                    </div>
                </div>
            @empty
                <div class="xl:col-span-2 bg-white rounded-xl shadow p-10 text-center text-gray-500">No T-account entries found for the selected filter.</div>
            @endforelse
        </div>
    @endif

    @if($section === 'trial-balance')
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="p-5 border-b flex flex-col md:flex-row md:items-center md:justify-between gap-2">
                <h3 class="font-semibold text-gray-800"><i class="fas fa-scale-balanced text-green-600 mr-2"></i>Trial Balance Check</h3>
                <span class="px-3 py-1 rounded-full text-xs font-semibold {{ abs((float) ($trialBalanceTotals['difference'] ?? 0)) < 0.01 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                    {{ abs((float) ($trialBalanceTotals['difference'] ?? 0)) < 0.01 ? 'Balanced' : 'Difference: ' . number_format((float) $trialBalanceTotals['difference'], 2) }}
                </span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-5 py-3 text-left">Code</th>
                            <th class="px-5 py-3 text-left">Account</th>
                            <th class="px-5 py-3 text-left">Type</th>
                            <th class="px-5 py-3 text-right">Debit</th>
                            <th class="px-5 py-3 text-right">Credit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($trialBalanceRows as $row)
                            <tr>
                                <td class="px-5 py-3 font-mono font-semibold text-blue-600">{{ $row->account->code }}</td>
                                <td class="px-5 py-3">{{ $row->account->name }}</td>
                                <td class="px-5 py-3">{{ ucfirst($row->account->type) }}</td>
                                <td class="px-5 py-3 text-right font-semibold">{{ $row->debit_balance > 0 ? number_format((float) $row->debit_balance, 2) : '-' }}</td>
                                <td class="px-5 py-3 text-right font-semibold">{{ $row->credit_balance > 0 ? number_format((float) $row->credit_balance, 2) : '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-10 text-center text-gray-500">No trial balance rows found.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-gray-50 border-t">
                        <tr>
                            <td colspan="3" class="px-5 py-4 text-right font-bold">Total</td>
                            <td class="px-5 py-4 text-right font-bold">{{ number_format((float) ($trialBalanceTotals['debit'] ?? 0), 2) }}</td>
                            <td class="px-5 py-4 text-right font-bold">{{ number_format((float) ($trialBalanceTotals['credit'] ?? 0), 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    @endif

    @if($section === 'balance-sheet')
        @php
            $sheetRows = $balanceSheet['rows'] ?? ['assets' => collect(), 'liabilities' => collect(), 'equity' => collect()];
            $sheetTotals = $balanceSheet['totals'] ?? ['assets' => 0, 'liabilities' => 0, 'equity' => 0, 'liabilities_equity' => 0, 'difference' => 0];
        @endphp
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white rounded-xl shadow-md p-5 border-l-4 border-blue-500">
                <p class="text-sm text-gray-500">Assets</p>
                <div class="text-2xl font-bold text-gray-900">{{ number_format((float) $sheetTotals['assets'], 2) }}</div>
            </div>
            <div class="bg-white rounded-xl shadow-md p-5 border-l-4 border-red-500">
                <p class="text-sm text-gray-500">Liabilities</p>
                <div class="text-2xl font-bold text-gray-900">{{ number_format((float) $sheetTotals['liabilities'], 2) }}</div>
            </div>
            <div class="bg-white rounded-xl shadow-md p-5 border-l-4 border-indigo-500">
                <p class="text-sm text-gray-500">Equity</p>
                <div class="text-2xl font-bold text-gray-900">{{ number_format((float) $sheetTotals['equity'], 2) }}</div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="p-5 border-b flex flex-col md:flex-row md:items-center md:justify-between gap-2">
                <div>
                    <h3 class="font-semibold text-gray-800"><i class="fas fa-file-invoice-dollar text-blue-600 mr-2"></i>Business Position</h3>
                    <p class="text-sm text-gray-500">Assets, Liabilities, and Equity from the Chart Accounting balances.</p>
                </div>
                <span class="px-3 py-1 rounded-full text-xs font-semibold {{ abs((float) $sheetTotals['difference']) < 0.01 ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                    {{ abs((float) $sheetTotals['difference']) < 0.01 ? 'Balanced' : 'Difference: ' . number_format((float) $sheetTotals['difference'], 2) }}
                </span>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-3 divide-y lg:divide-y-0 lg:divide-x">
                @foreach(['assets' => 'Assets', 'liabilities' => 'Liabilities', 'equity' => 'Equity'] as $key => $label)
                    <div class="p-5">
                        <h4 class="mb-4 text-sm font-bold uppercase tracking-wide text-gray-500">{{ $label }}</h4>
                        <div class="space-y-3">
                            @forelse($sheetRows[$key] as $account)
                                <div class="flex items-center justify-between gap-3 rounded-lg bg-gray-50 px-3 py-2">
                                    <div class="min-w-0">
                                        <div class="font-semibold text-gray-800 truncate">{{ $account->name }}</div>
                                        <div class="text-xs text-gray-500">{{ $account->code }}</div>
                                    </div>
                                    <div class="font-bold text-gray-900">{{ number_format((float) $account->current_balance, 2) }}</div>
                                </div>
                            @empty
                                <div class="rounded-lg bg-gray-50 px-3 py-6 text-center text-sm text-gray-500">No {{ strtolower($label) }} accounts.</div>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="border-t bg-gray-50 p-5 flex flex-col md:flex-row md:items-center md:justify-end gap-4 text-sm">
                <div><span class="text-gray-500">Assets:</span> <strong>{{ number_format((float) $sheetTotals['assets'], 2) }}</strong></div>
                <div><span class="text-gray-500">Liabilities + Equity:</span> <strong>{{ number_format((float) $sheetTotals['liabilities_equity'], 2) }}</strong></div>
            </div>
        </div>
    @endif

    @if(in_array($section, ['cash-book', 'bank-book'], true))
        @php
            $isCashBook = $section === 'cash-book';
            $bookRows = $isCashBook ? $cashBookTransactions : $bankBookTransactions;
            $bookTotals = $isCashBook ? $cashBookTotals : $bankBookTotals;
            $resetRoute = $isCashBook ? route('accounting.cash-book') : route('accounting.bank-book');
        @endphp
        <div class="bg-white rounded-lg shadow p-4">
            <form method="GET" class="grid grid-cols-1 {{ $isCashBook ? 'md:grid-cols-5' : 'md:grid-cols-6' }} gap-3">
                <input type="date" name="from" value="{{ request('from') }}" class="border rounded-lg px-3 py-2">
                <input type="date" name="to" value="{{ request('to') }}" class="border rounded-lg px-3 py-2">
                <select name="direction" class="border rounded-lg px-3 py-2">
                    <option value="">Cash/Bank In & Out</option>
                    <option value="in" {{ request('direction') === 'in' ? 'selected' : '' }}>In only</option>
                    <option value="out" {{ request('direction') === 'out' ? 'selected' : '' }}>Out only</option>
                </select>
                @if(!$isCashBook)
                    <select name="payment_method" class="border rounded-lg px-3 py-2">
                        <option value="">All bank methods</option>
                        @foreach(['bank_deposit' => 'Bank Deposit', 'bank_transfer' => 'Bank Transfer', 'card' => 'Card', 'mobile_payment' => 'Online / Mobile'] as $value => $label)
                            <option value="{{ $value }}" {{ request('payment_method') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                @endif
                <input name="search" value="{{ request('search') }}" placeholder="Reference or note" class="border rounded-lg px-3 py-2">
                <div class="flex gap-2">
                    <button class="flex-1 bg-gray-800 text-white rounded-lg px-3 py-2">Filter</button>
                    <a href="{{ $resetRoute }}" class="flex-1 text-center bg-gray-100 text-gray-700 rounded-lg px-3 py-2">Reset</a>
                </div>
            </form>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white rounded-xl shadow-md p-5 border-l-4 border-green-500">
                <p class="text-sm text-gray-500">{{ $isCashBook ? 'Cash In' : 'Bank In' }}</p>
                <div class="text-2xl font-bold text-green-700">{{ number_format((float) ($bookTotals['in'] ?? 0), 2) }}</div>
            </div>
            <div class="bg-white rounded-xl shadow-md p-5 border-l-4 border-red-500">
                <p class="text-sm text-gray-500">{{ $isCashBook ? 'Cash Out' : 'Bank Out' }}</p>
                <div class="text-2xl font-bold text-red-700">{{ number_format((float) ($bookTotals['out'] ?? 0), 2) }}</div>
            </div>
            <div class="bg-white rounded-xl shadow-md p-5 border-l-4 border-blue-500">
                <p class="text-sm text-gray-500">Net Movement</p>
                <div class="text-2xl font-bold text-gray-900">{{ number_format((float) ($bookTotals['net'] ?? 0), 2) }}</div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="p-5 border-b">
                <h3 class="font-semibold text-gray-800">
                    <i class="fas {{ $isCashBook ? 'fa-cash-register text-green-600' : 'fa-building-columns text-indigo-600' }} mr-2"></i>
                    {{ $isCashBook ? 'Cash In / Cash Out Transactions' : 'Bank, Card, Transfer, and Online Payment Transactions' }}
                </h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-5 py-3 text-left">Date</th>
                            <th class="px-5 py-3 text-left">Account</th>
                            <th class="px-5 py-3 text-left">Method</th>
                            <th class="px-5 py-3 text-left">Reference</th>
                            <th class="px-5 py-3 text-left">Note</th>
                            <th class="px-5 py-3 text-right">In</th>
                            <th class="px-5 py-3 text-right">Out</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($bookRows as $transaction)
                            <tr>
                                <td class="px-5 py-3 whitespace-nowrap">{{ $transaction->transaction_date?->format('M d, Y') }}</td>
                                <td class="px-5 py-3">{{ $transaction->account?->name ?? 'Account' }}</td>
                                <td class="px-5 py-3">{{ str_replace('_', ' ', ucfirst($transaction->payment_method)) }}</td>
                                <td class="px-5 py-3">{{ $transaction->reference_no ?: $transaction->cheque_number ?: '-' }}</td>
                                <td class="px-5 py-3 text-gray-600">{{ $transaction->description ?: '-' }}</td>
                                <td class="px-5 py-3 text-right font-semibold text-green-700">{{ $transaction->direction === 'in' ? number_format((float) $transaction->amount, 2) : '-' }}</td>
                                <td class="px-5 py-3 text-right font-semibold text-red-700">{{ $transaction->direction === 'out' ? number_format((float) $transaction->amount, 2) : '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-5 py-10 text-center text-gray-500">No transactions found for this book.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if($section === 'owner-equity')
        @php
            $canCreateOwnerEquity = auth()->user()?->hasPermission('accounting.owner-equity.create');
            $canEditOwnerEquity = auth()->user()?->hasPermission('accounting.owner-equity.edit');
            $canDeleteOwnerEquity = auth()->user()?->hasPermission('accounting.owner-equity.delete');
        @endphp
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white rounded-xl shadow-md p-5 border-l-4 border-green-500">
                <p class="text-sm text-gray-500">Owner Investment</p>
                <div class="text-2xl font-bold text-green-700">{{ number_format((float) ($ownerEquityTotals['investment'] ?? 0), 2) }}</div>
            </div>
            <div class="bg-white rounded-xl shadow-md p-5 border-l-4 border-red-500">
                <p class="text-sm text-gray-500">Owner Withdrawals</p>
                <div class="text-2xl font-bold text-red-700">{{ number_format((float) ($ownerEquityTotals['withdrawal'] ?? 0), 2) }}</div>
            </div>
            <div class="bg-white rounded-xl shadow-md p-5 border-l-4 border-purple-500">
                <p class="text-sm text-gray-500">Net Owner Equity Movement</p>
                <div class="text-2xl font-bold text-gray-900">{{ number_format((float) ($ownerEquityTotals['net'] ?? 0), 2) }}</div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-4">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-3">
                <input type="date" name="from" value="{{ request('from') }}" class="border rounded-lg px-3 py-2">
                <input type="date" name="to" value="{{ request('to') }}" class="border rounded-lg px-3 py-2">
                <select name="type" class="border rounded-lg px-3 py-2">
                    <option value="">Investment & withdrawal</option>
                    <option value="investment" {{ request('type') === 'investment' ? 'selected' : '' }}>Owner Investment</option>
                    <option value="withdrawal" {{ request('type') === 'withdrawal' ? 'selected' : '' }}>Owner Withdrawal</option>
                </select>
                <select name="payment_account" class="border rounded-lg px-3 py-2">
                    <option value="">Cash & Bank</option>
                    <option value="cash" {{ request('payment_account') === 'cash' ? 'selected' : '' }}>Cash</option>
                    <option value="bank" {{ request('payment_account') === 'bank' ? 'selected' : '' }}>Bank</option>
                </select>
                <input name="search" value="{{ request('search') }}" placeholder="Owner/reference/note" class="border rounded-lg px-3 py-2">
                <div class="flex gap-2">
                    <button class="flex-1 bg-gray-800 text-white rounded-lg px-3 py-2">Filter</button>
                    <a href="{{ route('accounting.owner-equity') }}" class="flex-1 text-center bg-gray-100 text-gray-700 rounded-lg px-3 py-2">Reset</a>
                </div>
            </form>
        </div>

        @if($canCreateOwnerEquity)
            <div class="bg-white rounded-xl shadow-md p-5">
                <h3 class="font-semibold text-gray-800 mb-4"><i class="fas fa-user-tie text-purple-600 mr-2"></i>Add Owner Capital / Drawing</h3>
                <form method="POST" action="{{ route('accounting.owner-equity.store') }}" class="grid grid-cols-1 md:grid-cols-4 gap-3">
                    @csrf
                    <select name="type" class="border rounded-lg px-3 py-2" required>
                        <option value="investment">Owner Investment</option>
                        <option value="withdrawal">Owner Withdrawal</option>
                    </select>
                    <input name="owner_name" placeholder="Owner name (optional)" class="border rounded-lg px-3 py-2">
                    <input type="date" name="movement_date" value="{{ date('Y-m-d') }}" class="border rounded-lg px-3 py-2" required>
                    <select name="payment_account" class="border rounded-lg px-3 py-2" required>
                        <option value="cash">Cash</option>
                        <option value="bank">Bank</option>
                    </select>
                    <input type="number" step="0.01" min="0.01" name="amount" placeholder="Amount" class="border rounded-lg px-3 py-2" required>
                    <input name="reference_no" placeholder="Reference no" class="border rounded-lg px-3 py-2">
                    <input name="note" placeholder="Note" class="border rounded-lg px-3 py-2 md:col-span-2">
                    <button class="md:col-span-4 bg-purple-600 text-white rounded-lg px-4 py-2">Save Owner Movement</button>
                </form>
            </div>
        @endif

        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="p-5 border-b">
                <h3 class="font-semibold text-gray-800"><i class="fas fa-user-tie text-purple-600 mr-2"></i>Owner Investment & Withdrawal Records</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Date</th>
                            <th class="px-4 py-3 text-left">Type</th>
                            <th class="px-4 py-3 text-left">Owner</th>
                            <th class="px-4 py-3 text-left">Account</th>
                            <th class="px-4 py-3 text-left">Reference</th>
                            <th class="px-4 py-3 text-right">Amount</th>
                            <th class="px-4 py-3 text-left">Note</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($ownerEquityMovements as $movement)
                            <tr>
                                <td class="px-4 py-3 whitespace-nowrap">{{ $movement->movement_date?->format('M d, Y') }}</td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 rounded-full text-xs font-semibold {{ $movement->type === 'investment' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                        {{ $movement->type === 'investment' ? 'Investment' : 'Withdrawal' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">{{ $movement->owner_name ?: '-' }}</td>
                                <td class="px-4 py-3">{{ ucfirst($movement->payment_account) }} / {{ $movement->equityAccount?->name }}</td>
                                <td class="px-4 py-3">{{ $movement->reference_no ?: '-' }}</td>
                                <td class="px-4 py-3 text-right font-bold">{{ number_format((float) $movement->amount, 2) }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $movement->note ?: '-' }}</td>
                                <td class="px-4 py-3 text-right">
                                    <div class="inline-flex items-center gap-2">
                                        @if($canEditOwnerEquity)
                                            <button type="button" class="text-blue-600 hover:bg-blue-50 rounded px-2 py-1" data-owner-edit="{{ e(json_encode([
                                                'id' => $movement->id,
                                                'type' => $movement->type,
                                                'owner_name' => $movement->owner_name,
                                                'movement_date' => $movement->movement_date?->format('Y-m-d'),
                                                'payment_account' => $movement->payment_account,
                                                'amount' => (string) $movement->amount,
                                                'reference_no' => $movement->reference_no,
                                                'note' => $movement->note,
                                                'action' => route('accounting.owner-equity.update', $movement),
                                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) }}">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        @endif
                                        @if($canDeleteOwnerEquity)
                                            <form method="POST" action="{{ route('accounting.owner-equity.destroy', $movement) }}" data-owner-delete-form>
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:bg-red-50 rounded px-2 py-1">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-5 py-10 text-center text-gray-500">No owner capital or drawing records found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($canEditOwnerEquity)
            <div id="ownerEquityEditModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black bg-opacity-50 p-4">
                <div class="bg-white rounded-xl shadow-xl w-full max-w-3xl">
                    <div class="p-5 border-b flex items-center justify-between">
                        <h3 class="font-semibold text-gray-800">Edit Owner Capital / Drawing</h3>
                        <button type="button" class="text-gray-500 hover:text-gray-800" data-owner-edit-close><i class="fas fa-times"></i></button>
                    </div>
                    <form method="POST" id="ownerEquityEditForm" class="p-5 grid grid-cols-1 md:grid-cols-2 gap-3">
                        @csrf
                        @method('PUT')
                        <select name="type" id="ownerEditType" class="border rounded-lg px-3 py-2" required>
                            <option value="investment">Owner Investment</option>
                            <option value="withdrawal">Owner Withdrawal</option>
                        </select>
                        <input name="owner_name" id="ownerEditName" placeholder="Owner name (optional)" class="border rounded-lg px-3 py-2">
                        <input type="date" name="movement_date" id="ownerEditDate" class="border rounded-lg px-3 py-2" required>
                        <select name="payment_account" id="ownerEditPayment" class="border rounded-lg px-3 py-2" required>
                            <option value="cash">Cash</option>
                            <option value="bank">Bank</option>
                        </select>
                        <input type="number" step="0.01" min="0.01" name="amount" id="ownerEditAmount" placeholder="Amount" class="border rounded-lg px-3 py-2" required>
                        <input name="reference_no" id="ownerEditReference" placeholder="Reference no" class="border rounded-lg px-3 py-2">
                        <input name="note" id="ownerEditNote" placeholder="Note" class="border rounded-lg px-3 py-2 md:col-span-2">
                        <div class="md:col-span-2 flex justify-end gap-2 pt-2">
                            <button type="button" class="bg-gray-100 text-gray-700 rounded-lg px-4 py-2" data-owner-edit-close>Cancel</button>
                            <button class="bg-purple-600 text-white rounded-lg px-4 py-2">Update</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        @if($canDeleteOwnerEquity)
            <div id="ownerEquityDeleteModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black bg-opacity-50 p-4">
                <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
                    <div class="p-5 border-b">
                        <h3 class="font-semibold text-gray-800">Delete owner movement?</h3>
                    </div>
                    <div class="p-5 text-gray-600">This will remove the owner record and reverse its effect from Cash/Bank and Equity accounts.</div>
                    <div class="p-5 border-t flex justify-end gap-2">
                        <button type="button" class="bg-gray-100 text-gray-700 rounded-lg px-4 py-2" data-owner-delete-cancel>Cancel</button>
                        <button type="button" class="bg-red-600 text-white rounded-lg px-4 py-2" data-owner-delete-confirm>Delete</button>
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>

@if($section === 'overview')
@push('scripts')
<script>
    (function () {
        function formatDate(date) {
            const pad = (n) => n < 10 ? '0' + n : '' + n;
            return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
        }

        function goWithRange(start, end) {
            const url = new URL(window.location.href);
            url.searchParams.set('from', start);
            url.searchParams.set('to', end);
            window.location.href = url.toString();
        }

        const error = document.getElementById('accounting_custom_error');
        const customStart = document.getElementById('accounting_custom_start');
        const customEnd = document.getElementById('accounting_custom_end');

        document.querySelectorAll('[data-accounting-range]').forEach((button) => {
            button.addEventListener('click', () => {
                const range = button.dataset.accountingRange;
                const end = new Date();
                let start = new Date(end.getFullYear(), end.getMonth(), end.getDate());

                if (range === 'yesterday') {
                    start.setDate(start.getDate() - 1);
                    goWithRange(formatDate(start), formatDate(start));
                    return;
                }

                if (range === 'this_week') {
                    const day = start.getDay();
                    const diff = (day + 6) % 7;
                    start.setDate(start.getDate() - diff);
                    goWithRange(formatDate(start), formatDate(end));
                    return;
                }

                if (range === 'this_month') {
                    start = new Date(end.getFullYear(), end.getMonth(), 1);
                    goWithRange(formatDate(start), formatDate(end));
                    return;
                }

                if (range === 'custom') {
                    if (!customStart?.value || !customEnd?.value) {
                        error?.classList.remove('hidden');
                        return;
                    }
                    error?.classList.add('hidden');
                    goWithRange(customStart.value, customEnd.value);
                    return;
                }

                goWithRange(formatDate(end), formatDate(end));
            });
        });
    })();
</script>
@endpush
@endif

@if($section === 'transactions')
@push('scripts')
<script>
    (function () {
        const bankMethods = ['bank_deposit', 'bank_transfer', 'card', 'mobile_payment'];

        document.querySelectorAll('.accounting-payment-method').forEach((methodSelect) => {
            const form = methodSelect.closest('form');
            const bankSelect = form?.querySelector('.accounting-bank-select');

            function updateBankSelect() {
                const needsBank = bankMethods.includes(methodSelect.value);
                bankSelect?.classList.toggle('hidden', !needsBank);
                if (bankSelect) {
                    bankSelect.required = needsBank && bankSelect.options.length > 1;
                    if (! needsBank) {
                        bankSelect.value = '';
                    }
                }
            }

            methodSelect.addEventListener('change', updateBankSelect);
            updateBankSelect();
        });
    })();
</script>
@endpush
@endif

@if($section === 'banks')
@push('scripts')
<script>
    (function () {
        function numberValue(value) {
            const parsed = parseFloat(String(value || '').replace(/,/g, ''));
            return Number.isFinite(parsed) ? parsed : 0;
        }

        function money(value) {
            return Number(value || 0).toFixed(2);
        }

        document.querySelectorAll('.bank-reconcile-form').forEach((form) => {
            const bankSelect = form.querySelector('.bank-account-select');
            const monthInput = form.querySelector('.bank-statement-month');
            const statementInput = form.querySelector('.bank-statement-balance');
            const systemInput = form.querySelector('.bank-system-balance');
            const differenceInput = form.querySelector('.bank-difference');

            function selectedBankId() {
                return bankSelect?.value || '';
            }

            function applySelectedBank() {
                const bankId = selectedBankId();
                if (bankId && form.dataset.actionTemplate) {
                    form.action = form.dataset.actionTemplate.replace('__BANK_ID__', encodeURIComponent(bankId));
                }
            }

            function updateDifference() {
                const statementBalance = numberValue(statementInput?.value);
                const systemBalance = numberValue(systemInput?.value);
                differenceInput.value = money(statementBalance - systemBalance);
            }

            async function updateSystemBalance() {
                applySelectedBank();
                const bankId = selectedBankId();
                if (!bankId || !monthInput?.value) {
                    systemInput.value = money(0);
                    updateDifference();
                    return;
                }

                try {
                    const balanceUrl = form.dataset.systemBalanceUrl
                        || form.dataset.systemBalanceTemplate?.replace('__BANK_ID__', encodeURIComponent(bankId));
                    const url = new URL(balanceUrl, window.location.origin);
                    url.searchParams.set('month', monthInput.value);
                    const response = await fetch(url.toString(), {
                        headers: { Accept: 'application/json' },
                        credentials: 'same-origin',
                    });
                    if (!response.ok) {
                        throw new Error('Unable to load system balance');
                    }
                    const data = await response.json();
                    systemInput.value = money(data.system_balance || 0);
                } catch (_) {
                    systemInput.value = money(numberValue(systemInput.value));
                }

                updateDifference();
            }

            bankSelect?.addEventListener('change', updateSystemBalance);
            monthInput?.addEventListener('change', updateSystemBalance);
            statementInput?.addEventListener('input', updateDifference);
            form.addEventListener('submit', (event) => {
                applySelectedBank();
                if (bankSelect && !selectedBankId()) {
                    event.preventDefault();
                    bankSelect.focus();
                }
            });
            updateSystemBalance();
        });
    })();
</script>
@endpush
@endif

@if($section === 'owner-equity')
@push('scripts')
<script>
    (function () {
        const editModal = document.getElementById('ownerEquityEditModal');
        const editForm = document.getElementById('ownerEquityEditForm');
        const deleteModal = document.getElementById('ownerEquityDeleteModal');
        let deleteForm = null;

        function showModal(modal) {
            modal?.classList.remove('hidden');
            modal?.classList.add('flex');
        }

        function hideModal(modal) {
            modal?.classList.add('hidden');
            modal?.classList.remove('flex');
        }

        document.querySelectorAll('[data-owner-edit]').forEach((button) => {
            button.addEventListener('click', () => {
                const data = JSON.parse(button.dataset.ownerEdit || '{}');
                editForm.action = data.action || '';
                document.getElementById('ownerEditType').value = data.type || 'investment';
                document.getElementById('ownerEditName').value = data.owner_name || '';
                document.getElementById('ownerEditDate').value = data.movement_date || '';
                document.getElementById('ownerEditPayment').value = data.payment_account || 'cash';
                document.getElementById('ownerEditAmount').value = data.amount || '';
                document.getElementById('ownerEditReference').value = data.reference_no || '';
                document.getElementById('ownerEditNote').value = data.note || '';
                showModal(editModal);
            });
        });

        document.querySelectorAll('[data-owner-edit-close]').forEach((button) => {
            button.addEventListener('click', () => hideModal(editModal));
        });

        document.querySelectorAll('[data-owner-delete-form]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                event.preventDefault();
                deleteForm = form;
                showModal(deleteModal);
            });
        });

        document.querySelector('[data-owner-delete-cancel]')?.addEventListener('click', () => {
            deleteForm = null;
            hideModal(deleteModal);
        });

        document.querySelector('[data-owner-delete-confirm]')?.addEventListener('click', () => {
            deleteForm?.submit();
        });

        editModal?.addEventListener('click', (event) => {
            if (event.target === editModal) {
                hideModal(editModal);
            }
        });

        deleteModal?.addEventListener('click', (event) => {
            if (event.target === deleteModal) {
                deleteForm = null;
                hideModal(deleteModal);
            }
        });
    })();
</script>
@endpush
@endif
@endsection
