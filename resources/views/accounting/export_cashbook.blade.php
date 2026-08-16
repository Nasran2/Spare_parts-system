<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111827; }
        .header { border-bottom: 2px solid #2563eb; padding-bottom: 12px; margin-bottom: 20px; text-align: center; }
        .header img { max-height: 60px; max-width: 140px; object-fit: contain; margin-bottom: 8px; }
        .shop h2 { margin: 0; font-size: 24px; color: #1e3a8a; }
        .shop p { margin: 2px 0; color: #4b5563; font-size: 12px; }
        
        .title-bar { display: table; width: 100%; margin-bottom: 16px; }
        .title-bar h1 { display: table-cell; font-size: 20px; margin: 0; padding: 0; color: #111827; }
        .title-bar .muted { display: table-cell; text-align: right; color: #6b7280; font-size: 10px; vertical-align: bottom; }
        
        .summary-grid { width: 100%; margin-bottom: 24px; border-collapse: separate; border-spacing: 8px; }
        .summary-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px; text-align: center; }
        .summary-card .label { color: #64748b; font-size: 10px; text-transform: uppercase; margin-bottom: 4px; font-weight: bold; }
        .summary-card .value { color: #0f172a; font-size: 16px; font-weight: bold; }
        .summary-card.net-positive .value { color: #059669; }
        .summary-card.net-negative .value { color: #e11d48; }

        .section-title { font-size: 14px; font-weight: bold; margin: 20px 0 8px; color: #1e293b; padding-bottom: 4px; border-bottom: 1px solid #cbd5e1; }
        
        table.data-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        table.data-table th, table.data-table td { border: 1px solid #d1d5db; padding: 6px 8px; text-align: left; }
        table.data-table th { background: #f1f5f9; font-weight: bold; color: #334155; }
        table.data-table .right { text-align: right; }
        table.data-table tr:nth-child(even) { background-color: #f8fafc; }
    </style>
</head>
<body>
    <div class="header">
        @if(!empty($shop['logo']))
            <img src="{{ public_path($shop['logo']) }}" alt="Logo" />
        @endif
        <div class="shop">
            <h2>{{ $shop['name'] }}</h2>
            <p>{{ $shop['address'] }}</p>
            @if(!empty($shop['phone']) || !empty($shop['email']))
                <p>
                    @if(!empty($shop['phone'])) Tel: {{ $shop['phone'] }} @endif
                    @if(!empty($shop['phone']) && !empty($shop['email'])) | @endif
                    @if(!empty($shop['email'])) Email: {{ $shop['email'] }} @endif
                </p>
            @endif
        </div>
    </div>

    <div class="title-bar">
        <h1>{{ $title }}</h1>
        <div class="muted">Generated: {{ now()->format('Y-m-d H:i') }}</div>
    </div>

    <table class="summary-grid">
        <tr>
            <td class="summary-card">
                <div class="label">Main Account Balance</div>
                <div class="value">{{ number_format($totals['main_balance'], 2) }}</div>
            </td>
            <td class="summary-card">
                <div class="label">Main Account Out</div>
                <div class="value">{{ number_format($totals['main_out'], 2) }}</div>
            </td>
            <td class="summary-card">
                <div class="label">Petty Cash Balance</div>
                <div class="value">{{ number_format($totals['petty_balance'], 2) }}</div>
            </td>
        </tr>
        <tr>
            <td class="summary-card">
                <div class="label">Petty Cash Out</div>
                <div class="value">{{ number_format($totals['petty_out'], 2) }}</div>
            </td>
            <td class="summary-card">
                <div class="label">Total Cash Out</div>
                <div class="value">{{ number_format($totals['total_out'], 2) }}</div>
            </td>
            <td class="summary-card {{ $totals['net_movement'] >= 0 ? 'net-positive' : 'net-negative' }}">
                <div class="label">Net Movement</div>
                <div class="value">{{ number_format($totals['net_movement'], 2) }}</div>
            </td>
        </tr>
    </table>

    <div class="section-title">{{ $main_table[0] }}</div>
    <table class="data-table">
        <thead>
            <tr>
                @foreach($main_table[1] as $header)
                    <th class="{{ $loop->last ? 'right' : '' }}">{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($main_table[2] as $row)
                <tr>
                    @foreach($row as $value)
                        <td class="{{ $loop->last ? 'right' : '' }}">{{ $value }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($main_table[1]) }}" style="text-align: center; color: #6b7280;">No transactions found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">{{ $petty_table[0] }}</div>
    <table class="data-table">
        <thead>
            <tr>
                @foreach($petty_table[1] as $header)
                    <th class="{{ $loop->last ? 'right' : '' }}">{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($petty_table[2] as $row)
                <tr>
                    @foreach($row as $value)
                        <td class="{{ $loop->last ? 'right' : '' }}">{{ $value }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($petty_table[1]) }}" style="text-align: center; color: #6b7280;">No transactions found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

</body>
</html>
