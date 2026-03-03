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
            <strong>Low Stock:</strong> {{ $summary['low_stock'] ?? 0 }} —
            <strong>Total Units In Stock:</strong> {{ $summary['total_stock'] ?? 0 }}
            <br>
            <strong>Total Cost Price:</strong> {{ number_format($summary['total_cost_value'] ?? 0, 2) }} —
            <strong>Total Selling Price:</strong> {{ number_format($summary['total_selling_value'] ?? 0, 2) }} —
            <strong>After Sale Profit (Selling - Cost):</strong> {{ number_format($summary['expected_profit'] ?? 0, 2) }}
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
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $row)
                    <tr>
                        <td>{{ $row['name'] }}</td>
                        <td>{{ $row['categories'] ?: '-' }}</td>
                        <td>{{ $row['brand'] ?? '-' }}</td>
                        <td style="text-align:right;">{{ number_format((float) ($row['cost_price'] ?? 0), 2) }}</td>
                        <td style="text-align:right;">{{ number_format((float) ($row['selling_price'] ?? 0), 2) }}</td>
                        <td style="text-align:right;">{{ $row['purchased'] }}</td>
                        <td style="text-align:right;">{{ $row['sold'] }}</td>
                        <td style="text-align:right;">{{ $row['current_stock'] }}</td>
                        <td class="{{ !empty($row['low_stock']) ? 'status-low' : 'status-ok' }}">{{ !empty($row['low_stock']) ? 'Low' : 'OK' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" style="text-align:center; color:#777;">No product data.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</body>
</html>
