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
                <td>{{ $s->sale_no }}</td>
                <td>{{ $s->customer?->name ?? 'Walk-in' }}</td>
                <td class="right">{{ \App\Support\SecretPos::maskForSale($s->total_amount, $s->total_amount) }}</td>
                <td class="right">{{ \App\Support\SecretPos::maskForSale($s->total_amount, $s->paid_amount) }}</td>
                <td class="right">{{ \App\Support\SecretPos::maskForSale($s->total_amount, $s->due_amount) }}</td>
                <td>{{ ucfirst($s->payment_status) }}</td>
            </tr>
        @empty
            <tr><td colspan="7" style="text-align:center;color:#666;">No due bills found.</td></tr>
        @endforelse
        </tbody>
    </table>

    <div class="summary">
        <strong>Total Amount:</strong> {{ \App\Support\SecretPos::mask($summary['total_amount']) }}
        &nbsp; | &nbsp;
        <strong>Total Paid:</strong> {{ number_format($summary['total_paid'],2) }}
        &nbsp; | &nbsp;
        <strong>Total Due:</strong> {{ number_format($summary['total_due'],2) }}
        &nbsp; | &nbsp;
        <strong>Bills:</strong> {{ $summary['count'] }}
    </div>
</body>
</html>
