@extends('layouts.app')
@section('title', 'Bank Balances & Transfers')
@section('page-title', 'Bank Balances & Transfers')
@section('content')
@php
    $canManage = auth()->user()->hasPermission('accounting.manage');
    $accountName = $bank ? $bank->bank_name.' / '.$bank->account_name : ($account === 'cash' ? 'Cash in hand' : 'Unassigned bank entries');
@endphp
<link rel="stylesheet" href="{{ asset('css/accounting-balances.css') }}?v=20261017b">
<div class="bank-page">
    <div class="bank-toolbar">
        <div><h3>Your accounts</h3><p class="bank-muted">Select an account to see its balance and activity.</p></div>
        <div class="bank-actions">
            @if($canManage)<button type="button" class="bank-button" data-bank-open="transfer-money"><i class="fas fa-exchange-alt" aria-hidden="true"></i>Transfer money</button><a href="{{ route('accounting.banks') }}#add-bank-account" class="bank-button bank-button-primary"><i class="fas fa-plus" aria-hidden="true"></i>Add bank account</a>@endif
        </div>
    </div>
    @if(session('success'))<div role="status" class="rounded-lg bg-green-50 p-4 text-green-800">{{ session('success') }}</div>@endif
    @if($errors->any())<div role="alert" class="rounded-lg bg-red-50 p-4 text-red-700">{{ $errors->first() }}</div>@endif
    <div class="bank-accounts" aria-label="Account balances">
        @foreach($cards as $card)
        <a href="{{ route('accounting.bank-balances', ['account' => $card['bank']->id]) }}" class="bank-card" aria-current="{{ $account === (string)$card['bank']->id ? 'true' : 'false' }}">
            <span class="bank-account-icon"><i class="fas fa-university" aria-hidden="true"></i></span>
            <div class="bank-card-body"><h4>{{ $card['bank']->bank_name }} @if(!$card['bank']->is_active)<span class="bank-muted">(Inactive)</span>@endif</h4>
            <p class="bank-muted">{{ $card['bank']->account_name }} · {{ $card['bank']->account_number ?: 'No account number' }}</p>
            <p class="bank-amount {{ $card['balance'] < 0 ? 'bank-negative' : '' }}">{{ $currency }} {{ number_format($card['balance'], 2) }}</p>
            <p class="bank-card-status {{ $card['balance'] < 0 ? 'bank-negative' : '' }}">{{ $card['balance'] < 0 ? 'Overdrawn' : 'Current balance' }} · {{ $account === (string)$card['bank']->id ? 'Selected' : 'View history →' }}</p></div>
        </a>
        @endforeach
        <a href="{{ route('accounting.bank-balances', ['account' => 'cash']) }}" class="bank-card" aria-current="{{ $account === 'cash' ? 'true' : 'false' }}">
            <span class="bank-account-icon"><i class="fas fa-wallet" aria-hidden="true"></i></span>
            <div class="bank-card-body"><h4>Cash in hand</h4><p class="bank-muted">Main cash account</p>
            <p class="bank-amount {{ $cashBalance < 0 ? 'bank-negative' : '' }}">{{ $currency }} {{ number_format($cashBalance, 2) }}</p>
            <p class="bank-card-status">Current balance · {{ $account === 'cash' ? 'Selected' : 'View history →' }}</p></div>
        </a>
    </div>
    @if($banks->isEmpty())<div class="bank-no-accounts"><span class="bank-account-icon"><i class="fas fa-university" aria-hidden="true"></i></span><div><strong>No bank accounts added yet</strong><p class="bank-muted">Add your business bank accounts to track deposits, payments and transfers separately.</p>@if($canManage)<a href="{{ route('accounting.banks') }}#add-bank-account" class="text-blue-600 text-sm font-semibold">Add your first bank account →</a>@endif</div></div>@endif
    @if($unassignedCount)
    <div class="bank-alert">{{ $unassignedCount }} older bank entries need an account. <a href="{{ route('accounting.bank-balances', ['account' => 'unassigned', 'all_dates' => 1]) }}">Review entries</a> to include them in the correct bank balance.</div>
    @endif
    <section class="bank-panel" aria-labelledby="bank-history-title">
        <div class="bank-panel-heading">
            <div><h3 id="bank-history-title">{{ $accountName }} — History</h3><p class="bank-muted">{{ $from ? \Carbon\Carbon::parse($from)->format('d M Y') : 'Beginning' }} to {{ $to ? \Carbon\Carbon::parse($to)->format('d M Y') : 'latest' }}</p></div>
            <div class="bank-actions"><a href="{{ route('accounting.bank-balances', ['account' => $account, 'all_dates' => 1]) }}">All history</a>@if($canManage && $account !== 'unassigned')<button type="button" class="bank-button" data-bank-open="opening-balance"><i class="fas fa-sliders-h" aria-hidden="true"></i>Opening balance</button>@endif</div>
        </div>
        <form method="GET" class="bank-history-filters">
            <label class="bank-account-filter">Account<select name="account"><option value="cash" @selected($account === 'cash')>Cash in hand</option>@foreach($banks as $item)<option value="{{ $item->id }}" @selected($account === (string)$item->id)>{{ $item->bank_name }} · {{ $item->account_number }}</option>@endforeach @if($unassignedCount)<option value="unassigned" @selected($account === 'unassigned')>Unassigned entries</option>@endif</select></label>
            <label>From<input type="date" name="from" value="{{ $from }}"></label>
            <label>To<input type="date" name="to" value="{{ $to }}"></label>
            <label class="bank-movement-filter">Movement<select name="direction"><option value="">All movements</option><option value="in" @selected(request('direction') === 'in')>Money in</option><option value="out" @selected(request('direction') === 'out')>Money out</option></select></label>
            <label class="bank-search-field">Search activity<input type="search" name="search" value="{{ request('search') }}" maxlength="100" placeholder="Reference, invoice or reason"></label>
            <div class="bank-filter-actions"><a href="{{ route('accounting.bank-balances', ['account' => $account]) }}" class="bank-button">Reset</a><button class="bank-button bank-button-primary">Apply filters</button></div>
        </form>
        <div class="bank-period">
            @foreach(['opening' => 'Starting balance', 'in' => 'Money in', 'out' => 'Money out', 'closing' => 'Ending balance'] as $key => $label)
            <div class="bank-period-item"><p>{{ $label }}</p><strong class="{{ $totals[$key] < 0 ? 'bank-negative' : ($key === 'in' ? 'text-green-700' : '') }}">{{ $currency }} {{ number_format($totals[$key], 2) }}</strong></div>
            @endforeach
        </div>
        @if($totals['closing'] < 0 && $account !== 'unassigned')<div class="bank-overdrawn">This account is overdrawn by {{ $currency }} {{ number_format(abs($totals['closing']), 2) }} at the end of this period. See the payments and transfers below to trace the balance.</div>@endif
        <p class="bank-history-note">Totals and running balances cover the selected dates. Searching or filtering movements only changes the entries shown.</p>
        <div class="bank-table-wrap"><table data-export-table data-export-title="{{ $accountName }} — Transactions" class="bank-table">
            <thead><tr><th>Date</th><th>Reference</th><th>Reason / details</th><th class="bank-numeric">In</th><th class="bank-numeric">Out</th><th class="bank-numeric">Balance</th>@if($account === 'unassigned' && $canManage)<th>Assign bank</th>@endif</tr></thead>
            <tbody>
                @forelse($history as $entry)
                <tr><td data-label="Date">{{ $entry->transaction_date?->format('d M Y') }}</td>
                    <td data-label="Reference">@if($entry->detail_url)<a href="{{ $entry->detail_url }}">{{ $entry->reference_no ?: 'View details' }}</a>@else {{ $entry->reference_no ?: '—' }} @endif</td>
                    <td data-label="Details" class="bank-reason"><p>{{ $entry->description ?: ucwords(str_replace('_', ' ', $entry->source_type ?? 'Transaction')) }}</p><p class="bank-entry-meta">{{ ucwords(str_replace('_', ' ', $entry->payment_method)) }} · {{ $entry->user?->name ?? 'System' }}@if($account === 'unassigned') · {{ $entry->account?->name }} @endif</p></td>
                    <td data-label="Money in" class="bank-numeric bank-in">{{ $entry->direction === 'in' ? number_format($entry->amount, 2) : '—' }}</td>
                    <td data-label="Money out" class="bank-numeric bank-out">{{ $entry->direction === 'out' ? number_format($entry->amount, 2) : '—' }}</td>
                    <td data-label="Running balance" class="bank-numeric bank-balance-cell font-semibold {{ $entry->running_balance < 0 ? 'bank-negative' : '' }}">{{ number_format($entry->running_balance, 2) }}</td>
                    @if($account === 'unassigned' && $canManage)<td data-label="Assign bank" class="bank-assign"><form method="POST" action="{{ route('accounting.bank-entries.assign', $entry) }}" class="space-y-2">@csrf<label>Bank account<select name="bank_account_id" required><option value="">Select bank</option>@foreach($banks->where('is_active', true) as $item)<option value="{{ $item->id }}">{{ $item->bank_name }} · {{ $item->account_number }}</option>@endforeach</select></label><label>Reason<input name="reason" required maxlength="200" placeholder="Reason for assignment"></label><button class="bank-button">Assign</button></form></td>@endif
                </tr>
                @empty<tr><td colspan="{{ $account === 'unassigned' && $canManage ? 7 : 6 }}" class="bank-empty"><i class="fas fa-receipt" aria-hidden="true"></i><strong>No activity found</strong><p>No movements match this period and these filters.</p><a href="{{ route('accounting.bank-balances', ['account' => $account, 'all_dates' => 1]) }}">View all history</a></td></tr>@endforelse
            </tbody>
        </table></div>
        @if($history->hasPages())<div class="bank-pagination">{{ $history->links() }}</div>@endif
    </section>
    @if($canManage)
    <div class="bank-tools">
        <details id="transfer-money" class="bank-panel bank-tool" @if(old('from_account') || old('request_token')) open @endif>
            <summary><span class="bank-account-icon"><i class="fas fa-exchange-alt" aria-hidden="true"></i></span><span><strong>Transfer money</strong><span class="bank-muted">Move funds between banks and cash</span></span><i class="fas fa-chevron-down bank-tool-chevron" aria-hidden="true"></i></summary>
            <form method="POST" action="{{ route('accounting.bank-transfers.store') }}">@csrf<input type="hidden" name="request_token" value="{{ old('request_token', (string)\Illuminate\Support\Str::uuid()) }}">
                @foreach(['from_account' => 'From account', 'to_account' => 'To account'] as $field => $label)
                <label>{{ $label }}<select name="{{ $field }}" required><option value="">Choose account</option><option value="cash" @selected(old($field) === 'cash')>Cash in hand</option>@foreach($banks->where('is_active', true) as $item)<option value="{{ $item->id }}" @selected((string)old($field) === (string)$item->id)>{{ $item->bank_name }} · {{ $item->account_number }}</option>@endforeach</select></label>
                @endforeach
                <label>Amount<input type="number" name="amount" value="{{ old('amount') }}" min="0.01" step="0.01" required></label>
                <label>Date<input type="date" name="transfer_date" value="{{ old('transfer_date', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required></label>
                <label class="bank-span-full">Reason / reference<input name="note" value="{{ old('note') }}" maxlength="255" required placeholder="e.g. Cash withdrawal for shop expenses"></label>
                <p class="bank-span-full bank-form-note">A transfer moves existing money; it is not income or an expense. Both accounts record the reason. Balances may go below zero.</p><button class="bank-span-full bank-button bank-button-primary">Record Transfer</button>
            </form>
        </details>
        @if($account !== 'unassigned')
        <details id="opening-balance" class="bank-panel bank-tool" @if(old('opening_balance') !== null) open @endif>
            <summary><span class="bank-account-icon"><i class="fas fa-sliders-h" aria-hidden="true"></i></span><span><strong>Opening balance</strong><span class="bank-muted">Set the starting balance for this account</span></span><i class="fas fa-chevron-down bank-tool-chevron" aria-hidden="true"></i></summary>
            <form method="POST" action="{{ $bank ? route('accounting.banks.opening', $bank) : route('accounting.cash.opening') }}">@csrf
                <p class="bank-span-full bank-form-note">For {{ $accountName }}. This is the account's starting money, not its current balance. Use a transfer or payment for later activity. Corrections stay in history.</p>
                <label>Opening balance<input name="opening_balance" type="number" step="0.01" value="{{ old('opening_balance', number_format($effectiveOpening, 2, '.', '')) }}" required></label>
                <label>Effective date<input name="date" type="date" value="{{ old('date', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required></label>
                <label class="bank-span-full">Reason<input name="reason" value="{{ old('reason') }}" required maxlength="200" placeholder="e.g. Starting balance from bank statement"></label>
                <button class="bank-span-full bank-button bank-button-primary">Save Opening Balance</button>
            </form>
        </details>
        @endif
    </div>
    @endif
</div>
@push('scripts')
<script>
document.querySelectorAll('[data-bank-open]').forEach(button => button.addEventListener('click', () => {
    const panel = document.getElementById(button.dataset.bankOpen);
    if (!panel) return;
    panel.open = true;
    panel.scrollIntoView({behavior: 'smooth', block: 'center'});
    panel.querySelector('select, input:not([type="hidden"])')?.focus({preventScroll: true});
}));
</script>
@endpush
@include('exports.supplement')
@endsection
