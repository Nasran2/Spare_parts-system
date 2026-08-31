<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        .header { text-align: center; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ccc; padding: 6px; text-align: left; }
        th { background-color: #f3f4f6; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        .summary-box { border: 1px solid #ccc; padding: 10px; margin-top: 20px; width: 40%; float: right; }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 5px; }
        .clear { clear: both; }
    </style>
</head>
<body>
    <div class="header">
        <h2>{{ $title }}</h2>
        <p>Customer: {{ $customer->name }}</p>
        <p>Date Range: {{ \Carbon\Carbon::parse($start)->format('m/d/Y') }} - {{ \Carbon\Carbon::parse($end)->format('m/d/Y') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Reference No</th>
                <th>Type</th>
                <th>Payment Status</th>
                <th class="text-right">Debit</th>
                <th class="text-right">Credit</th>
                <th>Others</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transactions as $t)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($t['date'])->format('m/d/Y') }}</td>
                    <td>{{ $t['reference'] }}</td>
                    <td>{{ $t['type'] }}</td>
                    <td>{{ ucfirst($t['payment_status'] ?? '') }}</td>
                    <td class="text-right">{{ $t['debit'] ? $t['debit'] : '-' }}</td>
                    <td class="text-right">{{ $t['credit'] ? $t['credit'] : '-' }}</td>
                    <td>{{ $t['notes'] ?: '-' }}</td>
                </tr>
            @endforeach
            @if(count($transactions) === 0)
                <tr>
                    <td colspan="7" class="text-center">No transactions found.</td>
                </tr>
            @endif
        </tbody>
    </table>

    <div class="summary-box">
        <div class="summary-row">
            <span>Total Invoice:</span>
            <span class="text-right font-bold">{{ config('app.currency') }} {{ $overallTotals['invoice'] ?? 0 }}</span>
        </div>
        <div class="summary-row">
            <span>Total Paid:</span>
            <span class="text-right font-bold">{{ config('app.currency') }} {{ $overallTotals['paid'] ?? 0 }}</span>
        </div>
        @if(isset($overallTotals['sales_due']) && $overallTotals['sales_due'] > 0)
        <div class="summary-row">
            <span style="color:#f97316;">Sales Due:</span>
            <span class="text-right font-bold" style="color:#ea580c;">{{ config('app.currency') }} {{ $overallTotals['sales_due'] }}</span>
        </div>
        @endif
        @if(isset($overallTotals['pre_order_due']) && $overallTotals['pre_order_due'] > 0)
        <div class="summary-row">
            <span style="color:#a855f7;">Pre-Order Due:</span>
            <span class="text-right font-bold" style="color:#9333ea;">{{ config('app.currency') }} {{ $overallTotals['pre_order_due'] }}</span>
        </div>
        @endif
        <div class="summary-row">
            <span>Balance Due:</span>
            <span class="text-right font-bold">{{ config('app.currency') }} {{ $overallTotals['balance'] ?? 0 }}</span>
        </div>
    </div>
    <div class="clear"></div>
</body>
</html>
