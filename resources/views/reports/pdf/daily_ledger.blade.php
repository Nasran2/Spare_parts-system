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
                <div class="kpi-label">Period Income (In)</div>
                <div class="kpi-value text-green">{{ $maskMoney($totalIn) }}</div>
            </div>
            <div class="kpi-box">
                <div class="kpi-label">Period Expenses (Out)</div>
                <div class="kpi-value text-red">{{ $maskMoney($totalExpensePeriod) }}</div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Account</th>
                    <th>Related Account</th>
                    <th>Reference</th>
                    <th>Description</th>
                    <th class="right">Money In (Dr)</th>
                    <th class="right">Money Out (Cr)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transactions as $t)
                <tr>
                    <td>{{ $t->transaction_date ? $t->transaction_date->format('Y-m-d') : '' }}</td>
                    <td><span class="type-badge">{{ ucfirst(str_replace('_', ' ', $t->source_type)) }}</span></td>
                    <td>{{ $t->account?->name }}</td>
                    <td>{{ $t->relatedAccount?->name ?? '—' }}</td>
                    <td>{{ $t->reference_no ?? '—' }}</td>
                    <td>{{ $t->description ?? '—' }}</td>
                    <td class="right text-green">
                        {{ $t->direction === 'in' ? $maskMoney($t->amount) : '—' }}
                    </td>
                    <td class="right text-red">
                        {{ $t->direction === 'out' ? $maskMoney($t->amount) : '—' }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" style="text-align: center; padding: 20px; color: #777;">No transactions found for the selected date range.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</body>
</html>
