<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>VAT Day Details</title>
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
            $controls = \App\Services\DashboardVisibilityService::configForUser(auth()->user());
            $priceVisiblePct = (float) ($controls['price_visible_percentage'] ?? 100);
            $qtyVisiblePct = (float) ($controls['qty_visible_percentage'] ?? 100);
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
            $maskQty = function ($value, $forceHide = false) use ($controls, $qtyVisiblePct, $applyPct) {
                if ($forceHide || !empty($controls['hide_qty_wise_data'])) {
                    return '—';
                }

                return number_format(round($applyPct((float) $value, $qtyVisiblePct)), 0);
            };
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
            <div class="title">VAT Details — {{ $date }}</div>
            <div class="meta">VAT Rate: {{ $enabled ? $rate.'%' : 'Disabled' }}</div>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Invoice</th>
                    <th>Product</th>
                    <th class="right">Qty</th>
                    <th class="right">Unit Price</th>
                    <th class="right">Line Total</th>
                    <th class="right">VAT (Exclusive)</th>
                    <th class="right">VAT (Inclusive)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lines as $i)
                <tr>
                    <td>{{ $i['invoice'] }}</td>
                    <td>{{ $i['product'] }}</td>
                    <td class="right">{{ $maskQty($i['quantity']) }}</td>
                    <td class="right">{{ $maskMoney($i['unit_price']) }}</td>
                    <td class="right">{{ $maskMoney($i['line_total'], !empty($controls['hide_total_sales'])) }}</td>
                    <td class="right">{{ $maskMoney($i['vat_exclusive']) }}</td>
                    <td class="right">{{ $maskMoney($i['vat_inclusive']) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="summary">
            <strong>Sales Total:</strong> {{ $maskMoney(($totals['line_total'] ?? 0), !empty($controls['hide_total_sales'])) }} —
            <strong>VAT (Exclusive):</strong> {{ $maskMoney(($totals['vat_exclusive'] ?? 0)) }} —
            <strong>VAT (Inclusive):</strong> {{ $maskMoney(($totals['vat_inclusive'] ?? 0)) }}
        </div>
    </div>
</body>
</html>
