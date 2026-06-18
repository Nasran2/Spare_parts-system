<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Sales Export</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; }
        .header { display:flex; align-items:center; gap:12px; border-bottom:1px solid #ccc; padding-bottom:8px; margin-bottom:12px; }
        .header img { max-height: 60px; max-width: 140px; object-fit: contain; }
        .shop h2 { margin:0; font-size: 18px; }
        .shop small { color:#555; }
        table { width:100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 6px; }
        th { background:#f2f2f2; text-align:left; }
        .right { text-align:right; }
        .center { text-align:center; }
    </style>
</head>
<body>
    @php
        $controls = is_array($controls ?? null) ? $controls : [];
        $priceVisiblePct = (float) ($controls['price_visible_percentage'] ?? 100);
        $applyPct = function ($value, $pct) {
            $pct = max(0, min(100, (float) $pct));
            return (float) $value * ($pct / 100);
        };
        $maskMoney = function ($value, $forceHide = false) use ($controls, $priceVisiblePct, $applyPct) {
            if ($forceHide || !empty($controls['hide_price_wise_data'])) {
                return '—';
            }
            $masked = $applyPct((float) $value, $priceVisiblePct);
            $roundToWhole = $priceVisiblePct < 100;

            return number_format($roundToWhole ? round($masked) : $masked, $roundToWhole ? 0 : 2);
        };
    @endphp
    <div class="header">
        @if(!empty($shop['logo']))
            <img src="{{ public_path($shop['logo']) }}" alt="Logo" />
        @endif
        <div class="shop">
            <h2>{{ $shop['name'] }}</h2>
            <div>
                <small>{{ $shop['address'] }}</small><br>
                @if($shop['phone'])<small>Tel: {{ $shop['phone'] }}</small><br>@endif
                @if($shop['email'])<small>Email: {{ $shop['email'] }}</small>@endif
            </div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Invoice</th>
                <th>Date</th>
                <th>Customer</th>
                <th class="right">Amount</th>
                <th class="center">Pay Status</th>
                <th class="center">Method</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sales as $sale)
                <tr>
                    <td>{{ !empty($controls['hide_invoice_details']) ? 'HIDDEN' : \App\Services\PrivacyModeService::displayInvoiceNumber($sale) }}</td>
                    <td>{{ optional($sale->sale_date)->format('Y-m-d') ?? $sale->created_at->format('Y-m-d') }}</td>
                    <td>{{ !empty($controls['hide_supplier_names']) ? 'Hidden' : ($sale->customer->name ?? 'Walk-in Customer') }}</td>
                    <td class="right">{{ $maskMoney($sale->total_amount, !empty($controls['hide_invoice_details'])) }}</td>
                    <td class="center">{{ ucfirst($sale->payment_status) }}</td>
                    <td class="center">{{ str_replace('_',' ', ucfirst($sale->payment_method)) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
