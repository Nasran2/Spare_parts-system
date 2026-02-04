<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Profit & Loss Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; }
        .container { width: 100%; padding: 16px; }
        .header { text-align: center; margin-bottom: 12px; }
        .title { font-size: 18px; font-weight: bold; }
        .meta { font-size: 12px; color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ddd; padding: 6px; font-size: 12px; }
        th { background: #f3f3f3; text-align: left; }
        .right { text-align: right; }
    </style>
</head>
<body>
    <div class="container">
        @php
            $businessName = \App\Models\Setting::get('shop_name') ?? \App\Models\Setting::get('business_name') ?? config('app.name', 'Vehicle POS');
            $businessAddress = \App\Models\Setting::get('shop_address') ?? \App\Models\Setting::get('business_address') ?? '';
            $businessPhone = \App\Models\Setting::get('shop_phone') ?? \App\Models\Setting::get('business_phone') ?? '';
        @endphp
        <div style="text-align:center; margin-bottom:8px;">
            <div style="font-size:20px; font-weight:bold;">{{ $businessName }}</div>
            <div class="meta">{{ $businessAddress }} @if($businessPhone) • {{ $businessPhone }} @endif</div>
            <hr>
        </div>
        <div class="header">
            <div class="title">Profit & Loss Report</div>
            <div class="meta">Date Range: {{ $from ?? '—' }} to {{ $to ?? '—' }}</div>
        </div>
        <table>
            <tbody>
                <tr>
                    <th>Sales Revenue</th>
                    <td class="right">{{ number_format($summary['salesRevenue'], 2) }}</td>
                </tr>
                <tr>
                    <th>COGS</th>
                    <td class="right">{{ number_format($summary['cogs'], 2) }}</td>
                </tr>
                <tr>
                    <th>Gross Profit</th>
                    <td class="right">{{ number_format($summary['grossProfit'], 2) }}</td>
                </tr>
                <tr>
                    <th>Expenses</th>
                    <td class="right">{{ number_format($summary['expenseTotal'], 2) }}</td>
                </tr>
                <tr>
                    <th>Net Profit</th>
                    <td class="right">{{ number_format($summary['netProfit'], 2) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</body>
</html>
