<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Sales Report</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #333; font-size: 12px; margin: 0; padding: 0; }
        .container { width: 100%; margin: 0 auto; padding: 20px; }
        
        /* Letterhead */
        .letterhead { width: 100%; border-bottom: 2px solid #2c3e50; padding-bottom: 15px; margin-bottom: 20px; }
        .letterhead td { vertical-align: middle; border: none; padding: 0; }
        .company-name { font-size: 24px; font-weight: bold; color: #2c3e50; margin-bottom: 4px; }
        .company-meta { font-size: 11px; color: #7f8c8d; line-height: 1.4; }
        .report-title-container { text-align: right; }
        .report-title { font-size: 22px; font-weight: bold; color: #2c3e50; text-transform: uppercase; margin-bottom: 4px; }
        .report-meta { font-size: 11px; color: #7f8c8d; }

        /* Summary Boxes */
        .summary-container { width: 100%; margin-bottom: 25px; border-collapse: collapse; }
        .summary-container td { padding: 0 5px; width: 16.66%; vertical-align: top; }
        .summary-box { border: 1px solid #e0e4e8; background: #f8fafc; padding: 12px 8px; text-align: center; border-radius: 4px; }
        .summary-label { font-size: 10px; color: #64748b; text-transform: uppercase; font-weight: bold; margin-bottom: 6px; }
        .summary-value { font-size: 15px; font-weight: bold; color: #1e293b; }
        .text-green { color: #16a34a; }
        .text-blue { color: #2563eb; }
        .text-red { color: #dc2626; }

        /* Data Table */
        .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .data-table th, .data-table td { border: 1px solid #e2e8f0; padding: 8px 10px; font-size: 11px; }
        .data-table th { background: #f1f5f9; color: #475569; text-transform: uppercase; font-size: 10px; font-weight: bold; text-align: left; }
        .right { text-align: right !important; }
        .center { text-align: center !important; }
        
        .footer { margin-top: 30px; text-align: center; font-size: 10px; color: #94a3b8; border-top: 1px solid #f1f5f9; padding-top: 15px; }
        
        table { border-collapse: collapse; }
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

    <div class="container">
        <!-- Letterhead Header -->
        <table class="letterhead">
            <tr>
                <td style="width: 50%;">
                    <div class="company-name">{{ $businessName }}</div>
                    <div class="company-meta">
                        @if($businessAddress){{ $businessAddress }}<br>@endif
                        @if($businessPhone)Phone: {{ $businessPhone }}@endif
                    </div>
                </td>
                <td class="report-title-container" style="width: 50%;">
                    <div class="report-title">Sales Report</div>
                    <div class="report-meta">
                        <strong>Period:</strong> {{ $from ?? '—' }} to {{ $to ?? '—' }}<br>
                        <strong>Generated:</strong> {{ now()->format('Y-m-d H:i') }}
                    </div>
                </td>
            </tr>
        </table>

        <!-- Summary Widgets (Top) -->
        <table class="summary-container">
            <tr>
                <td style="padding-left: 0;">
                    <div class="summary-box">
                        <div class="summary-label">Total Sales</div>
                        <div class="summary-value">{{ $maskMoney($summary['total_sales'], !empty($controls['hide_total_sales'])) }}</div>
                    </div>
                </td>
                <td>
                    <div class="summary-box">
                        <div class="summary-label">Cash</div>
                        <div class="summary-value text-green">{{ $maskMoney($summary['total_cash'], !empty($controls['hide_supplier_payments']) || !empty($controls['hide_invoice_details'])) }}</div>
                    </div>
                </td>
                <td>
                    <div class="summary-box">
                        <div class="summary-label">Cheque</div>
                        <div class="summary-value text-blue">{{ $maskMoney($summary['total_cheque'], !empty($controls['hide_supplier_payments']) || !empty($controls['hide_invoice_details'])) }}</div>
                    </div>
                </td>
                <td>
                    <div class="summary-box">
                        <div class="summary-label">Paid</div>
                        <div class="summary-value text-green">{{ $maskMoney($summary['total_paid'], !empty($controls['hide_supplier_payments']) || !empty($controls['hide_invoice_details'])) }}</div>
                    </div>
                </td>
                <td>
                    <div class="summary-box">
                        <div class="summary-label">Due</div>
                        <div class="summary-value text-red">{{ $maskMoney($summary['total_due'], !empty($controls['hide_supplier_payments']) || !empty($controls['hide_invoice_details'])) }}</div>
                    </div>
                </td>
                <td style="padding-right: 0;">
                    <div class="summary-box">
                        <div class="summary-label">Invoices</div>
                        <div class="summary-value">{{ !empty($controls['hide_invoice_details']) ? '—' : $summary['count'] }}</div>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Main Data Table -->
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Invoice</th>
                    <th>Customer</th>
                    <th class="right">Total</th>
                    <th class="right">Paid</th>
                    <th class="right">Due</th>
                    <th class="center">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($sales as $s)
                <tr>
                    <td>{{ optional($s->sale_date)->toDateString() }}</td>
                    <td>{{ !empty($controls['hide_invoice_details']) ? 'HIDDEN' : \App\Services\PrivacyModeService::displayInvoiceNumber($s) }}</td>
                    <td>{{ !empty($controls['hide_supplier_names']) ? 'Hidden' : ($s->customer?->name ?? 'Walk-in') }}</td>
                    <td class="right">{{ $maskMoney($s->total_amount, !empty($controls['hide_invoice_details']) || !empty($controls['hide_total_sales'])) }}</td>
                    <td class="right">{{ $maskMoney($s->paid_amount, !empty($controls['hide_supplier_payments']) || !empty($controls['hide_invoice_details'])) }}</td>
                    <td class="right">{{ $maskMoney($s->due_amount, !empty($controls['hide_supplier_payments']) || !empty($controls['hide_invoice_details'])) }}</td>
                    <td class="center">{{ ucfirst($s->payment_status) }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="center">No sales found for this period.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
        
        <div class="footer">
            Generated by {{ $businessName }} System
        </div>
    </div>
</body>
</html>
