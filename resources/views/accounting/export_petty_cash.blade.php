<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 30px 30px 38px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #172033; }
        table.statement { width: 100%; table-layout: fixed; border-collapse: collapse; }
        .statement th, .statement td { padding: 8px 6px; border: 1px solid #d7dde5; vertical-align: top; word-wrap: break-word; }
        .statement th { text-align: left; font-size: 8px; }
        .statement th.amount, .statement td.amount { text-align: right; white-space: nowrap; }
        .statement thead { display: table-header-group; }
        .statement tr { page-break-inside: avoid; }
        .statement .balance-row td { background: #edf2f7; font-weight: bold; }
        .statement .closing-row td { background: #e6edf5; font-weight: bold; border-top: 2px solid #64748b; }
        .statement .totals-row td { font-weight: bold; background: #fafafa; }
        .details { color: #667085; font-size: 7px; margin-top: 3px; }
        .negative { color: #b42318; }
        .note { color: #667085; font-size: 8px; line-height: 1.6; margin: 12px 0; }
        .empty { padding: 18px !important; text-align: center; color: #667085; }
        .page-footer { position: fixed; bottom: -22px; left: 0; right: 0; font-size: 8px; color: #667085; text-align: right; }
        .page-number:after { content: counter(page); }
    </style>
</head>
<body>
    @include('pdf.partials.letterhead', [
        'documentTitle' => $title,
        'documentMeta' => [
            'From Date' => $from,
            'To Date' => $to,
            'Fund' => $fundLabel,
            'Currency' => $currency,
        ],
    ])
    @if($search || $category)
    <p class="note">Shown entries: @if($search) Reference / description containing "{{ $search }}".@endif @if($category) Category: {{ $category }}.@endif Balances and period totals include all movements for the selected funds and dates, including entries hidden by these filters.</p>
    @endif
    <table class="statement">
        <thead><tr>
            <th style="width:9%">Date</th>
            <th style="width:13%">Fund Account</th>
            <th style="width:12%">Reference / Voucher</th>
            <th style="width:30%">Description / Details</th>
            <th class="amount" style="width:11%">Money In</th>
            <th class="amount" style="width:11%">Money Out</th>
            <th class="amount" style="width:14%">Balance</th>
        </tr></thead>
        <tbody>
            <tr class="balance-row">
                <td>{{ $from }}</td>
                <td colspan="5">Balance Brought Forward <span class="details">(Opening balance before this period)</span></td>
                <td class="amount {{ $opening < 0 ? 'negative' : '' }}">{{ number_format($opening, 2) }}</td>
            </tr>
            @forelse($rows as $row)
            <tr>
                <td>{{ $row['date'] }}</td>
                <td>{{ $row['fund'] }}</td>
                <td>{{ $row['reference'] }}</td>
                <td>{{ $row['description'] }}<div class="details">{{ $row['details'] }}</div></td>
                <td class="amount">{{ $row['direction'] === 'in' ? number_format($row['amount'], 2) : '-' }}</td>
                <td class="amount">{{ $row['direction'] === 'out' ? number_format($row['amount'], 2) : '-' }}</td>
                <td class="amount {{ $row['balance'] < 0 ? 'negative' : '' }}">{{ number_format($row['balance'], 2) }}</td>
            </tr>
            @empty<tr><td colspan="7" class="empty">No transactions found for the selected period and filters.</td></tr>@endforelse
            <tr class="totals-row"><td colspan="4">Total Movements for the Period</td><td class="amount">{{ number_format($moneyIn, 2) }}</td><td class="amount">{{ number_format($moneyOut, 2) }}</td><td></td></tr>
            <tr class="closing-row"><td>{{ $to }}</td><td colspan="5">Balance Carried Forward <span class="details">(Closing balance for the next period)</span></td><td class="amount {{ $closing < 0 ? 'negative' : '' }}">{{ number_format($closing, 2) }}</td></tr>
        </tbody>
    </table>
    <p class="note">Balance Carried Forward = Balance Brought Forward + Money In - Money Out. Transactions are listed from oldest to newest. @if($fundLabel === 'All active petty cash funds')Balances combine all active petty cash funds.@endif</p>
    <div class="page-footer">Petty Cash Statement | Page <span class="page-number"></span></div>
</body>
</html>
