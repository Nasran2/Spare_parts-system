@extends('layouts.app')
@section('title', 'Daily Ledger')
@section('page-title', 'Daily Ledger')
@section('content')
@php
    $maskMoney = fn ($value) => \App\Services\PrivacyModeService::isActiveForUser(auth()->user()) && \App\Services\PrivacyModeService::shouldMaskForCurrentPage()
        ? \App\Services\PrivacyModeService::maskAmount((float)$value) : number_format((float)$value, 2);
    $sectionVisuals = [
        'main' => ['tone' => 'blue', 'icon' => 'fa-wallet'],
        'cheques' => ['tone' => 'violet', 'icon' => 'fa-money-check-alt'],
        'petty' => ['tone' => 'amber', 'icon' => 'fa-cash-register'],
        'sales' => ['tone' => 'emerald', 'icon' => 'fa-receipt'],
    ];
    $overviewCards = [
        ['label' => 'Main Account (Cash)', 'value' => $ledgerSections['main']['closing'], 'tone' => 'blue', 'icon' => 'fa-wallet'],
        ['label' => 'Customer Cheques Balance', 'value' => $ledgerSections['cheques']['closing'], 'tone' => 'violet', 'icon' => 'fa-money-check-alt'],
        ['label' => 'Petty Cash Balance', 'value' => $ledgerSections['petty']['closing'], 'tone' => 'amber', 'icon' => 'fa-cash-register'],
        ['label' => 'Total Sales Value', 'value' => $salesTotals['total'], 'tone' => 'indigo', 'icon' => 'fa-chart-line'],
        ['label' => 'Sales-Related Receipts / Cheque Holds', 'value' => $salesReceipts, 'tone' => 'teal', 'icon' => 'fa-hand-holding-usd'],
        ['label' => 'Period Expenses (Cash / Petty Cash)', 'value' => $periodExpenses, 'tone' => 'rose', 'icon' => 'fa-arrow-up-from-bracket'],
    ];
    $salesVisuals = [
        'cash' => ['tone' => 'emerald', 'icon' => 'fa-coins'],
        'cheque' => ['tone' => 'violet', 'icon' => 'fa-money-check-alt'],
        'credit' => ['tone' => 'amber', 'icon' => 'fa-clock'],
        'other' => ['tone' => 'blue', 'icon' => 'fa-university'],
        'total' => ['tone' => 'indigo', 'icon' => 'fa-chart-line'],
        'count' => ['tone' => 'slate', 'icon' => 'fa-layer-group'],
    ];
@endphp
<link rel="stylesheet" href="{{ asset('css/daily-ledger.css') }}?v=20261017-colors">
<div class="daily-ledger">
    <form method="GET" class="dl-toolbar" id="daily-ledger-filters">
        <div><label for="dl-from">From Date</label><input id="dl-from" type="date" name="from" value="{{ $from }}" required></div>
        <div><label for="dl-to">To Date</label><input id="dl-to" type="date" name="to" value="{{ $to }}" required></div>
        @include('partials.quick-date-filter', ['fromName' => 'from', 'toName' => 'to', 'labelClass' => 'text-sm font-medium text-gray-600', 'selectClass' => 'mt-1 border rounded px-3 py-2 text-sm'])
        <div class="dl-toolbar-actions"><button class="dl-button dl-blue">Filter</button><a href="{{ route('reports.daily-ledger') }}" class="dl-button">Reset</a><button type="button" class="dl-button dl-green" data-ledger-export="excel"><i class="fas fa-file-excel" aria-hidden="true"></i>Excel</button><button type="button" class="dl-button dl-blue" data-ledger-export="pdf"><i class="fas fa-file-pdf" aria-hidden="true"></i>PDF</button></div>
    </form>
    <p class="dl-period">Report period: <strong>{{ $from }}</strong> to <strong>{{ $to }}</strong> · {{ $currency }} · Balances shown at the end of this period.</p>
    <div class="dl-overview">
        @foreach($overviewCards as $card)
        <div class="dl-stat dl-tone-{{ $card['tone'] }}">
            <div class="dl-stat-head"><p>{{ $card['label'] }}</p><span class="dl-stat-icon"><i class="fas {{ $card['icon'] }}" aria-hidden="true"></i></span></div>
            <strong class="{{ $card['value'] < 0 ? 'dl-out' : '' }}">{{ $maskMoney($card['value']) }}</strong>
        </div>
        @endforeach
    </div>
    <section class="dl-section dl-tone-emerald" id="daily-sales-transactions" aria-labelledby="dl-sales-title">
        <div class="dl-section-heading"><h3 id="dl-sales-title"><span class="dl-section-icon"><i class="fas fa-receipt" aria-hidden="true"></i></span>Sales Transactions</h3><span>{{ $salesTotals['count'] }} {{ \Illuminate\Support\Str::plural('transaction', $salesTotals['count']) }}</span></div>
        <div class="dl-sales-totals">
            @foreach(['cash' => 'Total Cash Sales', 'cheque' => 'Total Cheque Sales', 'credit' => 'Credit Sales', 'other' => 'Bank / Other Payments', 'total' => 'Total Sales Value', 'count' => 'Total Transactions'] as $key => $label)
            <div class="dl-stat dl-tone-{{ $salesVisuals[$key]['tone'] }}">
                <div class="dl-stat-head"><p>{{ $label }}</p><span class="dl-stat-icon"><i class="fas {{ $salesVisuals[$key]['icon'] }}" aria-hidden="true"></i></span></div>
                <strong>{{ $key === 'count' ? $salesTotals[$key] : $maskMoney($salesTotals[$key]) }}</strong>
            </div>
            @endforeach
        </div>
        <p class="dl-sales-note">Invoices dated within this period, excluding quotations. Cash, cheque, bank/other and credit amounts split each invoice as at {{ $to }}. Cheques include held and cleared cheques once; credit is the remaining unpaid amount. Bank/other includes transfers, cards and advance credit used.</p>
        @include('reports.partials.daily_sales_table', ['pdf' => false])
    </section>
    @foreach($ledgerSections as $key => $section)
    <section class="dl-section dl-tone-{{ $sectionVisuals[$key]['tone'] }}" aria-labelledby="dl-{{ $key }}-title">
        <div class="dl-section-heading"><h3 id="dl-{{ $key }}-title"><span class="dl-section-icon"><i class="fas {{ $sectionVisuals[$key]['icon'] }}" aria-hidden="true"></i></span>{{ $sectionLabels[$key] }}</h3><span>{{ $section['rows']->count() }} entries</span></div>
        <div class="dl-section-summary">
            @foreach(['opening' => 'Balance Brought Forward', 'in' => 'Money In', 'out' => 'Money Out', 'closing' => 'Balance Carried Forward'] as $field => $label)<div class="dl-summary-{{ $field }}"><p>{{ $label }}</p><strong class="{{ $section[$field] < 0 ? 'dl-out' : '' }}">{{ $maskMoney($section[$field]) }}</strong></div>@endforeach
        </div>
        @include('reports.partials.daily_ledger_table', ['pdf' => false])
    </section>
    @endforeach
</div>
<dialog id="daily-ledger-export-dialog" class="dl-dialog" aria-labelledby="dl-export-title">
    <form id="daily-ledger-export-form" method="GET" data-pdf-url="{{ route('reports.daily-ledger.pdf') }}" data-excel-url="{{ route('reports.daily-ledger.excel') }}">
        <header><div><h3 id="dl-export-title">Export Daily Ledger</h3><p>Choose the sections to include in your download.</p></div><button type="button" data-ledger-close aria-label="Close"><i class="fas fa-times" aria-hidden="true"></i></button></header>
        <div class="dl-dialog-body">
            <p>From <strong>{{ $from }}</strong> to <strong>{{ $to }}</strong></p>
            <input type="hidden" name="from" value="{{ $from }}"><input type="hidden" name="to" value="{{ $to }}"><input type="hidden" name="section_selection" value="1">
            <div class="dl-selection-actions"><button type="button" data-ledger-select-all>Select all</button><button type="button" data-ledger-clear-all>Clear selection</button></div>
            <fieldset><legend class="sr-only">Sections to export</legend>
                @foreach($sectionLabels as $key => $label)<label class="dl-section-choice dl-tone-{{ $sectionVisuals[$key]['tone'] }}"><input type="checkbox" name="sections[]" value="{{ $key }}" checked><span class="dl-choice-icon"><i class="fas {{ $sectionVisuals[$key]['icon'] }}" aria-hidden="true"></i></span><span>{{ $label }}</span></label>@endforeach
            </fieldset>
            <p id="dl-export-help">All sections are selected by default. Uncheck any sections you do not need.</p>
            <p id="dl-export-error" class="dl-export-error" role="alert" hidden>Select at least one section.</p>
        </div>
        <footer><button type="button" class="dl-button" data-ledger-close>Cancel</button><button type="submit" id="dl-export-submit" class="dl-button dl-blue">Download PDF</button></footer>
    </form>
</dialog>
@push('scripts')
<script src="{{ asset('js/daily-ledger.js') }}?v=20261017"></script>
@endpush
@endsection
