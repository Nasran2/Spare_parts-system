<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Customer Due Report</title>
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
        .summary { margin-top: 12px; font-size: 12px; }
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
            <div class="title">Customer Due Report</div>
            <div class="meta">Date Range: {{ $from ?? '—' }} to {{ $to ?? '—' }}</div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Phone</th>
                    <th>Invoices</th>
                    <th class="right">Due Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $row)
                <tr>
                    <td>{{ $row['name'] }}</td>
                    <td>{{ $row['phone'] ?? '-' }}</td>
                    <td>{{ $row['invoices'] }}</td>
                    <td class="right">{{ number_format($row['due'], 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="summary">
            <strong>Total Due:</strong> {{ number_format($summary['total_due'], 2) }} —
            <strong>Customers:</strong> {{ $summary['customers'] }}
        </div>
    </div>
</body>
</html>
