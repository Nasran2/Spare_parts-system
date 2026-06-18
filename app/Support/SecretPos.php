<?php

namespace App\Support;

use App\Models\Setting;
use App\Services\DashboardVisibilityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class SecretPos
{
    private static function visibilityControls(): array
    {
        return DashboardVisibilityService::configForUser(Auth::user());
    }

    private static function formatPriceByControls(float $amount, array $controls): string
    {
        $priceVisiblePct = (float) ($controls['price_visible_percentage'] ?? 100);
        $masked = DashboardVisibilityService::maskByPercentage(abs($amount), $priceVisiblePct);
        $roundToWhole = $priceVisiblePct < 100;

        if ($roundToWhole) {
            $masked = round($masked);
        }

        if ($priceVisiblePct < 100 && abs($amount) > 0 && $masked <= 0) {
            $masked = 1;
        }

        if ($amount < 0) {
            $masked *= -1;
        }

        return number_format($masked, $roundToWhole ? 0 : 2);
    }

    private static function normalizeRanges($ranges): array
    {
        $ranges = (array) $ranges;
        $normalized = [];
        foreach ($ranges as $r) {
            if (! is_array($r)) {
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
        $ranges = self::getRanges('secretpos.hidden_ranges_sales', 'secretpos.hidden_ranges', []);
        $controlRanges = DashboardVisibilityService::rangesFromControls(
            self::visibilityControls(),
            'hidden_sales_price_ranges',
            ['hidden_price_ranges']
        );

        return array_values(array_merge($ranges, $controlRanges));
    }

    public static function purchaseHiddenRanges(): array
    {
        $ranges = self::getRanges('secretpos.hidden_ranges_purchases', null, []);
        $controlRanges = DashboardVisibilityService::rangesFromControls(
            self::visibilityControls(),
            'hidden_purchase_price_ranges'
        );

        return array_values(array_merge($ranges, $controlRanges));
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
            if (! $hide) {
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

    public static function isSaleHidden(float $amount): bool
    {
        return self::isHidden($amount);
    }

    public static function isPurchaseHidden(float $amount): bool
    {
        $ranges = self::purchaseHiddenRanges();
        foreach ($ranges as $r) {
            if (! (bool) ($r['hide'] ?? false)) {
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
        if (\App\Services\PrivacyModeService::isActiveForUser(Auth::user()) && \App\Services\PrivacyModeService::shouldMaskForCurrentPage()) {
            return \App\Services\PrivacyModeService::maskAmount($amount);
        }

        $controls = self::visibilityControls();
        if (! empty($controls['hide_price_wise_data']) || self::isHidden($amount)) {
            return '—';
        }

        return self::formatPriceByControls($amount, $controls);
    }

    /**
     * If sale total is provided, use its range to mask all related amounts.
     */
    public static function maskForSale(?float $saleTotal, float $amount): string
    {
        if (\App\Services\PrivacyModeService::isActiveForUser(Auth::user()) && \App\Services\PrivacyModeService::shouldMaskForCurrentPage()) {
            return \App\Services\PrivacyModeService::maskAmount($amount);
        }

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
        if (\App\Services\PrivacyModeService::isActiveForUser(Auth::user()) && \App\Services\PrivacyModeService::shouldMaskForCurrentPage()) {
            return \App\Services\PrivacyModeService::maskAmount($amount, $currency);
        }

        return trim($currency).' '.self::mask($amount);
    }

    /**
     * Currency + masked amount using sale total.
     */
    public static function currencyMaskForSale(?float $saleTotal, float $amount, string $currency): string
    {
        if (\App\Services\PrivacyModeService::isActiveForUser(Auth::user()) && \App\Services\PrivacyModeService::shouldMaskForCurrentPage()) {
            return \App\Services\PrivacyModeService::maskAmount($amount, $currency);
        }

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
                if (! $hide) {
                    continue;
                }
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
                if (! (bool) ($r['hide'] ?? false)) {
                    continue;
                }
                $min = (int) ($r['min'] ?? 0);
                $max = (int) ($r['max'] ?? PHP_INT_MAX);
                $q->whereNotBetween($column, [$min, $max]);
            }
        });
    }
}
