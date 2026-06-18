<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Sales Report</title>
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
            $controls = is_array($controls ?? null) ? $controls : [];
            $priceVisiblePct = (float) ($controls['price_visible_percentage'] ?? 100);
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
            <div class="title">Sales Report</div>
            <div class="meta">Date Range: {{ $from ?? '—' }} to {{ $to ?? '—' }}</div>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Invoice</th>
                    <th>Customer</th>
                    <th class="right">Total</th>
                    <th class="right">Paid</th>
                    <th class="right">Due</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sales as $s)
                <tr>
                    <td>{{ optional($s->sale_date)->toDateString() }}</td>
                    <td>{{ !empty($controls['hide_invoice_details']) ? 'HIDDEN' : \App\Services\PrivacyModeService::displayInvoiceNumber($s) }}</td>
                    <td>{{ !empty($controls['hide_supplier_names']) ? 'Hidden' : ($s->customer?->name ?? 'Walk-in') }}</td>
                    <td class="right">{{ $maskMoney($s->total_amount, !empty($controls['hide_invoice_details']) || !empty($controls['hide_total_sales'])) }}</td>
                    <td class="right">{{ $maskMoney($s->paid_amount, !empty($controls['hide_supplier_payments']) || !empty($controls['hide_invoice_details'])) }}</td>
                    <td class="right">{{ $maskMoney($s->due_amount, !empty($controls['hide_supplier_payments']) || !empty($controls['hide_invoice_details'])) }}</td>
                    <td>{{ ucfirst($s->payment_status) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="summary">
            <strong>Total Sales:</strong> {{ $maskMoney($summary['total_sales'], !empty($controls['hide_total_sales'])) }} —
            <strong>Paid:</strong> {{ $maskMoney($summary['total_paid'], !empty($controls['hide_supplier_payments']) || !empty($controls['hide_invoice_details'])) }} —
            <strong>Due:</strong> {{ $maskMoney($summary['total_due'], !empty($controls['hide_supplier_payments']) || !empty($controls['hide_invoice_details'])) }} —
            <strong>Invoices:</strong> {{ !empty($controls['hide_invoice_details']) ? '—' : $summary['count'] }}
        </div>
    </div>
</body>
</html>
