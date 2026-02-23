<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Receipt {{ $sale->sale_no }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; margin:0; padding:16px; }
        .receipt { max-width:420px; margin:0 auto; border:1px solid #000; padding:16px; }
        h1 { font-size:18px; margin:0 0 4px; text-align:center; }
        .shop { text-align:center; font-size:12px; line-height:1.4; margin-bottom:12px; }
        table { width:100%; border-collapse:collapse; margin-top:8px; }
        th, td { font-size:12px; padding:4px; border-bottom:1px solid #000; }
        th { text-align:left; }
        tfoot td { font-weight:bold; }
        .totals { margin-top:10px; }
        .totals-row { display:flex; justify-content:space-between; font-size:12px; margin-bottom:4px; }
        .grand { border-top:1px dashed #000; padding-top:6px; font-size:14px; }
        .footer { margin-top:14px; font-size:12px; text-align:center; border-top:1px dashed #000; padding-top:8px; }
        @media print { body { padding:0; } }
    </style>
</head>
<body>
    <div class="receipt">
        @if(!empty($shop['logo']))
            <div style="text-align:center; margin-bottom:6px;">
                <img src="{{ asset($shop['logo']) }}" alt="Logo" style="max-height:60px; max-width:180px; object-fit:contain;" />
            </div>
        @endif
        <h1>{{ $shop['name'] }}</h1>
        <div class="shop">
            {{ $shop['address'] }}<br>
            @if($shop['phone']) Tel: {{ $shop['phone'] }}<br>@endif
            @if($shop['email']) Email: {{ $shop['email'] }}<br>@endif
        </div>
        <div style="font-size:12px; margin-bottom:8px;">
            <div style="display:flex; justify-content:space-between;"><span>Invoice:</span><span>{{ $sale->sale_no }}</span></div>
            <div style="display:flex; justify-content:space-between;"><span>Date:</span><span>{{ ($sale->sale_date ?? $sale->created_at)->format('Y-m-d H:i') }}</span></div>
            <div style="display:flex; justify-content:space-between;"><span>Cashier:</span><span>{{ $sale->user?->name }}</span></div>
            <div style="display:flex; justify-content:space-between;"><span>Customer:</span><span>{{ $sale->customer?->name ?? 'Walk-in Customer' }}</span></div>
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
                @foreach($sale->items as $it)
                    <tr>
                        <td>{{ $it->product?->name ?? ('#'.$it->product_id) }}</td>
                        <td style="text-align:right;">{{ $it->quantity }}</td>
                        <td style="text-align:right;">{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $it->unit_price, $currency) }}</td>
                        <td style="text-align:right;">{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $it->total, $currency) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @php($vatEnabled = (bool) \App\Models\Setting::get('vat_enabled', false))
        @php($vatRate = (float) \App\Models\Setting::get('vat_rate', 0))
        @php($payments = $sale->payments ?? collect())
        @php($tenderedAmount = (float) $payments->sum('amount'))
        @php($hasCashPayment = $payments->isNotEmpty() ? $payments->contains(fn($p) => strtolower((string) ($p->payment_method ?? '')) === 'cash') : strtolower((string) $sale->payment_method) === 'cash')
        @php($changeAmount = $hasCashPayment ? max(0, $tenderedAmount - (float) $sale->total_amount) : 0)
        @php($showPaidRow = ((float) $sale->due_amount > 0) || (abs((float) $sale->paid_amount - (float) $sale->total_amount) > 0.0001))
        <div class="totals">
            <div class="totals-row"><span>Subtotal</span><span>{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $sale->subtotal, $currency) }}</span></div>
            <div class="totals-row"><span>Discount</span><span>{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $sale->discount, $currency) }}</span></div>
            @if($vatEnabled)
            <div class="totals-row"><span>VAT{{ $vatRate ? ' ('.$vatRate.'%)' : '' }}</span><span>{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $sale->tax, $currency) }}</span></div>
            @endif
            <div class="totals-row grand"><span>Total</span><span>{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $sale->total_amount, $currency) }}</span></div>
            @if($tenderedAmount > 0)
            <div class="totals-row"><span>Tendered</span><span>{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $tenderedAmount, $currency) }}</span></div>
            @endif
            @if($showPaidRow)
            <div class="totals-row"><span>Paid</span><span>{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $sale->paid_amount, $currency) }}</span></div>
            @endif
            @if((float) $sale->due_amount > 0)
            <div class="totals-row"><span>Due</span><span>{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $sale->due_amount, $currency) }}</span></div>
            @endif
            @if($changeAmount > 0)
            <div class="totals-row"><span>Change</span><span>{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $changeAmount, $currency) }}</span></div>
            @endif
            <div class="totals-row"><span>Status</span><span>{{ ucfirst($sale->payment_status) }}</span></div>
        </div>
        <div class="footer">
            @php($invoiceTerms = \App\Models\Setting::get('invoice_terms', ''))
            @if(!empty($invoiceTerms))
                <div style="text-align:left; margin-bottom:6px;">
                    <strong>Terms & Conditions:</strong>
                    <div>{{ $invoiceTerms }}</div>
                </div>
            @endif
            Thank you for your business!<br>
            @php($dev = config('services.developer'))
            @php($phoneDigits = preg_replace('/\D+/', '', $dev['phone'] ?? ''))
            Powered by
            @if(!empty($dev['website']))
                <a href="https://{{ $dev['website'] }}" target="_blank" style="color:inherit; text-decoration:none; font-weight:600;">{{ $dev['website'] }}</a>
            @elseif(!empty($phoneDigits))
                <a href="https://wa.me/{{ $dev['phone'] ? preg_replace('/\D+/', '', $dev['phone']) : '' }}" target="_blank" style="color:inherit; text-decoration:none; font-weight:600;">{{ $dev['name'] ?? $phoneDigits }}</a>
            @else
                <span style="font-weight:600;">{{ $dev['name'] ?? 'Developer' }}</span>
            @endif
        </div>
    </div>
    <script>window.print();</script>
</body>
</html>
