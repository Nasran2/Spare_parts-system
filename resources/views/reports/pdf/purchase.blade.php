<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Purchase Report</title>
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
            <div class="title">Purchase Report</div>
            <div class="meta">Date Range: {{ $from ?? '—' }} to {{ $to ?? '—' }}</div>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>PO #</th>
                    <th>Supplier</th>
                    <th class="right">Total</th>
                    <th class="right">Paid</th>
                    <th class="right">Due</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($purchases as $p)
                <tr>
                    <td>{{ optional($p->purchase_date)->toDateString() }}</td>
                    <td>{{ $p->purchase_no }}</td>
                    <td>{{ $p->supplier?->name ?? 'N/A' }}</td>
                    <td class="right">{{ number_format($p->total_amount, 2) }}</td>
                    <td class="right">{{ number_format($p->paid_amount, 2) }}</td>
                    <td class="right">{{ number_format($p->due_amount, 2) }}</td>
                    <td>{{ ucfirst($p->payment_status) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="summary">
            <strong>Total Purchases:</strong> {{ number_format($summary['total_purchases'], 2) }} —
            <strong>Paid:</strong> {{ number_format($summary['total_paid'], 2) }} —
            <strong>Due:</strong> {{ number_format($summary['total_due'], 2) }} —
            <strong>Orders:</strong> {{ $summary['count'] }}
        </div>
    </div>
</body>
</html>
