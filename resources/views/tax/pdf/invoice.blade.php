<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <link href="https://fonts.googleapis.com/css2?family=Abhaya+Libre:wght@400;700&display=swap" rel="stylesheet">
    <style>
        @page { size: A4 portrait; margin: 12mm 15mm 12mm; }
        body { font-family: "DejaVu Serif", serif; color: #000; font-size: 10.5pt; line-height: 1.35; }
        .sinhala-text { font-family: 'Abhaya Libre', sans-serif, "DejaVu Serif"; }
        .title { border: 2px solid #000; width: 145px; margin: 0 auto 10px; padding: 5px 0; text-align: center; font-weight: bold; font-size: 13pt; }
        table { border-collapse: collapse; width: 100%; }
        .meta td { border: 1px solid #000; padding: 5px 7px; width: 50%; vertical-align: top; }
        .party td { height: 95px; }
        .line { margin-bottom: 2px; }
        .supply td { height: 20px; }
        .additional td { height: 40px; }
        .items { margin-top: 10px; page-break-inside: auto; }
        .items thead { display: table-header-group; }
        .items tr { page-break-inside: avoid; }
        .items th, .items td { border: 1px solid #000; padding: 4px 5px; }
        .items th { height: 35px; text-align: center; vertical-align: middle; font-weight: bold; }
        .items .ref { width: 12%; }
        .items .desc { width: 44%; }
        .items .qty { width: 12%; text-align: center; }
        .items .unit { width: 13%; text-align: right; }
        .items .amount { width: 19%; text-align: right; }
        .total-label { text-align: left; }
        .totals { page-break-inside: avoid; }
        .footer-table { margin-top: 10px; page-break-inside: avoid; }
        .footer-table td { border: 1px solid #000; padding: 5px 7px; min-height: 20px; }
        .nowrap { white-space: nowrap; }
    </style>
</head>
<body>
    <div style="font-size: 9pt; margin-bottom: 5px; font-family: sans-serif;">
        <table style="width: 100%; border: none;">
            <tr>
                <td style="width: 30px; vertical-align: top; border: none; padding: 0;">4A</td>
                <td style="text-align: center; border: none; padding: 0;">
                    <span class="sinhala-text" style="font-size: 10.5pt;">I කොටස : (I) ඡේදය - ශ්‍රී ලංකා ප්‍රජාතාන්ත්‍රික සමාජවාදී ජනරජයේ අති විශේෂ ගැසට් පත්‍රය - 2025.11.17</span><br>
                    <span style="font-size: 8.5pt;">PART I : SEC. (I) - GAZETTE EXTRAORDINARY OF THE DEMOCRATIC SOCIALIST REPUBLIC OF SRI LANKA - 17.11.2025</span>
                </td>
            </tr>
        </table>
    </div>
    <div style="border-top: 1px solid #000; margin-bottom: 15px; padding-top: 8px; font-size: 10.5pt; font-weight: bold;">
        8. &nbsp; A sample tax invoice is provided below.
    </div>

    <div class="title">Tax Invoice</div>

    <table class="meta">
        <tr>
            <td>Date of Invoice: <strong>{{ $sale->sale_date?->format('Y-m-d') }}</strong></td>
            <td>Tax Invoice No.: <strong>{{ $sale->tax_invoice_number }}</strong></td>
        </tr>
    </table>
    <table class="meta party" style="margin-top:8px">
        <tr>
            <td>
                <div class="line">Supplier’s TIN: <strong>{{ $settings['business_tin'] ?? $settings['supplier_tin'] ?? '-' }}</strong></div>
                <div class="line">Supplier’s Name: <strong>{{ $shop['name'] }}</strong></div>
                <div class="line">Address: {{ $shop['address'] }}</div>
                <div style="margin-top:42px">Telephone No: {{ $shop['phone'] }}</div>
            </td>
            <td>
                <div class="line">Purchaser’s TIN: <strong>{{ $settings['customer_tin'] ?? $sale->customer?->tin ?: '-' }}</strong></div>
                <div class="line">Purchaser’s Name: <strong>{{ $sale->customer?->name ?: 'Walking Customer' }}</strong></div>
                <div class="line">Address: {{ $sale->customer?->address ?: '-' }}</div>
                <div style="margin-top:42px">Telephone No: {{ $sale->customer?->phone ?: '-' }}</div>
            </td>
        </tr>
    </table>
    <table class="meta supply" style="margin-top:8px">
        <tr><td>Date of Delivery: {{ $sale->sale_date?->format('Y-m-d') }}</td><td>Place of Supply: {{ $sale->store?->name ?: ($sale->customer?->city ?: 'Sri Lanka') }}</td></tr>
    </table>
    <table class="meta additional" style="margin-top:8px"><tr><td>Additional Information if any: {{ $sale->notes }}</td></tr></table>

    <table class="items">
        <thead>
            <tr>
                <th class="ref" style="text-align: center;">Reference</th>
                <th class="desc" style="text-align: center;">Description of Goods or Services</th>
                <th class="qty" style="text-align: center;">Quantity</th>
                <th class="unit" style="text-align: center;">Unit Price</th>
                <th class="amount" style="text-align: center;">Amount<br>Excluding VAT<br>(Rs.)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
            <tr>
                <td class="ref">{{ $row['reference'] }}</td>
                <td class="desc">{{ $row['description'] }}</td>
                <td class="qty">{{ number_format((float) $row['quantity'], 2) }}</td>
                <td class="unit">{{ number_format((float) $row['unit_price'], 2) }}</td>
                <td class="amount">{{ number_format((float) $row['taxable_amount'], 2) }}</td>
            </tr>
            @endforeach
            @for($i = $rows->count(); $i < max(4, $rows->count()); $i++)
                <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td></tr>
            @endfor
        </tbody>
        <tbody class="totals">
            <tr><td colspan="4" class="total-label">Total Value of Supply:</td><td class="amount">{{ number_format((float) $taxable, 2) }}</td></tr>
            <tr><td colspan="4" class="total-label">VAT Amount (Total Value of Supply @ {{ number_format((float) ($settings['default_vat_rate'] ?? 0), 2) }}%)</td><td class="amount">{{ number_format((float) $vat, 2) }}</td></tr>
            <tr><td colspan="4" class="total-label">Total Amount including VAT:</td><td class="amount">{{ number_format((float) $total, 2) }}</td></tr>
        </tbody>
    </table>
    <table class="footer-table">
        <tr><td>Total Amount in words: {{ $amountWords }}</td></tr>
        <tr><td>Mode of Payment: {{ ucwords(str_replace('_', ' ', $sale->payment_method)) }}</td></tr>
    </table>
    
    <div style="margin-top: 30px; font-size: 10pt; font-family: sans-serif;">
        EOG 11 - 0124
    </div>
    <div style="margin-top: 15px; border-top: 3px double #000; text-align: center; font-size: 8.5pt; font-family: sans-serif; padding-top: 5px;">
        PRINTED AT THE DEPARTMENT OF GOVERNMENT PRINTING, SRI LANKA.
    </div>
</body>
</html>
