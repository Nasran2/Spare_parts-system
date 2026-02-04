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
        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Category</th>
                    <th>Purchased Qty</th>
                    <th>Sold Qty</th>
                    <th>Current Stock</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $row)
                <tr>
                    <td>{{ $row['name'] }}</td>
                    <td>{{ $row['categories'] }}</td>
                    <td>{{ $row['purchased'] }}</td>
                    <td>{{ $row['sold'] }}</td>
                    <td>{{ $row['current_stock'] }}</td>
                    <td class="{{ $row['low_stock'] ? 'status-low' : 'status-ok' }}">{{ $row['low_stock'] ? 'Low' : 'OK' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="summary">
            <strong>Total Products:</strong> {{ $summary['total_products'] }} —
            <strong>Low Stock:</strong> {{ $summary['low_stock'] }} —
            <strong>Total Units In Stock:</strong> {{ $summary['total_stock'] }}
        </div>
    </div>
</body>
</html>
