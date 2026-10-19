<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>Daily Ledger</title>
<style>
    @page { margin: 28px 26px 38px; }
    body {font-family:DejaVu Sans,sans-serif;font-size:9px;color:#172033}
    h3 {font-size:14px;margin:20px 0 10px;padding-bottom:6px;border-bottom:1px solid #cbd5e1}
    table {width:100%;border-collapse:collapse;table-layout:fixed}
    .dl-table th,.dl-table td {border:1px solid #d1d5db;padding:6px 5px;vertical-align:top;word-wrap:break-word;font-size:8px}
    .dl-table th {font-size:7px;text-align:left}
    .dl-table .dl-money {text-align:right;white-space:nowrap}
    .ledger-table th:nth-child(1){width:8%}.ledger-table th:nth-child(2){width:10%}.ledger-table th:nth-child(3){width:10%}.ledger-table th:nth-child(4){width:10%}.ledger-table th:nth-child(5){width:10%}.ledger-table th:nth-child(6){width:22%}.ledger-table th:nth-child(7),.ledger-table th:nth-child(8),.ledger-table th:nth-child(9){width:10%}
    .sales-table th:nth-child(1){width:9%}.sales-table th:nth-child(2){width:14%}.sales-table th:nth-child(3){width:22%}.sales-table th:nth-child(n+4){width:11%}
    .dl-table thead {display:table-header-group}.dl-table tr{page-break-inside:avoid}.dl-balance td{background:#edf2f7;font-weight:bold}.dl-empty{text-align:center;padding:18px!important;color:#667085}
    .dl-in{color:#15803d}.dl-out{color:#b91c1c}.summary td{border:1px solid #e2e8f0;background:#f8fafc;padding:9px;font-size:9px}.summary strong{display:block;margin-top:3px;font-size:11px}.note{font-size:8px;color:#667085;line-height:1.6;margin:8px 0 12px}
    .page-footer{position:fixed;bottom:-22px;left:0;right:0;text-align:right;color:#667085;font-size:8px}.page-number:after{content:counter(page)}
</style></head>
<body>
@php $maskMoney = fn ($value) => \App\Services\PrivacyModeService::isActiveForUser(auth()->user()) && \App\Services\PrivacyModeService::shouldMaskForCurrentPage() ? \App\Services\PrivacyModeService::maskAmount((float)$value) : number_format((float)$value, 2); @endphp
@include('pdf.partials.letterhead', ['documentTitle' => 'Daily Ledger', 'documentMeta' => ['From Date' => $from, 'To Date' => $to, 'Currency' => $currency]])
@foreach($selectedSections as $key)
@if(!$loop->first)<div style="page-break-before: always;"></div>@endif
<h3>{{ $sectionLabels[$key] }}</h3>
@if($key === 'sales')
    <table class="summary"><tr>@foreach(['cash' => 'Total Cash Sales', 'cheque' => 'Total Cheque Sales', 'credit' => 'Credit Sales'] as $field => $label)<td>{{ $label }}<strong>{{ $maskMoney($salesTotals[$field]) }}</strong></td>@endforeach</tr><tr><td>Bank / Other Payments<strong>{{ $maskMoney($salesTotals['other']) }}</strong></td><td>Total Sales Value<strong>{{ $maskMoney($salesTotals['total']) }}</strong></td><td>Total Transactions<strong>{{ $salesTotals['count'] }}</strong></td></tr></table>
    <p class="note">Sales dated within this period, excluding quotations. Amounts split each invoice as at {{ $to }}; credit is unpaid, cheques include held and cleared cheques once, and bank/other includes transfers, cards and advance credit used. These are sales classifications, not additional cash movements.</p>
    @include('reports.partials.daily_sales_table', ['pdf' => true])
@else
    @php $section = $ledgerSections[$key]; @endphp
    <table class="summary"><tr>@foreach(['opening' => 'Balance Brought Forward', 'in' => 'Money In', 'out' => 'Money Out', 'closing' => 'Balance Carried Forward'] as $field => $label)<td>{{ $label }}<strong>{{ $maskMoney($section[$field]) }}</strong></td>@endforeach</tr></table>
    <p class="note">Sales-related receipts / cheque holds: {{ $maskMoney($section['revenue']) }} | Period expenses: {{ $maskMoney($section['expenses']) }}</p>
    @include('reports.partials.daily_ledger_table', ['pdf' => true])
@endif
@endforeach
<div class="page-footer">Daily Ledger | {{ $from }} to {{ $to }} | Page <span class="page-number"></span></div>
</body></html>
