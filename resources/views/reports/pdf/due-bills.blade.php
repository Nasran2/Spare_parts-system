<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Due Bills Report</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #111; }
        .letterhead { margin-bottom: 12px; }
        .letterhead .name { font-size: 18px; font-weight: bold; }
        .letterhead .meta { font-size: 12px; color: #555; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 6px; }
        thead th { background: #f6f6f6; text-align: left; }
        .right { text-align: right; }
        .summary { margin-top: 12px; }
    </style>
</head>
<body>
    @php
        $controls = \App\Services\DashboardVisibilityService::configForUser(auth()->user());
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
    @endphp
    <?php $name = \App\Models\Setting::get('shop_name') ?? \App\Models\Setting::get('business_name');
          $addr = \App\Models\Setting::get('shop_address') ?? \App\Models\Setting::get('business_address');
          $phone = \App\Models\Setting::get('shop_phone') ?? \App\Models\Setting::get('business_phone'); ?>
    <div class="letterhead">
        <div class="name">{{ $name }}</div>
        <div class="meta">{{ $addr }}{{ $addr ? ' | ' : '' }}{{ $phone }}</div>
    </div>
    <div class="title" style="font-size:16px;font-weight:bold;margin-bottom:8px;">Due Bills Report</div>
    <div style="margin-bottom:8px;">Date Range: {{ $from ?? '—' }} to {{ $to ?? '—' }}</div>

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
        @forelse($sales as $s)
            <tr>
                <td>{{ $s->sale_date?->toDateString() }}</td>
                <td>{{ !empty($controls['hide_invoice_details']) ? 'HIDDEN' : \App\Services\PrivacyModeService::displayInvoiceNumber($s) }}</td>
                <td>{{ $s->customer?->name ?? 'Walk-in' }}</td>
                <td class="right">{{ $maskMoney($s->total_amount, !empty($controls['hide_invoice_details']) || !empty($controls['hide_total_sales'])) }}</td>
                <td class="right">{{ $maskMoney($s->paid_amount, !empty($controls['hide_supplier_payments']) || !empty($controls['hide_invoice_details'])) }}</td>
                <td class="right">{{ $maskMoney($s->due_amount, !empty($controls['hide_supplier_payments']) || !empty($controls['hide_invoice_details'])) }}</td>
                <td>{{ ucfirst($s->payment_status) }}</td>
            </tr>
        @empty
            <tr><td colspan="7" style="text-align:center;color:#666;">No due bills found.</td></tr>
        @endforelse
        </tbody>
    </table>

    <div class="summary">
        <strong>Total Amount:</strong> {{ $maskMoney($summary['total_amount'], !empty($controls['hide_invoice_details']) || !empty($controls['hide_total_sales'])) }}
        &nbsp; | &nbsp;
        <strong>Total Paid:</strong> {{ $maskMoney($summary['total_paid'], !empty($controls['hide_supplier_payments']) || !empty($controls['hide_invoice_details'])) }}
        &nbsp; | &nbsp;
        <strong>Total Due:</strong> {{ $maskMoney($summary['total_due'], !empty($controls['hide_supplier_payments']) || !empty($controls['hide_invoice_details'])) }}
        &nbsp; | &nbsp;
        <strong>Bills:</strong> {{ $summary['count'] }}
    </div>
</body>
</html>
