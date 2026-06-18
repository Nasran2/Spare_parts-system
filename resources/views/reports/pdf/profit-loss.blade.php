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
            $controls = \App\Services\DashboardVisibilityService::configForUser(auth()->user());
            $priceVisiblePct = (float) ($controls['price_visible_percentage'] ?? 100);
            $profitVisiblePct = (float) ($controls['profit_visible_percentage'] ?? 100);
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
            $maskProfitMoney = function ($value, $forceHide = false) use ($controls, $profitVisiblePct, $applyPct) {
        if (\App\Services\PrivacyModeService::isActiveForUser(auth()->user()) && \App\Services\PrivacyModeService::shouldMaskForCurrentPage()) {
            return \App\Services\PrivacyModeService::maskAmount((float) $value);
        }
                if ($forceHide || !empty($controls['hide_profit_loss'])) {
                    return '—';
                }

                $masked = $applyPct((float) $value, $profitVisiblePct);
                $roundToWhole = $profitVisiblePct < 100;

                return number_format($roundToWhole ? round($masked) : $masked, $roundToWhole ? 0 : 2);
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
            <div class="title">Profit & Loss Report</div>
            <div class="meta">Date Range: {{ $from ?? '—' }} to {{ $to ?? '—' }}</div>
        </div>
        <table>
            <tbody>
                <tr>
                    <th>Sales Revenue</th>
                    <td class="right">{{ $maskMoney($summary['salesRevenue'], !empty($controls['hide_total_sales'])) }}</td>
                </tr>
                <tr>
                    <th>COGS</th>
                    <td class="right">{{ $maskMoney($summary['cogs'], !empty($controls['hide_actual_purchase_price']) || !empty($controls['hide_actual_stock_price'])) }}</td>
                </tr>
                <tr>
                    <th>Gross Profit</th>
                    <td class="right">{{ $maskProfitMoney($summary['grossProfit'], !empty($controls['hide_profit_loss'])) }}</td>
                </tr>
                <tr>
                    <th>Expenses</th>
                    <td class="right">{{ $maskMoney($summary['expenseTotal']) }}</td>
                </tr>
                <tr>
                    <th>Net Profit</th>
                    <td class="right">{{ $maskProfitMoney($summary['netProfit'], !empty($controls['hide_profit_loss'])) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</body>
</html>
