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
        .sheet.align-left { margin-left: 0; margin-right: auto; }
        .sheet.align-center { margin-left: auto; margin-right: auto; }
        .sheet.align-right { margin-left: auto; margin-right: 0; }
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
        .barcode-number { font-size: {{ $settings['barcode_number_size'] ?? 8 }}px; font-weight: 600; letter-spacing: 1px; line-height: 1.1; margin-top: 2px; }
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
    $currency = $currency ?? 'Rs';
    $currencyPosition = $currencyPosition ?? 'before';
    $costMap = $settings['barcode_cost_code_map'] ?? [];
    $sellingMap = $settings['barcode_selling_code_map'] ?? $costMap;
    $showCostCode = (bool) ($settings['barcode_show_cost_code'] ?? false);
    $costZeroFallback = (bool) config('app.secret_cost_zero_fallback', false);
    $sellingZeroFallback = (bool) config('app.secret_selling_zero_fallback', false);

    $pickZeroFallbackChar = function (array $map): string {
        if (!empty($map['0'])) {
            return '';
        }
        $alphabet = range('A', 'Z');
        $used = [];
        foreach ($map as $val) {
            $val = strtoupper((string) $val);
            foreach (str_split($val) as $ch) {
                $used[$ch] = true;
            }
        }
        foreach ($alphabet as $ch) {
            if (empty($used[$ch])) {
                return $ch;
            }
        }
        return '';
    };

    $costZeroFallbackChar = $pickZeroFallbackChar((array) $costMap);
    $sellingZeroFallbackChar = $pickZeroFallbackChar((array) $sellingMap);

    $formatCurrency = function ($amount) use ($currency, $currencyPosition) {
        $formatted = number_format((float) $amount, 2);
        return $currencyPosition === 'after'
            ? $formatted.' '.trim($currency)
            : trim($currency).' '.$formatted;
    };

    $encodeAmount = function ($amount, $map, $zeroFallback, $zeroFallbackChar) {
        $fixed = number_format((float) $amount, 2, '.', '');
        [$intPart, $decPart] = array_pad(explode('.', $fixed, 2), 2, '00');

        $encodeDigits = function (string $digits) use ($map, $zeroFallback, $zeroFallbackChar) {
            $out = '';
            foreach (str_split($digits) as $d) {
                if ($d === '0' && $zeroFallback && (empty($map['0']) || $map['0'] === '')) {
                    $out .= $zeroFallbackChar ?: '0';
                } else {
                    $out .= $map[$d] ?? $d;
                }
            }
            return $out;
        };

        $encodedInt = $encodeDigits($intPart);
        if ($decPart === '00') {
            return $encodedInt;
        }

        $encodedDec = $encodeDigits($decPart);
        return $encodedInt . '.' . $encodedDec;
    };
@endphp

@php
    $alignment = $settings['barcode_alignment'] ?? 'left';
    $alignment = in_array($alignment, ['left', 'center', 'right'], true) ? $alignment : 'left';
@endphp
<div class="sheet align-{{ $alignment }}">
    <div class="labels">
        @forelse($items as $item)
            @php
                $product = $item['product'];
                $barcodeValue = $product->barcode ?: ($product->sku ?: $product->id);
                $showSecretSellingPrice = (bool) ($settings['barcode_enable_selling_secret_code'] ?? false)
                    && (bool) ($item['show_secret_price'] ?? false);
                $displayPrice = $showSecretSellingPrice
                    ? $encodeAmount($product->selling_price, $sellingMap, $sellingZeroFallback, $sellingZeroFallbackChar)
                    : $formatCurrency($product->selling_price);
            @endphp
            @for($i = 0; $i < $item['qty']; $i++)
                <div class="label">
                    <div class="shop">{{ $shopName }}</div>
                    <div class="product">{{ $product->name }}</div>
                    <div class="price">{{ $displayPrice }}</div>
                    <div class="barcode-wrap">
                        <svg class="barcode" data-code="{{ $barcodeValue }}"></svg>
                    </div>
                    <div class="barcode-number">{{ $barcodeValue }}</div>
                    @if($showCostCode)
                        <div class="secret">{{ $encodeAmount($product->cost_price, $costMap, $costZeroFallback, $costZeroFallbackChar) }}</div>
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
