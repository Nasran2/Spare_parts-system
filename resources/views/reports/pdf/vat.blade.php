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
    @php
        $controls = \App\Services\DashboardVisibilityService::configForUser(auth()->user());
        $priceVisiblePct = (float) ($controls['price_visible_percentage'] ?? 100);
        $applyPct = function ($value, $pct) {
            $pct = max(0, min(100, (float) $pct));
            return (float) $value * ($pct / 100);
        };
        $maskMoney = function ($value, $forceHide = false) use ($controls, $priceVisiblePct, $applyPct) {
        if (\App\Services\PrivacyModeService::isActiveForUser(auth()->user()) && \App\Services\PrivacyModeService::shouldMaskForCurrentPage()) {
            return \App\Services\PrivacyModeService::maskAmount((float) $value);
        }
            if ($forceHide || !empty($controls['hide_price_wise_data'])) {
                return '—';
            }

            $masked = $applyPct((float) $value, $priceVisiblePct);

            $roundToWhole = $priceVisiblePct < 100;


            return number_format($roundToWhole ? round($masked) : $masked, $roundToWhole ? 0 : 2);
        };
    @endphp
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
        <div class="card"><div class="label">Total Sales</div><div class="value">{{ $maskMoney(($summary['totalFinal'] ?? 0), !empty($controls['hide_total_sales'])) }}</div></div>
        <div class="card"><div class="label">VAT (Exclusive)</div><div class="value">{{ $maskMoney(($summary['vatExclusive'] ?? 0)) }}</div></div>
        <div class="card"><div class="label">VAT (Inclusive)</div><div class="value">{{ $maskMoney(($summary['vatInclusive'] ?? 0)) }}</div></div>
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
                    <td>{{ $maskMoney($d['final_total'], !empty($controls['hide_total_sales'])) }}</td>
                    <td>{{ $maskMoney($d['vat_exclusive']) }}</td>
                    <td>{{ $maskMoney($d['vat_inclusive']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
