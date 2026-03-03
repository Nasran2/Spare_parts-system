<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $sale->sale_no }}</title>
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
        .shop { text-align:center; font-size:14px; line-height:1.4; margin-bottom:12px; }
        table { width:100%; border-collapse:collapse; margin-top:8px; }
        th, td { font-size:14px; padding:4px; border-bottom:1px solid #000; }
        th { text-align:left; }
        tfoot td { font-weight:bold; }
        .totals { margin-top:10px; }
        .totals-row { display:flex; justify-content:space-between; font-size:14px; margin-bottom:4px; }
        .grand { border-top:1px dashed #000; padding-top:6px; font-size:16px; }
        .footer { margin-top:14px; font-size:14px; text-align:center; border-top:1px dashed #000; padding-top:8px; }
        .terms { text-align:left; margin-bottom:6px; white-space:pre-wrap; }
        .seal-wrap { text-align:center; margin: 6px 0 10px; }
        .seal {
            display:inline-block;
            border: 2px solid #000;
            padding: 4px 10px;
            font-weight: 800;
            letter-spacing: 2px;
            font-size: 12px;
            transform: rotate(-8deg);
        }
        .seal-returned { border-color: #b91c1c; color: #b91c1c; }
        .seal-exchange { border-color: #b45309; color: #b45309; }

        /*
         * Thermal printers often cut before the very last lines are visible.
         * Add an extra paper feed area so the footer doesn't appear at the top of the next print.
         */
        .paper-feed { height: 0; }
        body.paper-80mm .paper-feed { height: 24mm; }
        body.paper-58mm .paper-feed { height: 22mm; }
        @media print { body { padding:0; } }
    </style>
</head>
<body class="{{ in_array($paperSize ?? 'a4', ['80mm','58mm'], true) ? 'paper-'.($paperSize ?? 'a4') : 'paper-a4' }}">
    <div class="receipt">
        @php
            $isExchangeBill = ((float) ($exchangeReturnAmount ?? 0)) > 0;
        @endphp
        @if(($invoiceShowLogo ?? true) && !empty($logoSrc))
            <div style="text-align:center; margin-bottom:6px;">
                <img src="{{ $logoSrc }}" alt="Logo" style="max-height:60px; max-width:180px; object-fit:contain;" />
            </div>
        @endif
        <h1>{{ $shop['name'] }}</h1>
        @if($isExchangeBill)
            <div class="seal-wrap"><span class="seal seal-exchange">EXCHANGE BILL</span></div>
        @elseif(!empty($hasReturns))
            <div class="seal-wrap"><span class="seal seal-returned">RETURNED</span></div>
        @endif
        <div class="shop">
            {{ $shop['address'] }}<br>
            @if($shop['phone']) Tel: {{ $shop['phone'] }}<br>@endif
            @if($shop['email']) Email: {{ $shop['email'] }}<br>@endif
        </div>
        <div style="font-size:12px; margin-bottom:8px;">
            <div style="display:flex; justify-content:space-between;"><span>Invoice:</span><span>{{ $sale->sale_no }}</span></div>
            @php
                $printedAt = ($sale->created_at ?? now())->timezone(config('app.timezone'))->format('Y-m-d H:i');
            @endphp
            <div style="display:flex; justify-content:space-between;"><span>Date:</span><span>{{ $printedAt }}</span></div>
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
                @foreach(($netItems ?? $sale->items) as $it)
                    <tr>
                        <td>
                            <div>{{ $it->product?->name ?? ('#'.$it->product_id) }}</div>
                            @if(((float) ($it->line_discount_amount ?? 0)) > 0)
                                <div style="font-size:11px; color:#b91c1c; font-weight:700; margin-top:2px;">Discount: -{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, (float) ($it->line_discount_amount ?? 0), $currency) }}</div>
                            @endif
                        </td>
                        <td style="text-align:right;">{{ $it->net_quantity ?? $it->quantity }}</td>
                        <td style="text-align:right;">{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $it->display_unit_price ?? $it->unit_price, $currency) }}</td>
                        <td style="text-align:right;">{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $it->net_total ?? $it->total, $currency) }}</td>
                    </tr>
                @endforeach

                @if(!empty($exchangeReturnItems) && $exchangeReturnItems->count() > 0)
                    <tr>
                        <td colspan="4" style="border-bottom:0; padding-top:10px; font-weight:700;">Returned Items</td>
                    </tr>
                    @foreach($exchangeReturnItems as $rit)
                        <tr>
                            <td>
                                <div>{{ $rit->product?->name ?? ('#'.$rit->product_id) }}</div>
                            </td>
                            <td style="text-align:right;">{{ -1 * (int) $rit->quantity }}</td>
                            <td style="text-align:right;">{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, (float) $rit->unit_price, $currency) }}</td>
                            <td style="text-align:right;">{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, -1 * (float) $rit->total, $currency) }}</td>
                        </tr>
                    @endforeach
                @endif

                @if(!empty($returnItems) && $returnItems->count() > 0)
                    <tr>
                        <td colspan="4" style="border-bottom:0; padding-top:10px; font-weight:700;">Returned From This Invoice</td>
                    </tr>
                    @foreach($returnItems as $rit)
                        <tr>
                            <td>
                                <div>{{ $rit->product?->name ?? ('#'.$rit->product_id) }}</div>
                            </td>
                            <td style="text-align:right;">{{ -1 * (int) $rit->quantity }}</td>
                            <td style="text-align:right;">{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, (float) $rit->unit_price, $currency) }}</td>
                            <td style="text-align:right;">{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, -1 * (float) $rit->total, $currency) }}</td>
                        </tr>
                    @endforeach
                @endif
            </tbody>
        </table>
        @php
            $vatEnabled = (bool) \App\Models\Setting::get('vat_enabled', false);
            $vatRate = (float) \App\Models\Setting::get('vat_rate', 0);
            $payments = $sale->payments ?? collect();
            $tenderedAmount = isset($sale->tendered_amount) && (float) $sale->tendered_amount > 0
                ? (float) $sale->tendered_amount
                : (float) $payments->sum('amount');
            $totalBeforeReturn = (!empty($hasReturns) && !is_null($originalTotalForDisplay ?? null))
                ? (float) $originalTotalForDisplay
                : (float) $sale->total_amount;
            $exchangeCredit = (float) ($exchangeReturnAmount ?? 0);
            $netAfterExchange = round(((float) $sale->total_amount) - $exchangeCredit, 2);
            $customerPay = max(0.0, $netAfterExchange);
            $refundDue = max(0.0, -1 * $netAfterExchange);
            $balanceAmount = $exchangeCredit > 0
                ? max(0, $tenderedAmount - $customerPay)
                : max(0, $tenderedAmount - $totalBeforeReturn);
            $showPaidRow = ((float) $sale->due_amount > 0) || (abs((float) $sale->paid_amount - (float) $sale->total_amount) > 0.0001);
        @endphp
        <div class="totals">
            <div class="totals-row"><span>Subtotal</span><span>{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, (float) ($displaySubtotal ?? $sale->subtotal), $currency) }}</span></div>
            @if(((float) ($cartDiscountAmount ?? 0)) > 0)
            <div class="totals-row"><span>Discount</span><span>{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, (float) $cartDiscountAmount, $currency) }}</span></div>
            @endif
            @if($vatEnabled)
            <div class="totals-row"><span>VAT{{ $vatRate ? ' ('.$vatRate.'%)' : '' }}</span><span>{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $sale->tax, $currency) }}</span></div>
            @endif
            <div class="totals-row grand"><span>Total</span><span>{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $totalBeforeReturn, $currency) }}</span></div>

            @if($exchangeCredit > 0)
                <div class="totals-row"><span>Return Credit</span><span>{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, -1 * $exchangeCredit, $currency) }}</span></div>
                @if($refundDue > 0)
                    <div class="totals-row grand"><span>Refund Due</span><span>{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $refundDue, $currency) }}</span></div>
                @elseif($customerPay > 0)
                    <div class="totals-row grand"><span>Customer Pay</span><span>{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $customerPay, $currency) }}</span></div>
                @endif
            @endif
            @if($tenderedAmount > 0)
            <div class="totals-row"><span>Tendered</span><span>{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $tenderedAmount, $currency) }}</span></div>
            <div class="totals-row"><span>Balance</span><span>{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $balanceAmount, $currency) }}</span></div>
            @endif

            @if(!empty($hasReturns) && ((float) ($returnAmount ?? 0)) > 0)
            <div class="totals-row"><span>Return Amount</span><span>{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, (float) $returnAmount, $currency) }}</span></div>
            <div class="totals-row grand"><span>Final Total</span><span>{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, (float) $sale->total_amount, $currency) }}</span></div>
            @endif
            @if($showPaidRow)
            <div class="totals-row"><span>Paid</span><span>{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $sale->paid_amount, $currency) }}</span></div>
            @endif
            @if((float) $sale->due_amount > 0)
            <div class="totals-row"><span>Due</span><span>{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $sale->due_amount, $currency) }}</span></div>
            @endif
            <div class="totals-row"><span>Status</span><span>{{ ucfirst($sale->payment_status) }}</span></div>
        </div>
        <div class="footer">
            @if(!empty($invoiceTerms))
                <div class="terms">
                    <strong>Terms &amp; Conditions:</strong>
                    <div>{{ $invoiceTerms }}</div>
                </div>
            @endif
            {{ $invoiceFooterText ?? 'Thank you for your business!' }}<br>
            @php
                $dev = config('services.developer');
                $phoneDigits = preg_replace('/\D+/', '', $dev['phone'] ?? '');
            @endphp
            Powered by
            @if(!empty($dev['website']))
                <a href="https://{{ $dev['website'] }}" target="_blank" style="color:inherit; text-decoration:none; font-weight:600;">{{ $dev['website'] }}</a>
            @elseif(!empty($phoneDigits))
                <a href="https://wa.me/{{ $dev['phone'] ? preg_replace('/\D+/', '', $dev['phone']) : '' }}" target="_blank" style="color:inherit; text-decoration:none; font-weight:600;">{{ $dev['name'] ?? $phoneDigits }}</a>
            @else
                <span style="font-weight:600;">{{ $dev['name'] ?? 'Developer' }}</span>
            @endif
        </div>

        <div class="paper-feed"></div>
    </div>
    <script>
        (function () {
            let closed = false;
            const closeSelf = function () {
                if (closed) return;
                closed = true;
                try { window.open('', '_self'); } catch (e) {}
                try { window.close(); } catch (e) {}
            };

            window.addEventListener('afterprint', function () {
                setTimeout(closeSelf, 100);
            });

            if (window.matchMedia) {
                const mediaQuery = window.matchMedia('print');
                const onMediaChange = function (event) {
                    if (!event.matches) {
                        setTimeout(closeSelf, 100);
                    }
                };

                if (mediaQuery.addEventListener) {
                    mediaQuery.addEventListener('change', onMediaChange);
                } else if (mediaQuery.addListener) {
                    mediaQuery.addListener(onMediaChange);
                }
            }

            setTimeout(function () {
                window.print();
            }, 120);
        })();
    </script>
</body>
</html>
