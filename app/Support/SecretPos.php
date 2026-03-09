<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Builder;

class SecretPos
{
    private static function normalizeRanges($ranges): array
    {
        $ranges = (array) $ranges;
        $normalized = [];
        foreach ($ranges as $r) {
            if (!is_array($r)) {
                continue;
            }
            $normalized[] = [
                'min' => (int) ($r['min'] ?? 0),
                'max' => (int) ($r['max'] ?? PHP_INT_MAX),
                'hide' => (bool) ($r['hide'] ?? false),
            ];
        }
        return $normalized;
    }

    private static function getRanges(string $primaryKey, ?string $fallbackKey = null, array $default = []): array
    {
        $ranges = Setting::get($primaryKey, null);
        if ($ranges === null && $fallbackKey) {
            $ranges = Setting::get($fallbackKey, null);
        }
        if ($ranges === null) {
            $ranges = $default;
        }
        return self::normalizeRanges($ranges);
    }

    public static function salesHiddenRanges(): array
    {
        // Backward compatible: older installs stored this at secretpos.hidden_ranges
        return self::getRanges('secretpos.hidden_ranges_sales', 'secretpos.hidden_ranges', []);
    }

    public static function purchaseHiddenRanges(): array
    {
        return self::getRanges('secretpos.hidden_ranges_purchases', null, []);
    }

    /**
     * Returns true if SALE amount falls into any hidden range.
     * (Backward compatible: historically this method was used for sales.)
     */
    public static function isHidden(float $amount): bool
    {
        $ranges = self::salesHiddenRanges();
        foreach ($ranges as $r) {
            $hide = (bool) ($r['hide'] ?? false);
            if (!$hide) { continue; }
            $min = (int) ($r['min'] ?? 0);
            $max = (int) ($r['max'] ?? PHP_INT_MAX);
            if ($amount >= $min && $amount <= $max) {
                return true;
            }
        }
        return false;
    }

    public static function isSaleHidden(float $amount): bool
    {
        return self::isHidden($amount);
    }

    public static function isPurchaseHidden(float $amount): bool
    {
        $ranges = self::purchaseHiddenRanges();
        foreach ($ranges as $r) {
            if (!(bool) ($r['hide'] ?? false)) {
                continue;
            }
            $min = (int) ($r['min'] ?? 0);
            $max = (int) ($r['max'] ?? PHP_INT_MAX);
            if ($amount >= $min && $amount <= $max) {
                return true;
            }
        }
        return false;
    }

    /**
     * Masks an amount (returns em dash) if hidden, else formats with 2 decimals.
     */
    public static function mask(float $amount): string
    {
        return self::isHidden($amount) ? '—' : number_format($amount, 2);
    }

    /**
     * If sale total is provided, use its range to mask all related amounts.
     */
    public static function maskForSale(?float $saleTotal, float $amount): string
    {
        if ($saleTotal !== null && self::isHidden($saleTotal)) {
            return '—';
        }
        return self::mask($amount);
    }

    /**
     * Currency + masked amount helper.
     */
    public static function currencyMask(float $amount, string $currency): string
    {
        return trim($currency).' '.self::mask($amount);
    }

    /**
     * Currency + masked amount using sale total.
     */
    public static function currencyMaskForSale(?float $saleTotal, float $amount, string $currency): string
    {
        return trim($currency).' '.self::maskForSale($saleTotal, $amount);
    }

    /**
     * Apply NOT-BETWEEN filters for all hidden ranges on a numeric column.
     * Returns the same query builder for chaining.
     */
    public static function excludeHiddenRanges($query, string $column = 'total_amount')
    {
        // Backward compatible: exclude SALE hidden ranges.
        $ranges = self::salesHiddenRanges();
        if (empty($ranges)) {
            return $query;
        }
        return $query->where(function ($q) use ($ranges, $column) {
            foreach ($ranges as $r) {
                $hide = (bool) ($r['hide'] ?? false);
                if (!$hide) { continue; }
                $min = (int) ($r['min'] ?? 0);
                $max = (int) ($r['max'] ?? PHP_INT_MAX);
                $q->whereNotBetween($column, [$min, $max]);
            }
        });
    }

    public static function excludeHiddenSaleRanges($query, string $column = 'total_amount')
    {
        return self::excludeHiddenRanges($query, $column);
    }

    public static function excludeHiddenPurchaseRanges($query, string $column = 'total_amount')
    {
        $ranges = self::purchaseHiddenRanges();
        if (empty($ranges)) {
            return $query;
        }
        return $query->where(function ($q) use ($ranges, $column) {
            foreach ($ranges as $r) {
                if (!(bool) ($r['hide'] ?? false)) {
                    continue;
                }
                $min = (int) ($r['min'] ?? 0);
                $max = (int) ($r['max'] ?? PHP_INT_MAX);
                $q->whereNotBetween($column, [$min, $max]);
            }
        });
    }
}
