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
        .cheque-footer { margin-top:10px; padding-top:8px; border-top:1px dashed #000; text-align:left; font-size:12px; }
        .cheque-footer-title { font-weight:700; margin-bottom:4px; text-align:center; }
        .cheque-line { display:flex; justify-content:space-between; gap:8px; margin-bottom:2px; }
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

        /* A4 Specific Styles */
        body.paper-a4 { padding: 40px; background: #f8fafc; font-size: 13px; }
        @media print { body.paper-a4 { padding: 0; background: #fff; } }
        .a4-invoice {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            padding: 40px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }
        @media print { .a4-invoice { box-shadow: none; padding: 20px; } }
        .a4-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #e2e8f0; padding-bottom: 20px; margin-bottom: 20px; }
        .a4-header-left { max-width: 50%; }
        .a4-logo { max-height: 80px; max-width: 250px; object-fit: contain; }
        .a4-header-right { text-align: right; max-width: 50%; }
        .a4-shop-title { font-size: 24px; color: #1e293b; margin: 0 0 8px 0; font-weight: 800; text-transform: uppercase; }
        .a4-shop-address { font-size: 13px; color: #475569; line-height: 1.5; }
        
        .a4-title-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .a4-title-row h2 { margin: 0; font-size: 28px; color: #334155; font-weight: 300; letter-spacing: 2px; }
        .a4-badge { padding: 6px 12px; border-radius: 4px; font-weight: bold; font-size: 12px; }
        .a4-badge-exchange { background: #fef3c7; color: #b45309; border: 1px solid #fcd34d; }
        .a4-badge-returned { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }

        .a4-meta { display: flex; justify-content: space-between; margin-bottom: 30px; }
        .a4-meta-box { width: 48%; color: #334155; }
        .a4-meta-box strong { color: #64748b; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; }
        .a4-customer-name { font-size: 16px; font-weight: 700; color: #0f172a; margin-top: 4px; margin-bottom: 4px; }
        .a4-text-right { text-align: right; }
        .a4-meta-table { width: 100%; border-collapse: collapse; }
        .a4-meta-table td { padding: 4px 0; border: none; font-size: 13px; }
        .a4-meta-table td:first-child { text-align: left; color: #64748b; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; }
        .a4-meta-table td:last-child { text-align: right; font-weight: 600; color: #0f172a; }

        .a4-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        .a4-table th { background: #f1f5f9; color: #475569; padding: 10px; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; border-bottom: 2px solid #cbd5e1; }
        .a4-table td { padding: 12px 10px; border-bottom: 1px solid #e2e8f0; vertical-align: top; color: #334155; }
        .a4-item-name { font-weight: 600; color: #0f172a; }
        .a4-item-discount { font-size: 11px; color: #b91c1c; margin-top: 4px; }
        .a4-section-heading { background: #f8fafc; font-weight: 700; color: #475569; padding: 12px 10px !important; }

        .a4-summary-container { display: flex; justify-content: space-between; margin-top: 20px; page-break-inside: avoid; }
        .a4-summary-left { width: 50%; padding-right: 20px; }
        .a4-terms { background: #f8fafc; padding: 16px; border-radius: 8px; font-size: 11px; color: #475569; }
        .a4-terms strong { display: block; margin-bottom: 8px; color: #334155; text-transform: uppercase; letter-spacing: 1px; }
        .a4-terms-text { white-space: pre-wrap; line-height: 1.5; }
        
        .a4-summary-right { width: 45%; }
        .a4-summary-table { width: 100%; border-collapse: collapse; }
        .a4-summary-table td { padding: 8px 0; border: none; font-size: 14px; color: #334155; }
        .a4-summary-table td:first-child { text-align: left; }
        .a4-summary-table td:last-child { text-align: right; font-weight: 600; }
        .a4-grand-total td { font-size: 18px; font-weight: 800; color: #0f172a; border-top: 2px solid #e2e8f0; padding-top: 12px; }
        .a4-balance td { color: #b91c1c; font-weight: 700; }

        .a4-cheques { margin-top: 30px; page-break-inside: avoid; }
        .a4-cheques-title { font-size: 12px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; }
        .a4-cheques-table { width: 100%; border-collapse: collapse; }
        .a4-cheques-table th, .a4-cheques-table td { padding: 6px; font-size: 12px; text-align: left; border-bottom: 1px solid #f1f5f9; }
        .a4-cheques-table th { color: #64748b; font-weight: 600; }

        .a4-footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #e2e8f0; text-align: center; color: #64748b; font-size: 12px; page-break-inside: avoid; }
        .a4-footer-msg { font-weight: 600; color: #334155; margin-bottom: 8px; font-size: 14px; }
        .a4-footer-powered { font-size: 10px; }
        .a4-footer-powered a { color: #2563eb; text-decoration: none; }

        @media print { body { padding:0; } }
    </style>
</head>
<body class="{{ in_array($paperSize ?? 'a4', ['80mm','58mm'], true) ? 'paper-'.($paperSize ?? 'a4') : 'paper-a4' }}">
    @php
        $isExchangeBill = ((float) ($exchangeReturnAmount ?? 0)) > 0;
        $controls = is_array($controls ?? null) ? $controls : [];
        $priceVisiblePct = (float) ($controls['price_visible_percentage'] ?? 100);
        $chequePayments = $sale->chequePayments ?? collect();
        $heldChequeAmount = (float) ($sale->held_cheque_amount ?? 0);
        $hasChequeHold = $heldChequeAmount > 0;
        $displayPaymentStatus = $hasChequeHold ? 'Hold' : ucfirst((string) $sale->payment_status);
        $applyPct = function ($value, $pct) {
            $pct = max(0, min(100, (float) $pct));
            return (float) $value * ($pct / 100);
        };
        $maskMoney = function ($value, $forceHide = false) use ($controls, $priceVisiblePct, $applyPct) {
            if ($forceHide || !empty($controls['hide_price_wise_data'])) {
                return '—';
            }

            $raw = (float) $value;
            $masked = $applyPct(abs($raw), $priceVisiblePct);
            $roundToWhole = $priceVisiblePct < 100;

            if ($roundToWhole) {
                $masked = round($masked);
            }

            if ($priceVisiblePct < 100 && abs($raw) > 0 && $masked <= 0) {
                $masked = 1;
            }

            if ($raw < 0) {
                $masked *= -1;
            }

            return number_format($masked, $roundToWhole ? 0 : 2);
        };
        $maskQty = function ($value, $forceHide = false) use ($controls) {
            if ($forceHide || !empty($controls['hide_qty_wise_data']) || !empty($controls['hide_actual_stock_quantity'])) {
                return '—';
            }

            $qty = (float) $value;
            if ($qty > 0 && $qty < 1) {
                $qty = 1;
            }
            if ($qty < 0 && $qty > -1) {
                $qty = -1;
            }

            return number_format(round($qty), 0);
        };

        $taxSnapshot = $sale->tax_snapshot ?? [];
        $vatEnabled = (bool) ($taxSnapshot['vat_enabled'] ?? false);
        $vatRate = (float) ($taxSnapshot['default_vat_rate'] ?? 0);
        $showVatBreakdown = ($taxSnapshot['customer_invoice_vat_display'] ?? 'hide_inclusive') === 'always_show'
            || $sale->taxLines->contains(fn ($line) => $line->price_mode === 'exclusive' && (float) $line->vat_amount > 0);
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

        @if(in_array($paperSize ?? 'a4', ['80mm','58mm'], true))
            @include('sales.partials.print_thermal')
        @else
            @include('sales.partials.print_a4')
        @endif


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
