<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Stock Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; }
        .container { width: 100%; padding: 16px; }
        .header { text-align: center; margin-bottom: 12px; }
        .title { font-size: 18px; font-weight: bold; }
        .meta { font-size: 12px; color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ddd; padding: 6px; font-size: 12px; }
        th { background: #f3f3f3; text-align: left; }
        .status-ok { color: #0a7; }
        .status-low { color: #c00; }
        .summary { margin-top: 12px; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        @php
            $controls = is_array($controls ?? null) ? $controls : [];
            $priceVisiblePct = (float) ($controls['price_visible_percentage'] ?? 100);
            $profitVisiblePct = (float) ($controls['profit_visible_percentage'] ?? 100);
            $qtyVisiblePct = (float) ($controls['qty_visible_percentage'] ?? 100);
            $stockVisiblePct = (float) ($controls['stock_visible_percentage'] ?? 100);
            $inventoryQtyPct = min($qtyVisiblePct, $stockVisiblePct);
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
            $maskInventoryQty = function ($value, $forceHide = false) use ($controls, $inventoryQtyPct, $applyPct) {
                if ($forceHide || !empty($controls['hide_qty_wise_data'])) {
                    return '—';
                }

                return number_format(round($applyPct((float) $value, $inventoryQtyPct)), 0);
            };
            $maskStockQty = function ($value, $forceHide = false) use ($controls, $inventoryQtyPct, $applyPct) {
                if ($forceHide || !empty($controls['hide_qty_wise_data']) || !empty($controls['hide_actual_stock_quantity'])) {
                    return '—';
                }

                return number_format(round($applyPct((float) $value, $inventoryQtyPct)), 0);
            };
            $maskStockMoney = function ($value, $forceHide = false) use ($controls, $inventoryQtyPct, $priceVisiblePct, $applyPct) {
                if ($forceHide || !empty($controls['hide_stock_values']) || !empty($controls['hide_price_wise_data'])) {
                    return '—';
                }

                $stockAdjusted = $applyPct((float) $value, $inventoryQtyPct);
                $masked = $applyPct($stockAdjusted, $priceVisiblePct);
                $roundToWhole = $inventoryQtyPct < 100 || $priceVisiblePct < 100;

                return number_format($roundToWhole ? round($masked) : $masked, $roundToWhole ? 0 : 2);
            };
            $maskProfitMoney = function ($value, $forceHide = false) use ($controls, $profitVisiblePct, $applyPct) {
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
            <div class="title">Stock Report</div>
        </div>
        <div class="summary">
            <strong>Total Products:</strong> {{ $summary['total_products'] ?? 0 }} —
            <strong>Low Stock:</strong> {{ !empty($controls['hide_actual_stock_count']) ? '—' : ($summary['low_stock'] ?? 0) }} —
            <strong>Total Units In Stock:</strong> {{ $maskStockQty(($summary['total_stock'] ?? 0), !empty($controls['hide_actual_stock_quantity'])) }}
            <br>
            <strong>Total Cost Price:</strong> {{ $maskStockMoney(($summary['total_cost_value'] ?? 0), !empty($controls['hide_stock_values']) || !empty($controls['hide_actual_stock_price'])) }} —
            <strong>Total Selling Price:</strong> {{ $maskStockMoney(($summary['total_selling_value'] ?? 0), !empty($controls['hide_stock_values']) || !empty($controls['hide_actual_stock_price'])) }} —
            <strong>After Sale Profit (Selling - Cost):</strong> {{ $maskProfitMoney(($summary['expected_profit'] ?? 0), !empty($controls['hide_stock_values']) || !empty($controls['hide_profit_loss'])) }}
        </div>

        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Category</th>
                    <th>Brand</th>
                    <th style="text-align:right;">Cost Price</th>
                    <th style="text-align:right;">Selling Price</th>
                    <th style="text-align:right;">Purchased Qty</th>
                    <th style="text-align:right;">Sold Qty</th>
                    <th style="text-align:right;">Current Stock</th>
                    <th style="text-align:right;">Total Cost</th>
                    <th style="text-align:right;">Total Selling</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $row)
                    <tr>
                        <td>{{ !empty($controls['hide_product_wise_data']) ? 'Hidden Product' : $row['name'] }}</td>
                        <td>{{ $row['categories'] ?: '-' }}</td>
                        <td>{{ $row['brand'] ?? '-' }}</td>
                        <td style="text-align:right;">{{ $maskMoney(($row['cost_price'] ?? 0), !empty($controls['hide_actual_purchase_price']) || !empty($controls['hide_actual_stock_price'])) }}</td>
                        <td style="text-align:right;">{{ $maskMoney(($row['selling_price'] ?? 0), !empty($controls['hide_actual_stock_price'])) }}</td>
                        <td style="text-align:right;">{{ $maskInventoryQty($row['purchased']) }}</td>
                        <td style="text-align:right;">{{ $maskInventoryQty($row['sold']) }}</td>
                        <td style="text-align:right;">{{ $maskStockQty($row['current_stock'], !empty($controls['hide_actual_stock_quantity'])) }}</td>
                        @php
                            $pLineCost    = (float) ($row['cost_price'] ?? 0) * max(0, (int) ($row['current_stock'] ?? 0));
                            $pLineSelling = (float) ($row['selling_price'] ?? 0) * max(0, (int) ($row['current_stock'] ?? 0));
                        @endphp
                        <td style="text-align:right;">{{ $maskStockMoney($pLineCost, !empty($controls['hide_stock_values']) || !empty($controls['hide_actual_stock_price'])) }}</td>
                        <td style="text-align:right;">{{ $maskStockMoney($pLineSelling, !empty($controls['hide_stock_values']) || !empty($controls['hide_actual_stock_price'])) }}</td>
                        <td class="{{ !empty($row['low_stock']) ? 'status-low' : 'status-ok' }}">{{ !empty($row['low_stock']) ? 'Low' : 'OK' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" style="text-align:center; color:#777;">No product data.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</body>
</html>
