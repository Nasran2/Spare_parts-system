<?php

namespace App\Services;

use App\Models\PrivacyModeLog;
use App\Models\PrivacyModeSetting;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class PrivacyModeService
{
    public static function getSettings(): PrivacyModeSetting
    {
        return PrivacyModeSetting::first() ?? new PrivacyModeSetting([
            'is_enabled' => false,
            'shortcut_key' => 'Alt+S',
            'shortcut_key_mac' => 'Cmd+X',
            'visible_invoice_limit' => 10,
            'masking_type' => 'hide',
            'apply_to_pos' => true,
            'apply_to_sales_list' => true,
            'apply_to_reports' => true,
            'apply_to_dashboard' => true,
            'apply_to_customer_history' => true,
        ]);
    }

    public static function isEnabled(): bool
    {
        return self::getSettings()->is_enabled;
    }

    public static function canToggle(?User $user): bool
    {
        return (bool) (
            $user
            && self::isEnabled()
            && $user->hasPermission('privacy_mode.toggle')
            && ! $user->hasPermission('privacy_mode.bypass')
        );
    }

    public static function isActiveForUser(?User $user): bool
    {
        if (! self::isEnabled()) {
            return false;
        }

        if ($user && $user->hasPermission('privacy_mode.bypass')) {
            return false;
        }

        return (bool) session('privacy_mode_active', false);
    }

    public static function setting(string $key, $default = null)
    {
        $settings = self::getSettings();

        return $settings->getAttribute($key) ?? $default;
    }

    public static function shouldMaskForCurrentPage(): bool
    {
        $route = request()->route() ? request()->route()->getName() : null;
        if (! $route) {
            // Also check path prefix as fallback
            $path = request()->path();
            if (str_starts_with($path, 'reports')) {
                return (bool) self::setting('apply_to_reports', true);
            }
            if (str_starts_with($path, 'pos')) {
                return (bool) self::setting('apply_to_pos', true);
            }
            if (str_starts_with($path, 'sales')) {
                return (bool) self::setting('apply_to_sales_list', true);
            }

            return false;
        }

        if ($route === 'pos.index' || $route === 'pos.recent-sales') {
            return (bool) self::setting('apply_to_pos', true);
        }
        if ($route === 'sales.index' || $route === 'sales.export.csv' || $route === 'sales.export.pdf') {
            return (bool) self::setting('apply_to_sales_list', true);
        }
        if (str_starts_with($route, 'reports.')) {
            return (bool) self::setting('apply_to_reports', true);
        }
        if ($route === 'dashboard') {
            return (bool) self::setting('apply_to_dashboard', true);
        }
        if ($route === 'customers.show' || $route === 'customer.history.view') {
            return (bool) self::setting('apply_to_customer_history', true);
        }

        return false;
    }

    public static function maskAmount(float|int $amount, string $currency = ''): string
    {
        $type = self::setting('masking_type', 'hide');
        $currencyStr = $currency !== '' ? trim($currency).' ' : '';

        switch ($type) {
            case 'low_amount':
                $lowAmount = (float) $amount * 0.1;

                return $currencyStr.number_format($lowAmount, 2);
            case 'blur':
                return $currencyStr.'****';
            case 'hidden':
                return 'Hidden';
            case 'hide':
            default:
                return '—';
        }
    }

    public static function orderedInvoiceLabel(string $invoiceNumber, int $position): string
    {
        $orderedSuffix = str_pad((string) $position, 2, '0', STR_PAD_LEFT);

        if (preg_match('/^(INV-[A-Z0-9]+?)(\d{2})$/i', $invoiceNumber, $matches)) {
            return strtoupper($matches[1]).$orderedSuffix;
        }

        return 'INV-'.$orderedSuffix;
    }

    public static function applyDailyInvoiceLabels(iterable $sales): iterable
    {
        $dailyCounters = [];

        foreach ($sales as $sale) {
            $dateKey = null;
            if (! empty($sale->sale_date) && method_exists($sale->sale_date, 'toDateString')) {
                $dateKey = $sale->sale_date->toDateString();
            } elseif (! empty($sale->sale_date)) {
                $dateKey = (string) $sale->sale_date;
            } elseif (! empty($sale->created_at) && method_exists($sale->created_at, 'toDateString')) {
                $dateKey = $sale->created_at->toDateString();
            }

            $dateKey = $dateKey ?: 'unknown';
            $dailyCounters[$dateKey] = ($dailyCounters[$dateKey] ?? 0) + 1;
            $sale->privacy_display_invoice_no = self::orderedInvoiceLabel((string) $sale->sale_no, $dailyCounters[$dateKey]);
        }

        return $sales;
    }

    public static function displayInvoiceNumber(object $sale): string
    {
        if (self::isActiveForUser(Auth::user()) && self::shouldMaskForCurrentPage()) {
            return (string) ($sale->privacy_display_invoice_no ?? self::orderedInvoiceLabel((string) ($sale->sale_no ?? ''), 1));
        }

        return (string) ($sale->sale_no ?? '');
    }

    public static function logAction(User $user, string $action, ?string $page = null): void
    {
        PrivacyModeLog::create([
            'user_id' => $user->id,
            'action' => $action,
            'page' => $page ?? request()->path(),
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }
}
