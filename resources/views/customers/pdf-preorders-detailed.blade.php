<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        .header { text-align: center; margin-bottom: 20px; }
        .pre-order-container { border: 1px solid #999; margin-bottom: 20px; page-break-inside: avoid; }
        .pre-order-header { background-color: #f3f4f6; padding: 10px; border-bottom: 1px solid #999; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 6px; text-align: left; }
        th { background-color: #f9fafb; font-size: 11px; text-transform: uppercase; color: #4b5563; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        .summary-box { border: 1px solid #ccc; padding: 10px; margin-top: 20px; width: 40%; float: right; page-break-inside: avoid; }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 5px; }
        .clear { clear: both; }
        .totals-section { background-color: #f9fafb; padding: 8px; border-top: 1px solid #999; }
        .totals-table { width: 100%; border: none; }
        .totals-table td { border: none; padding: 4px; }
        .status-badge { display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 10px; text-transform: uppercase; }
        .status-pending { background-color: #fef3c7; color: #92400e; }
        .status-completed { background-color: #d1fae5; color: #065f46; }
        .status-cancelled { background-color: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <div class="header">
        <h2>{{ $title }}</h2>
        <p>Customer: <strong>{{ $customer->name }}</strong></p>
        <p>Date Range: {{ \Carbon\Carbon::parse($start)->format('m/d/Y') }} - {{ \Carbon\Carbon::parse($end)->format('m/d/Y') }}</p>
    </div>

    @forelse($preOrders as $order)
        <div class="pre-order-container">
            <div class="pre-order-header">
                Bill #: {{ $order->pre_order_number }}
                | Date: {{ optional($order->pre_order_date)->format('m/d/Y') }}
                | Status: <span class="status-badge status-{{ strtolower($order->status) }}">{{ $order->status }}</span>
                | Vehicle: {{ $order->vehicle_name ?: 'N/A' }}
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Product / Item</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">Price</th>
                        <th class="text-right">Discount</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($order->items as $item)
                        <tr>
                            <td>{{ $item->product ? $item->product->name : $item->original_product_name }}</td>
                            <td class="text-right">{{ $item->quantity }}</td>
                            <td class="text-right">{{ config('app.currency') }} {{ number_format((float)$item->quoted_price, 2) }}</td>
                            <td class="text-right">{{ config('app.currency') }} {{ number_format((float)$item->discount_amount, 2) }}</td>
                            <td class="text-right">{{ config('app.currency') }} {{ number_format((float)$item->line_total, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center">No items found for this pre-order.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="totals-section">
                <table class="totals-table">
                    <tr>
                        <td width="60%"></td>
                        <td width="20%" class="text-right">Total Bill:</td>
                        <td width="20%" class="text-right font-bold">{{ config('app.currency') }} {{ number_format((float)$order->grand_total, 2) }}</td>
                    </tr>
                    <tr>
                        <td></td>
                        <td class="text-right">Paid Amount:</td>
                        <td class="text-right font-bold" style="color: green;">{{ config('app.currency') }} {{ number_format((float)$order->paid_amount, 2) }}</td>
                    </tr>
                    <tr>
                        <td></td>
                        <td class="text-right">Pending/Due:</td>
                        <td class="text-right font-bold" style="color: {{ (float)$order->due_amount > 0 ? 'red' : 'black' }};">{{ config('app.currency') }} {{ number_format((float)$order->due_amount, 2) }}</td>
                    </tr>
                </table>
            </div>
        </div>
    @empty
        <p class="text-center">No Pre-Orders found for the selected date range.</p>
    @endforelse

    <div class="summary-box">
        <div class="summary-row">
            <span>Overall Total Invoice:</span>
            <span class="text-right font-bold">{{ config('app.currency') }} {{ $overallTotals['invoice'] ?? 0 }}</span>
        </div>
        <div class="summary-row">
            <span>Overall Total Paid:</span>
            <span class="text-right font-bold">{{ config('app.currency') }} {{ $overallTotals['paid'] ?? 0 }}</span>
        </div>
        <div class="summary-row">
            <span>Overall Balance Due:</span>
            <span class="text-right font-bold">{{ config('app.currency') }} {{ $overallTotals['balance'] ?? 0 }}</span>
        </div>
    </div>
    <div class="clear"></div>
</body>
</html>
