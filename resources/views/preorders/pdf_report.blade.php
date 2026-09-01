<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Pre-Order Report</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #333; font-size: 12px; margin: 0; padding: 0; }
        .container { width: 100%; margin: 0 auto; padding: 20px; }
        
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
        $businessName = \App\Models\Setting::get('shop_name') ?? \App\Models\Setting::get('business_name') ?? config('app.name', 'Vehicle POS');
    @endphp

    <div class="container">
        @include('pdf.partials.letterhead', [
            'documentTitle' => 'Pre-Order Report',
            'documentMeta' => [
                'Period' => ($request->date_from ?? 'All') . ' to ' . ($request->date_to ?? 'All'),
                'Customer' => $request->filled('customer_id') ? \App\Models\Customer::find($request->customer_id)?->name : 'All Customers',
                'Status' => $request->filled('status') ? ucfirst($request->status) : 'All Statuses'
            ],
        ])

        <!-- Summary Widgets (Top) -->
        <table class="summary-container">
            <tr>
                <td style="padding-left: 0;">
                    <div class="summary-box">
                        <div class="summary-label">Total Pre-Orders</div>
                        <div class="summary-value">{{ number_format($summary['total']) }}</div>
                    </div>
                </td>
                <td>
                    <div class="summary-box">
                        <div class="summary-label">Pending Amount</div>
                        <div class="summary-value text-blue">{{ number_format($summary['pending'], 2) }}</div>
                    </div>
                </td>
                <td>
                    <div class="summary-box">
                        <div class="summary-label">Completed Sales</div>
                        <div class="summary-value text-green">{{ number_format($summary['completed'], 2) }}</div>
                    </div>
                </td>
                <td>
                    <div class="summary-box">
                        <div class="summary-label">Cancelled</div>
                        <div class="summary-value text-red">{{ number_format($summary['cancelled']) }}</div>
                    </div>
                </td>
                <td>
                    <div class="summary-box">
                        <div class="summary-label">Paid Amount</div>
                        <div class="summary-value text-green">{{ number_format($summary['paid'], 2) }}</div>
                    </div>
                </td>
                <td style="padding-right: 0;">
                    <div class="summary-box">
                        <div class="summary-label">Due Amount</div>
                        <div class="summary-value text-red">{{ number_format($summary['due'], 2) }}</div>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Main Data Table -->
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Pre-Order Number</th>
                    <th>Customer</th>
                    <th class="right">Total</th>
                    <th class="right">Paid</th>
                    <th class="right">Due</th>
                    <th class="center">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $order)
                <tr>
                    <td>{{ optional($order->pre_order_date)->format('Y-m-d') }}</td>
                    <td>{{ $order->pre_order_number }}</td>
                    <td>{{ $order->customer?->name ?? 'Walk-in' }}</td>
                    <td class="right">{{ number_format((float)$order->grand_total, 2) }}</td>
                    <td class="right">{{ number_format((float)$order->paid_amount, 2) }}</td>
                    <td class="right">{{ number_format((float)$order->due_amount, 2) }}</td>
                    <td class="center">{{ ucfirst($order->status) }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="center">No pre-orders found for this period.</td>
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
