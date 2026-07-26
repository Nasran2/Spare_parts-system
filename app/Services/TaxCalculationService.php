<?php

namespace App\Services;

use App\Models\Product;
use App\Models\TaxSetting;
use Illuminate\Support\Carbon;

class TaxCalculationService
{
    public function productRules(
        ?Product $product,
        TaxSetting $settings,
        string $context = 'sale'
    ): array {
        $override = $product?->taxSetting;
        $canOverride = $settings->allow_product_override && $override;
        $status = $canOverride ? $override->tax_status : 'standard';
        $rate = $canOverride && $override->vat_rate !== null
            ? (string) $override->vat_rate
            : (string) $settings->default_vat_rate;
        $modeField = $context === 'purchase' ? 'purchase_price_mode' : 'sale_price_mode';
        $globalMode = $context === 'purchase'
            ? $settings->default_purchase_price_mode
            : $settings->default_sale_price_mode;
        $mode = $canOverride && $override->{$modeField} !== 'global'
            ? $override->{$modeField}
            : $globalMode;
        $allowed = $context === 'purchase'
            ? (! $canOverride || $override->input_vat_allowed)
            : (! $canOverride || $override->output_vat_allowed);

        if ($status === 'zero_rated') {
            $rate = '0';
        }
        if (in_array($status, ['exempt', 'out_of_scope'], true)) {
            $rate = '0';
            $allowed = false;
        }

        return [
            'tax_status' => $status,
            'vat_rate' => $rate,
            'price_mode' => $mode,
            'vat_allowed' => $allowed,
        ];
    }

    /**
     * All inputs and outputs are decimal strings. Integer scaled arithmetic is
     * used internally so PHP floating-point values never decide tax amounts.
     */
    public function calculateLine(array $input): array
    {
        $unitPrice = DecimalMath::parse($input['unit_price'] ?? '0');
        $quantity = DecimalMath::parse($input['quantity'] ?? '0');
        $gross = DecimalMath::multiply($unitPrice, $quantity);
        $lineDiscount = $this->discount(
            $gross,
            (string) ($input['line_discount_type'] ?? 'fixed'),
            $input['line_discount_value'] ?? '0'
        );
        $billDiscount = min(
            max(0, DecimalMath::parse($input['bill_discount_amount'] ?? '0')),
            max(0, $gross - $lineDiscount)
        );
        $discount = min($gross, $lineDiscount + $billDiscount);
        $discounted = max(0, $gross - $discount);
        $status = (string) ($input['tax_status'] ?? 'standard');
        $mode = (string) ($input['price_mode'] ?? 'inclusive');
        $rate = DecimalMath::parse($input['vat_rate'] ?? '0');
        $enabled = (bool) ($input['vat_enabled'] ?? false);
        $allowed = (bool) ($input['vat_allowed'] ?? true);

        if (! $enabled || ! $allowed || in_array($status, ['exempt', 'out_of_scope'], true)) {
            $net = $discounted;
            $vat = 0;
            $total = $discounted;
            $rate = 0;
        } elseif ($mode === 'inclusive') {
            $net = DecimalMath::roundDiv(
                $discounted * (100 * DecimalMath::SCALE),
                (100 * DecimalMath::SCALE) + $rate
            );
            $vat = $discounted - $net;
            $total = $discounted;
        } else {
            $net = $discounted;
            $vat = DecimalMath::percentage($net, $rate);
            $total = $net + $vat;
        }

        return $this->formatLine([
            'unit_price_minor' => $unitPrice,
            'quantity_minor' => $quantity,
            'gross_minor' => $gross,
            'discount_minor' => $discount,
            'taxable_minor' => $net,
            'vat_minor' => $vat,
            'total_minor' => $total,
            'rounding_minor' => 0,
            'tax_rate_minor' => $rate,
            'tax_status' => $status,
            'price_mode' => $mode,
        ]);
    }

    public function calculateInvoice(
        array $lines,
        string $billDiscountType = 'fixed',
        string|int|float $billDiscountValue = '0'
    ): array {
        $bases = [];
        $discountableTotal = 0;

        foreach ($lines as $index => $line) {
            $unitPrice = DecimalMath::parse($line['unit_price'] ?? '0');
            $quantity = DecimalMath::parse($line['quantity'] ?? '0');
            $gross = DecimalMath::multiply($unitPrice, $quantity);
            $lineDiscount = $this->discount(
                $gross,
                (string) ($line['line_discount_type'] ?? 'fixed'),
                $line['line_discount_value'] ?? '0'
            );
            $afterLine = max(0, $gross - $lineDiscount);
            $bases[$index] = compact('gross', 'lineDiscount', 'afterLine');
            $discountableTotal += $afterLine;
        }

        $billDiscount = $this->discount($discountableTotal, $billDiscountType, $billDiscountValue);
        $remainingBillDiscount = $billDiscount;
        $results = [];
        $lastDiscountableIndex = collect($bases)->filter(fn ($base) => $base['afterLine'] > 0)->keys()->last();

        foreach ($lines as $index => $line) {
            $allocated = 0;
            if ($discountableTotal > 0 && $billDiscount > 0) {
                $allocated = $index === $lastDiscountableIndex
                    ? $remainingBillDiscount
                    : DecimalMath::roundDiv($billDiscount * $bases[$index]['afterLine'], $discountableTotal);
                $allocated = min($allocated, $bases[$index]['afterLine']);
                $remainingBillDiscount -= $allocated;
            }

            $line['bill_discount_amount'] = DecimalMath::format($allocated);
            $result = $this->calculateLine($line);
            $result['source'] = $line['source'] ?? null;
            $results[] = $result;
        }

        $totals = [
            'gross_minor' => 0,
            'discount_minor' => 0,
            'taxable_minor' => 0,
            'vat_minor' => 0,
            'total_minor' => 0,
            'rounding_minor' => 0,
        ];
        foreach ($results as $result) {
            foreach (array_keys($totals) as $key) {
                $totals[$key] += $result['_'.$key];
            }
        }

        return [
            'lines' => $results,
            'totals' => [
                'gross' => DecimalMath::currency($totals['gross_minor']),
                'discount' => DecimalMath::currency($totals['discount_minor']),
                'taxable' => DecimalMath::currency($totals['taxable_minor']),
                'vat' => DecimalMath::currency($totals['vat_minor']),
                'total' => DecimalMath::currency($totals['total_minor']),
                'rounding_adjustment' => DecimalMath::currency($totals['rounding_minor']),
                '_gross_minor' => $totals['gross_minor'],
                '_discount_minor' => $totals['discount_minor'],
                '_taxable_minor' => $totals['taxable_minor'],
                '_vat_minor' => $totals['vat_minor'],
                '_total_minor' => $totals['total_minor'],
            ],
        ];
    }

    public function settingsForDate(Carbon|string|null $date = null): TaxSetting
    {
        return TaxSetting::current($date);
    }

    private function discount(int $base, string $type, string|int|float|null $value): int
    {
        if ($base <= 0) {
            return 0;
        }

        $discount = $type === 'percent' || $type === 'percentage'
            ? DecimalMath::percentage($base, DecimalMath::parse($value ?? '0'))
            : DecimalMath::parse($value ?? '0');

        return min(max(0, $discount), $base);
    }

    private function formatLine(array $values): array
    {
        return [
            'original_unit_price' => DecimalMath::format($values['unit_price_minor']),
            'quantity' => DecimalMath::format($values['quantity_minor']),
            'gross_amount' => DecimalMath::format($values['gross_minor']),
            'discount_amount' => DecimalMath::format($values['discount_minor']),
            'taxable_amount' => DecimalMath::format($values['taxable_minor']),
            'vat_rate' => DecimalMath::format($values['tax_rate_minor']),
            'vat_amount' => DecimalMath::format($values['vat_minor']),
            'total_amount' => DecimalMath::format($values['total_minor']),
            'rounding_adjustment' => DecimalMath::format($values['rounding_minor']),
            'tax_status' => $values['tax_status'],
            'price_mode' => $values['price_mode'],
            '_gross_minor' => $values['gross_minor'],
            '_discount_minor' => $values['discount_minor'],
            '_taxable_minor' => $values['taxable_minor'],
            '_vat_minor' => $values['vat_minor'],
            '_total_minor' => $values['total_minor'],
            '_rounding_minor' => $values['rounding_minor'],
        ];
    }
}
