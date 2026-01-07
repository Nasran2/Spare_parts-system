<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Sales Report</title>
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
            <div class="title">Sales Report</div>
            <div class="meta">Date Range: {{ $from ?? '—' }} to {{ $to ?? '—' }}</div>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Invoice</th>
                    <th>Customer</th>
                    <th class="right">Total</th>
                    <th class="right">Paid</th>
                    <th class="right">Due</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sales as $s)
                <tr>
                    <td>{{ optional($s->sale_date)->toDateString() }}</td>
                    <td>{{ $s->sale_no }}</td>
                    <td>{{ $s->customer?->name ?? 'Walk-in' }}</td>
                    <td class="right">{{ \App\Support\SecretPos::maskForSale($s->total_amount, $s->total_amount) }}</td>
                    <td class="right">{{ \App\Support\SecretPos::maskForSale($s->total_amount, $s->paid_amount) }}</td>
                    <td class="right">{{ \App\Support\SecretPos::maskForSale($s->total_amount, $s->due_amount) }}</td>
                    <td>{{ ucfirst($s->payment_status) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="summary">
            <strong>Total Sales:</strong> {{ number_format($summary['total_sales'], 2) }} —
            <strong>Paid:</strong> {{ number_format($summary['total_paid'], 2) }} —
            <strong>Due:</strong> {{ number_format($summary['total_due'], 2) }} —
            <strong>Invoices:</strong> {{ $summary['count'] }}
        </div>
    </div>
</body>
</html>
