<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Cheque Payments Report</title>
    <style>
        @page { margin: 24px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #d1d5db; padding: 6px; text-align: left; vertical-align: top; overflow-wrap: break-word; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        .right { text-align: right; }
        .muted { color: #6b7280; font-size: 9px; }
        .summary { margin-top: 12px; text-align: right; }
    </style>
</head>
<body>
    @include('pdf.partials.letterhead', [
        'documentTitle' => 'Cheque Payments Report',
        'documentMeta' => [
            'Period' => (($filters['date_from'] ?? '') ?: 'All').' to '.(($filters['date_to'] ?? '') ?: 'All'),
            'Status' => ucfirst(($filters['status'] ?? '') ?: 'All'),
            'Search' => ($filters['search'] ?? '') ?: 'All',
        ],
    ])
    <table>
        <thead>
            <tr>
                <th style="width:10%">Pass Date</th>
                <th style="width:19%">Cheque Details</th>
                <th style="width:21%">Party</th>
                <th style="width:17%">Reference</th>
                <th style="width:13%" class="right">Amount ({{ $currency }})</th>
                <th style="width:8%">Status</th>
                <th style="width:12%">Processed</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ $row['date'] }}</td>
                    <td><strong>{{ $row['cheque_number'] }}</strong><br>Bank: {{ $row['bank'] }}<br><span class="muted">Name: {{ $row['account_name'] }}</span></td>
                    <td>{{ $row['party'] }}<br><span class="muted">{{ $row['type'] }}</span>
                        @if($row['endorsed_to'] !== '-')<br><span class="muted">Endorsed to: {{ $row['endorsed_to'] }}</span>@endif
                    </td>
                    <td>{{ $row['reference'] }}</td>
                    <td class="right">{{ number_format($row['amount'], 2) }}</td>
                    <td>{{ $row['status'] }}@if($row['auto_passed'] === 'Yes')<br><span class="muted">Auto passed</span>@endif</td>
                    <td>{{ $row['processed_at'] }}</td>
                </tr>
            @empty
                <tr><td colspan="7" style="text-align:center">No cheque payments found.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="summary"><strong>Cheques:</strong> {{ $rows->count() }} &nbsp; | &nbsp; <strong>Total Amount:</strong> {{ $currency }} {{ number_format($rows->sum('amount'), 2) }}</div>
</body>
</html>
