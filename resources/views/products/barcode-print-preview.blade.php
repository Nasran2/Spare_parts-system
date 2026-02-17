<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barcode Print</title>
    <style>
        :root {
            --label-width: {{ $settings['barcode_sticker_width'] }}cm;
            --label-height: {{ $settings['barcode_sticker_height'] }}cm;
            --paper-width: {{ $settings['barcode_paper_width'] }}cm;
            --row-gap: {{ $settings['barcode_row_gap'] }}cm;
            --col-gap: {{ $settings['barcode_col_gap'] }}cm;
            --top-margin: {{ $settings['barcode_top_margin'] }}cm;
            --left-margin: {{ $settings['barcode_left_margin'] }}cm;
            --pad-top: {{ $settings['barcode_sticker_top_padding'] }}cm;
            --pad-bottom: {{ $settings['barcode_sticker_bottom_padding'] }}cm;
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; color: #111; }
        .sheet {
            width: var(--paper-width);
            padding-top: var(--top-margin);
            padding-left: var(--left-margin);
        }
        .labels {
            display: grid;
            grid-template-columns: repeat({{ $settings['barcode_labels_per_row'] }}, var(--label-width));
            column-gap: var(--col-gap);
            row-gap: var(--row-gap);
        }
        .label {
            width: var(--label-width);
            height: var(--label-height);
            padding-top: var(--pad-top);
            padding-bottom: var(--pad-bottom);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            overflow: hidden;
        }
        .shop { font-size: {{ $settings['barcode_shop_name_size'] }}px; font-weight: 700; line-height: 1.1; }
        .product { font-size: {{ $settings['barcode_product_name_size'] }}px; font-weight: 600; text-align: center; line-height: 1.1; }
        .price { font-size: {{ $settings['barcode_price_tag_size'] }}px; font-weight: 700; line-height: 1.1; }
        .secret { font-size: {{ $settings['barcode_secret_code_size'] }}px; font-weight: 600; letter-spacing: 1px; }
        .barcode-wrap { width: 100%; display: flex; justify-content: center; }
        .barcode { width: 90%; max-width: 90%; height: auto; }
        @media print {
            body { margin: 0; }
        }
    </style>
</head>
<body>
@php
    $shopName = \App\Models\Setting::get('shop_name', 'Vehicle POS System');
    $map = $settings['barcode_cost_code_map'] ?? [];
    $showCostCode = (bool) ($settings['barcode_show_cost_code'] ?? false);

    $formatCurrency = function ($amount) use ($currency, $currencyPosition) {
        $formatted = number_format((float) $amount, 2);
        return $currencyPosition === 'after'
            ? $formatted.' '.trim($currency)
            : trim($currency).' '.$formatted;
    };

    $encodeCost = function ($amount) use ($map) {
        $digits = preg_replace('/\D/', '', number_format((float) $amount, 2, '', ''));
        $out = '';
        foreach (str_split($digits) as $d) {
            $out .= $map[$d] ?? $d;
        }
        return $out;
    };
@endphp

<div class="sheet">
    <div class="labels">
        @forelse($items as $item)
            @php
                $product = $item['product'];
                $barcodeValue = $product->barcode ?: ($product->sku ?: $product->id);
            @endphp
            @for($i = 0; $i < $item['qty']; $i++)
                <div class="label">
                    <div class="shop">{{ $shopName }}</div>
                    <div class="product">{{ $product->name }}</div>
                    <div class="price">{{ $formatCurrency($product->selling_price) }}</div>
                    <div class="barcode-wrap">
                        <svg class="barcode" data-code="{{ $barcodeValue }}"></svg>
                    </div>
                    @if($showCostCode)
                        <div class="secret">{{ $encodeCost($product->cost_price) }}</div>
                    @endif
                </div>
            @endfor
        @empty
            <p>No items selected.</p>
        @endforelse
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script>
document.querySelectorAll('.barcode').forEach((el) => {
    const value = el.getAttribute('data-code') || '';
    if (!value) return;
    JsBarcode(el, value, {
        format: 'CODE128',
        width: 1,
        height: {{ $barcodeHeightPx }},
        displayValue: false,
        margin: 0,
    });
});
window.onload = () => {
    window.print();
};
</script>
</body>
</html>
