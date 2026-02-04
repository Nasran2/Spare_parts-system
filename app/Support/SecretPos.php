<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Builder;

class SecretPos
{
    /**
     * Returns true if amount falls into any hidden range.
     */
    public static function isHidden(float $amount): bool
    {
        $ranges = (array) Setting::get('secretpos.hidden_ranges', []);
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
        $ranges = (array) Setting::get('secretpos.hidden_ranges', []);
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
}
