<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #111; }
        .header { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; }
        .title { font-size:20px; font-weight:700; }
        .chip { padding:6px 10px; border-radius:8px; background:#eef; font-size:12px; }
        table { width:100%; border-collapse:collapse; }
        th, td { padding:8px; border-bottom:1px solid #ddd; font-size:12px; }
        th { background:#f7f7f7; text-align:left; }
        .summary { display:grid; grid-template-columns:repeat(4,1fr); gap:8px; margin-bottom:12px; }
        .card { padding:10px; border:1px solid #eee; border-radius:8px; }
        .label { font-size:11px; color:#666; }
        .value { font-size:14px; font-weight:700; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">VAT Report</div>
        <div class="chip">Period: {{ $from ?? 'All' }} - {{ $to ?? 'All' }}</div>
    </div>
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
    <div class="summary">
        <div class="card"><div class="label">VAT Enabled</div><div class="value">{{ ($summary['enabled'] ?? false) ? 'Yes' : 'No' }}</div></div>
        <div class="card"><div class="label">VAT Rate</div><div class="value">{{ number_format($summary['rate'] ?? 0, 2) }}%</div></div>
        <div class="card"><div class="label">Total Sales</div><div class="value">{{ number_format($summary['totalFinal'] ?? 0, 2) }}</div></div>
        <div class="card"><div class="label">VAT (Exclusive)</div><div class="value">{{ number_format($summary['vatExclusive'] ?? 0, 2) }}</div></div>
        <div class="card"><div class="label">VAT (Inclusive)</div><div class="value">{{ number_format($summary['vatInclusive'] ?? 0, 2) }}</div></div>
    </div>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Sales Total</th>
                <th>VAT (Exclusive)</th>
                <th>VAT (Inclusive)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($daily as $d)
                <tr>
                    <td>{{ $d['date'] }}</td>
                    <td>{{ number_format($d['final_total'], 2) }}</td>
                    <td>{{ number_format($d['vat_exclusive'], 2) }}</td>
                    <td>{{ number_format($d['vat_inclusive'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
