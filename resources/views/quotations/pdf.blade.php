<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{ $sale->sale_no }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color:#111; }
        .header { display:flex; align-items:center; gap:14px; border-bottom:2px solid #333; padding-bottom:10px; margin-bottom:12px; }
        .header img { max-height: 70px; max-width: 160px; object-fit: contain; }
        h1 { margin:0; font-size: 22px; letter-spacing: 0.5px; }
        .muted{ color:#555; }
        table { width:100%; border-collapse:collapse; }
        th, td { border:1px solid #ddd; padding:6px; }
        th { background:#f5f5f5; text-align:left; }
        .right { text-align:right; }
        .section { margin-top:16px; }
        .totals td { border:none; }
        .terms { font-size: 11px; line-height: 1.6; white-space: pre-wrap; }
        .footer { margin-top:18px; font-size:11px; text-align:center; color:#555; }
        .badge { display:inline-block; border:1px solid #555; padding:2px 6px; font-size:11px; border-radius:4px; }
    </style>
</head>
<body>
    <div class="header">
        @if(!empty($shop['logo']))
            <img src="{{ public_path($shop['logo']) }}" alt="Logo">
        @endif
        <div>
            <h1>Quotation</h1>
            <div class="muted">{{ $shop['name'] }}</div>
            <div class="muted">{{ $shop['address'] }}</div>
            @if($shop['phone'])<div class="muted">Tel: {{ $shop['phone'] }}</div>@endif
            @if($shop['email'])<div class="muted">Email: {{ $shop['email'] }}</div>@endif
        </div>
        <div style="margin-left:auto; text-align:right;">
            <div><span class="badge">{{ $sale->sale_no }}</span></div>
            <div class="muted">Date: {{ ($sale->sale_date ?? $sale->created_at)->format('Y-m-d') }}</div>
            <div class="muted">Valid for: {{ $shop['valid_days'] }} days</div>
        </div>
    </div>

    <div class="section">
        <table>
            <tr>
                <th style="width:50%">Customer</th>
                <th style="width:50%">Prepared By</th>
            </tr>
            <tr>
                <td>
                    {{ $sale->customer->name ?? 'Walk-in Customer' }}<br>
                    @if($sale->customer?->phone) {{ $sale->customer->phone }}<br>@endif
                    @if($sale->customer?->address) {{ $sale->customer->address }} @endif
                </td>
                <td>
                    {{ $sale->user?->name }}
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Description</th>
                    <th class="right">Qty</th>
                    <th class="right">Unit Price</th>
                    <th class="right">Total</th>
                </tr>
            </thead>
            <tbody>
            @foreach($sale->items as $i => $it)
                <tr>
                    <td>{{ $i+1 }}</td>
                    <td>{{ $it->product?->name ?? ('#'.$it->product_id) }}</td>
                    <td class="right">{{ $it->quantity }}</td>
                    <td class="right">{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $it->unit_price, $currency) }}</td>
                    <td class="right">{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $it->total, $currency) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div class="section" style="display:flex; gap:14px;">
        <div style="flex:1">
            <div class="terms">
                <strong>Terms & Conditions</strong>
                <div>{{ $shop['terms'] }}</div>
            </div>
        </div>
        <div style="width:300px; margin-left:auto;">
            <table class="totals" style="width:100%">
                <tr><td>Subtotal</td><td class="right">{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $sale->subtotal, $currency) }}</td></tr>
                <tr><td>Discount</td><td class="right">{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $sale->discount, $currency) }}</td></tr>
                <tr><td>Tax</td><td class="right">{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $sale->tax, $currency) }}</td></tr>
                <tr><td style="font-weight:bold; border-top:1px solid #333;">Grand Total</td><td class="right" style="font-weight:bold; border-top:1px solid #333;">{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $sale->total_amount, $currency) }}</td></tr>
            </table>
        </div>
    </div>

    <div class="footer">This is a system generated quotation.</div>
</body>
</html>
