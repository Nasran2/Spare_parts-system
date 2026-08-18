<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Daily Ledger</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; }
        .container { width: 100%; padding: 16px; }
        .header { text-align: center; margin-bottom: 24px; }
        .title { font-size: 22px; font-weight: bold; margin-bottom: 4px; }
        .meta { font-size: 13px; color: #555; }
        
        .kpi-container { width: 100%; margin-bottom: 24px; display: table; }
        .kpi-box { display: table-cell; width: 25%; padding: 10px; border: 1px solid #ddd; text-align: center; background-color: #f9f9f9; }
        .kpi-label { font-size: 11px; color: #555; text-transform: uppercase; margin-bottom: 5px; }
        .kpi-value { font-size: 16px; font-weight: bold; color: #333; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ddd; padding: 8px; font-size: 11px; }
        th { background: #f3f3f3; text-align: left; }
        .right { text-align: right; }
        .text-green { color: #047857; }
        .text-red { color: #b91c1c; }
        .type-badge { padding: 2px 6px; background: #eee; border-radius: 4px; font-size: 10px; }
    </style>
</head>
<body>
    <div class="container">
        @php
            $maskMoney = function ($value) {
                if (\App\Services\PrivacyModeService::isActiveForUser(auth()->user()) && \App\Services\PrivacyModeService::shouldMaskForCurrentPage()) {
                    return \App\Services\PrivacyModeService::maskAmount((float) $value);
                }
                return number_format((float) $value, 2, '.', ',');
            };
            
            $businessName = \App\Models\Setting::get('shop_name') ?? \App\Models\Setting::get('business_name') ?? config('app.name', 'Vehicle POS');
            $businessAddress = \App\Models\Setting::get('shop_address') ?? \App\Models\Setting::get('business_address') ?? '';
            $businessPhone = \App\Models\Setting::get('shop_phone') ?? \App\Models\Setting::get('business_phone') ?? '';
        @endphp
        
        <div class="header">
            <div style="font-size:24px; font-weight:bold; margin-bottom: 6px;">{{ $businessName }}</div>
            <div class="meta">{{ $businessAddress }} @if($businessPhone) • {{ $businessPhone }} @endif</div>
            <div style="margin: 15px 0; border-bottom: 2px solid #333;"></div>
            <div class="title">Accounting Daily Ledger</div>
            <div class="meta">Date Range: {{ $from ?? '—' }} to {{ $to ?? '—' }}</div>
        </div>
        
        <div class="kpi-container">
            <div class="kpi-box">
                <div class="kpi-label">Main Account (Cash)</div>
                <div class="kpi-value">{{ $maskMoney($mainAccountBalance) }}</div>
            </div>
            <div class="kpi-box">
                <div class="kpi-label">Petty Cash Balance</div>
                <div class="kpi-value">{{ $maskMoney($pettyCashBalance) }}</div>
            </div>
            <div class="kpi-box">
                <div class="kpi-label">Sales Revenue</div>
                <div class="kpi-value text-green">{{ $maskMoney($salesRevenue) }}</div>
            </div>
            <div class="kpi-box">
                <div class="kpi-label">Period Expenses (Out)</div>
                <div class="kpi-value text-red">{{ $maskMoney($totalExpensePeriod) }}</div>
            </div>
        </div>

        <div class="kpi-container">
            <div class="kpi-box">
                <div class="kpi-label">Total Sales</div>
                <div class="kpi-value">{{ $maskMoney($totalSales) }}</div>
            </div>
            <div class="kpi-box">
                <div class="kpi-label">Total Cash Received</div>
                <div class="kpi-value">{{ $maskMoney($totalCashReceived) }}</div>
            </div>
            <div class="kpi-box">
                <div class="kpi-label">Total Cheque Received</div>
                <div class="kpi-value">{{ $maskMoney($totalChequeReceived) }}</div>
            </div>
            <div class="kpi-box">
                <div class="kpi-label">Total Due Amount</div>
                <div class="kpi-value text-red">{{ $maskMoney($totalDueAmount) }}</div>
            </div>
        </div>

        @php
            $renderPdfTable = function($transactions, $title, $openingBalance, $closingBalance) use ($maskMoney, $from, $to) {
                $html = '<h3 style="font-size: 14px; margin-top: 20px; color: #333; border-bottom: 1px solid #ddd; padding-bottom: 4px;">' . $title . '</h3>';
                $html .= '<table><thead><tr>';
                $html .= '<th>Date</th><th>Type</th><th>Account</th><th>Related Account</th><th>Reference</th><th>Description & Details</th><th class="right">Money In</th><th class="right">Money Out</th><th class="right">Balance</th>';
                $html .= '</tr></thead><tbody>';
                
                // Opening Balance Row
                $html .= '<tr style="background-color: #eff6ff;">';
                $html .= '<td>' . ($from ?? '') . '</td>';
                $html .= '<td><span class="type-badge" style="background:#bfdbfe;color:#1d4ed8;">Balance</span></td>';
                $html .= '<td style="color:#666;">—</td>';
                $html .= '<td style="color:#666;">—</td>';
                $html .= '<td style="color:#666;">—</td>';
                $html .= '<td style="font-style:italic;color:#333;">Opening Balance</td>';
                $html .= '<td style="color:#666;text-align:center;">—</td>';
                $html .= '<td style="color:#666;text-align:center;">—</td>';
                $html .= '<td class="right" style="font-weight:bold; color:#1d4ed8;">' . $maskMoney($openingBalance) . '</td>';
                $html .= '</tr>';
                
                if ($transactions->isEmpty()) {
                    $html .= '<tr><td colspan="9" style="text-align: center; padding: 20px; color: #777;">No transactions found.</td></tr>';
                } else {
                    foreach ($transactions as $t) {
                        $date = $t->transaction_date ? $t->transaction_date->format('Y-m-d') : '';
                        $type = ucfirst(str_replace('_', ' ', $t->source_type));
                        $accName = htmlspecialchars($t->account?->name ?? '—');
                        $relAccName = htmlspecialchars($t->relatedAccount?->name ?? '—');
                        $refNo = htmlspecialchars($t->reference_no ?? '—');
                        $moneyIn = $t->direction === 'in' ? $maskMoney($t->amount) : '—';
                        $moneyOut = $t->direction === 'out' ? $maskMoney($t->amount) : '—';
                        $balance = $maskMoney($t->running_balance);
                        
                        $desc = htmlspecialchars($t->description ?? '—');
                        if ($t->source_type === 'payment' && $t->cheque_details && $t->cheque_details->count() > 0) {
                            foreach ($t->cheque_details as $cheque) {
                                $desc .= '<br><span style="font-size: 9px; color: #555;">[Cheque: ' . htmlspecialchars($cheque->bank_name) . ' - ' . htmlspecialchars($cheque->cheque_number) . ' - ' . $maskMoney($cheque->amount) . ']</span>';
                            }
                        }
                        
                        $html .= '<tr>';
                        $html .= '<td>' . $date . '</td>';
                        $html .= '<td><span class="type-badge">' . $type . '</span></td>';
                        $html .= '<td>' . $accName . '</td>';
                        $html .= '<td>' . $relAccName . '</td>';
                        $html .= '<td>' . $refNo . '</td>';
                        $html .= '<td>' . $desc . '</td>';
                        $html .= '<td class="right text-green">' . $moneyIn . '</td>';
                        $html .= '<td class="right text-red">' . $moneyOut . '</td>';
                        $html .= '<td class="right" style="font-weight:bold; color:#0e7490;">' . $balance . '</td>';
                        $html .= '</tr>';
                    }
                }
                
                // Closing Balance Row
                $html .= '<tr style="background-color: #f3f4f6; border-top: 2px solid #ccc;">';
                $html .= '<td>' . ($to ?? '') . '</td>';
                $html .= '<td><span class="type-badge" style="background:#e5e7eb;color:#374151;">Balance</span></td>';
                $html .= '<td style="color:#666;">—</td>';
                $html .= '<td style="color:#666;">—</td>';
                $html .= '<td style="color:#666;">—</td>';
                $html .= '<td style="font-style:italic;color:#333;">Closing Balance</td>';
                $html .= '<td style="color:#666;text-align:center;">—</td>';
                $html .= '<td style="color:#666;text-align:center;">—</td>';
                $html .= '<td class="right" style="font-weight:bold; color:#1d4ed8; font-size:12px;">' . $maskMoney($closingBalance) . '</td>';
                $html .= '</tr>';
                
                $html .= '</tbody></table>';
                return $html;
            };
        @endphp

        {!! $renderPdfTable($mainTransactions, 'Main Account (Cash) Transactions', $mainOpeningBalance, $mainClosingBalance) !!}
        {!! $renderPdfTable($chequeTransactions, 'Customer Receivable (Cheques)', $chequeOpeningBalance, $chequeClosingBalance) !!}
        {!! $renderPdfTable($pettyTransactions, 'Petty Cash Transactions', $pettyOpeningBalance, $pettyClosingBalance) !!}
    </div>
</body>
</html>
