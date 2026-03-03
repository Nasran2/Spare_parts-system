<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Return Bill #{{ $return->id }}</title>
    <style>
        * { box-sizing: border-box; }
        @page { margin: 0; }
        body { font-family: Arial, sans-serif; margin:0; padding:16px; background:#fff; color:#000; font-size:12px; }
        body.paper-80mm, body.paper-58mm { padding:8px; }
        .receipt {
            width: auto;
            max-width: 420px;
            margin:0 auto;
            border: 1px solid #000;
            padding: 16px;
        }
        body.paper-80mm .receipt { width: 80mm; max-width: 300px; border: 0; padding: 8px; }
        body.paper-58mm .receipt { width: 58mm; max-width: 220px; border: 0; padding: 8px; }
        h1 { font-size: 18px; margin:0 0 4px; text-align:center; }
        body.paper-80mm h1, body.paper-58mm h1 { font-size:16px; }
        .title { text-align:center; font-weight:700; font-size:13px; margin: 6px 0 10px; text-transform: uppercase; }
        .shop { text-align:center; font-size:14px; line-height:1.4; margin-bottom:12px; }
        table { width:100%; border-collapse:collapse; margin-top:8px; }
        th, td { font-size:14px; padding:4px; border-bottom:1px solid #000; }
        th { text-align:left; }
        .totals { margin-top:10px; }
        .totals-row { display:flex; justify-content:space-between; font-size:14px; margin-bottom:4px; }
        .grand { border-top:1px dashed #000; padding-top:6px; font-size:16px; font-weight:700; }
        .footer { margin-top:14px; font-size:14px; text-align:center; border-top:1px dashed #000; padding-top:8px; }
        .terms { text-align:left; margin-bottom:6px; white-space:pre-wrap; }

        .paper-feed { height: 0; }
        body.paper-80mm .paper-feed { height: 24mm; }
        body.paper-58mm .paper-feed { height: 22mm; }
        @media print { body { padding:0; } }
    </style>
</head>
<body class="{{ in_array($paperSize ?? 'a4', ['80mm','58mm'], true) ? 'paper-'.($paperSize ?? 'a4') : 'paper-a4' }}">
    <div class="receipt">
        @if(($invoiceShowLogo ?? true) && !empty($logoSrc))
            <div style="text-align:center; margin-bottom:6px;">
                <img src="{{ $logoSrc }}" alt="Logo" style="max-height:60px; max-width:180px; object-fit:contain;" />
            </div>
        @endif

        <h1>{{ $shop['name'] }}</h1>
        <div class="shop">
            {{ $shop['address'] }}<br>
            @if($shop['phone']) Tel: {{ $shop['phone'] }}<br>@endif
            @if($shop['email']) Email: {{ $shop['email'] }}<br>@endif
        </div>

        <div class="title">Credit Note / Return Bill</div>

        <div style="font-size:12px; margin-bottom:8px;">
            <div style="display:flex; justify-content:space-between;"><span>Return #</span><span>{{ $return->id }}</span></div>
            <div style="display:flex; justify-content:space-between;"><span>Date</span><span>{{ $return->return_date?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</span></div>
            <div style="display:flex; justify-content:space-between;"><span>Orig. Invoice</span><span>{{ $return->sale?->sale_no }}</span></div>
            <div style="display:flex; justify-content:space-between;"><span>Cashier</span><span>{{ $return->user?->name }}</span></div>
            <div style="display:flex; justify-content:space-between;"><span>Customer</span><span>{{ $return->sale?->customer?->name ?? 'Walk-in Customer' }}</span></div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Item</th>
                    <th style="text-align:right;">Qty</th>
                    <th style="text-align:right;">Price</th>
                    <th style="text-align:right;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($return->items as $it)
                    <tr>
                        <td>{{ $it->product?->name ?? ('#'.$it->product_id) }}</td>
                        <td style="text-align:right;">{{ $it->quantity }}</td>
                        <td style="text-align:right;">{{ \App\Support\SecretPos::currencyMaskForSale($return->total_refund, $it->unit_price, $currency) }}</td>
                        <td style="text-align:right;">{{ \App\Support\SecretPos::currencyMaskForSale($return->total_refund, $it->total, $currency) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals">
            <div class="totals-row grand"><span>Total Refund</span><span>{{ \App\Support\SecretPos::currencyMaskForSale($return->total_refund, $return->total_refund, $currency) }}</span></div>
        </div>

        <div class="footer">
            @if(!empty($invoiceTerms))
                <div class="terms">
                    <strong>Terms &amp; Conditions:</strong>
                    <div>{{ $invoiceTerms }}</div>
                </div>
            @endif
            {{ $invoiceFooterText ?? 'Thank you for your business!' }}
        </div>

        <div class="paper-feed"></div>
    </div>
    <script>window.print();</script>
</body>
</html>