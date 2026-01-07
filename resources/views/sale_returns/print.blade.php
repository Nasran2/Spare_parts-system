<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Return Bill #{{ $return->id }}</title>
    <style>
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            margin: 0;
            padding: 20px;
            background-color: #fff;
        }
        .container {
            max-width: 300px;
            margin: 0 auto;
        }
        .header {
            text-align: center;
            margin-bottom: 10px;
            border-bottom: 1px dashed #000;
            padding-bottom: 10px;
        }
        .shop-name {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .bill-title {
            font-size: 14px;
            font-weight: bold;
            margin: 10px 0;
            text-transform: uppercase;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
        }
        .items-table th {
            text-align: left;
            padding: 5px 0;
            border-bottom: 1px dashed #000;
        }
        .items-table td {
            padding: 5px 0;
        }
        .text-right {
            text-align: right;
        }
        .total-section {
            margin-top: 10px;
            border-top: 1px dashed #000;
            padding-top: 5px;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            font-size: 10px;
        }
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 40px;
            font-weight: bold;
            color: rgba(255, 0, 0, 0.2);
            border: 5px solid rgba(255, 0, 0, 0.2);
            padding: 10px 20px;
            z-index: -1;
            pointer-events: none;
            text-transform: uppercase;
        }
        @media print {
            body { margin: 0; padding: 0; }
            .no-print { display: none; }
            .watermark {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="container" style="position: relative;">
        <div class="watermark">RETURNED</div>
        <div class="header">
            <div class="shop-name">{{ $shopName }}</div>
            <div>{{ $shopAddress }}</div>
            <div>Tel: {{ $shopPhone }}</div>
        </div>

        <div class="header" style="border: none; padding-bottom: 0;">
            <div class="bill-title">CREDIT NOTE / RETURN BILL</div>
        </div>

        <div class="info-row">
            <span>Return ID:</span>
            <span>#{{ $return->id }}</span>
        </div>
        <div class="info-row">
            <span>Date:</span>
            <span>{{ $return->return_date->format('d/m/Y H:i') }}</span>
        </div>
        <div class="info-row">
            <span>Orig. Sale:</span>
            <span>{{ $return->sale->sale_no }}</span>
        </div>
        <div class="info-row">
            <span>Customer:</span>
            <span>{{ $return->sale->customer->name ?? 'Walk-in' }}</span>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 40%">Item</th>
                    <th style="width: 20%" class="text-right">Qty</th>
                    <th style="width: 20%" class="text-right">Price</th>
                    <th style="width: 20%" class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($return->items as $item)
                <tr>
                    <td>{{ $item->product->name }}</td>
                    <td class="text-right">{{ $item->quantity }}</td>
                    <td class="text-right">{{ number_format($item->unit_price, 2) }}</td>
                    <td class="text-right">{{ number_format($item->total, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="total-section">
            <div class="info-row" style="font-weight: bold; font-size: 14px;">
                <span>TOTAL REFUND:</span>
                <span>{{ number_format($return->total_refund, 2) }}</span>
            </div>
        </div>

        <div class="footer">
            <p>Thank you for your business!</p>
            <p>Software by VehiclePOS</p>
        </div>
    </div>
</body>
</html>