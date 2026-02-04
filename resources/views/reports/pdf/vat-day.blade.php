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
                    <td class="right">{{ $i['quantity'] }}</td>
                    <td class="right">{{ number_format($i['unit_price'], 2) }}</td>
                    <td class="right">{{ number_format($i['line_total'], 2) }}</td>
                    <td class="right">{{ number_format($i['vat_exclusive'], 2) }}</td>
                    <td class="right">{{ number_format($i['vat_inclusive'], 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="summary">
            <strong>Sales Total:</strong> {{ number_format($totals['line_total'], 2) }} —
            <strong>VAT (Exclusive):</strong> {{ number_format($totals['vat_exclusive'], 2) }} —
            <strong>VAT (Inclusive):</strong> {{ number_format($totals['vat_inclusive'], 2) }}
        </div>
    </div>
</body>
</html>
