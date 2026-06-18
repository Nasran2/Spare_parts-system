<?php

namespace App\Services;

use App\Models\Product;
use App\Models\SecretSetting;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class DashboardVisibilityService
{
    private static ?bool $hasSecretTable = null;

    public static function defaults(): array
    {
        return [
            'hide_dashboard_cards' => false,
            'hide_total_sales' => false,
            'hide_total_purchase' => false,
            'hide_stock_values' => false,
            'hide_actual_stock_count' => false,
            'hide_genuine_stock' => false,
            'hide_profit_loss' => false,
            'hide_supplier_names' => false,
            'hide_supplier_payments' => false,
            'hide_invoice_details' => false,
            'hide_charts' => false,
            'hide_reports' => false,
            'hide_widgets' => false,
            'hide_tables' => false,
            'hide_product_wise_data' => false,
            'hide_price_wise_data' => false,
            'hide_qty_wise_data' => false,
            'hide_weekly_purchases' => false,
            'hide_monthly_purchases' => false,
            'hide_actual_purchase_price' => false,
            'hide_actual_stock_price' => false,
            'hide_actual_stock_quantity' => false,
            'profit_visible_percentage' => 100,
            'customer_visible_percentage' => 100,
            'stock_visible_percentage' => 100,
            'price_visible_percentage' => 100,
            'qty_visible_percentage' => 100,
            'hidden_products' => [],
            'hidden_suppliers' => [],
            'hidden_customers' => [],
            'hidden_sales' => [],
            'hidden_stores' => [],
            'affected_stores' => [],
            'hidden_price_ranges' => [],
            'hidden_sales_price_ranges' => [],
            'hidden_product_cost_price_ranges' => [],
            'hidden_product_selling_price_ranges' => [],
            'hidden_purchase_price_ranges' => [],
            'hidden_customer_purchase_price_ranges' => [],
            'hidden_widgets' => [],
        ];
    }

    public static function parseRangeInput(string|array|null $input): array
    {
        $parts = is_array($input)
            ? $input
            : array_map('trim', explode(',', (string) $input));

        $ranges = [];
        foreach ($parts as $part) {
            if (is_array($part)) {
                $min = (float) ($part['min'] ?? 0);
                $max = (float) ($part['max'] ?? $min);
                $ranges[] = [
                    'min' => min($min, $max),
                    'max' => max($min, $max),
                    'hide' => (bool) ($part['hide'] ?? true),
                ];

                continue;
            }

            $token = trim((string) $part);
            if ($token === '') {
                continue;
            }

            if (! preg_match('/^(-?\d+(?:\.\d+)?)\s*-\s*(-?\d+(?:\.\d+)?)$/', $token, $m)) {
                continue;
            }

            $min = (float) $m[1];
            $max = (float) $m[2];
            $ranges[] = [
                'min' => min($min, $max),
                'max' => max($min, $max),
                'hide' => true,
            ];
        }

        return array_values($ranges);
    }

    public static function rangesToInputString(array $ranges): string
    {
        return collect(self::parseRangeInput($ranges))
            ->filter(fn ($r) => (bool) ($r['hide'] ?? true))
            ->map(fn ($r) => rtrim(rtrim(number_format((float) $r['min'], 2, '.', ''), '0'), '.').'-'.rtrim(rtrim(number_format((float) $r['max'], 2, '.', ''), '0'), '.'))
            ->implode(',');
    }

    public static function rangesFromControls(array $controls, string $key, array $fallbackKeys = []): array
    {
        $raw = $controls[$key] ?? [];
        $ranges = self::parseRangeInput($raw);

        if (! empty($ranges)) {
            return $ranges;
        }

        foreach ($fallbackKeys as $fallbackKey) {
            $fallbackRanges = self::parseRangeInput($controls[$fallbackKey] ?? []);
            if (! empty($fallbackRanges)) {
                return $fallbackRanges;
            }
        }

        return [];
    }

    public static function rangesForUser(?User $user, string $key, array $fallbackKeys = []): array
    {
        $controls = self::configForUser($user);

        return self::rangesFromControls($controls, $key, $fallbackKeys);
    }

    public static function isAmountInRanges(float|int $amount, array $ranges): bool
    {
        $value = (float) $amount;
        foreach (self::parseRangeInput($ranges) as $range) {
            if (! (bool) ($range['hide'] ?? true)) {
                continue;
            }

            $min = (float) ($range['min'] ?? 0);
            $max = (float) ($range['max'] ?? $min);
            if ($value >= $min && $value <= $max) {
                return true;
            }
        }

        return false;
    }

    public static function isSuperAdmin(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->isSuperAdmin();
    }

    public static function configRaw(): array
    {
        $defaults = self::defaults();

        $stored = null;
        if (self::hasSecretTable()) {
            $stored = SecretSetting::get('dashboard.visibility', null);
        }

        if (! is_array($stored)) {
            $stored = Setting::get('dashboard.visibility', []);
        }

        if (! is_array($stored)) {
            $stored = [];
        }

        return array_replace_recursive($defaults, $stored);
    }

    public static function configForUser(?User $user): array
    {
        if (self::isSuperAdmin($user)) {
            return self::defaults();
        }

        $config = self::configRaw();
        $affectedStores = collect((array) ($config['affected_stores'] ?? []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->toArray();

        if (!empty($affectedStores) && $user) {
            $userStoreIds = $user->stores->pluck('id')->toArray();
            $intersect = array_intersect($affectedStores, $userStoreIds);
            if (empty($intersect)) {
                return self::defaults();
            }
        }

        return $config;
    }

    public static function save(array $config): void
    {
        $payload = array_replace_recursive(self::defaults(), $config);

        if (self::hasSecretTable()) {
            SecretSetting::set('dashboard.visibility', $payload, 'json', 'dashboard');
        }

        Setting::set('dashboard.visibility', $payload, 'json', 'dashboard');
    }

    public static function maskByPercentage(float|int $value, float|int $percentage): float
    {
        $pct = max(0.0, min(100.0, (float) $percentage));

        return round(((float) $value) * ($pct / 100), 2);
    }

    public static function profitValue(float|int $value, array $controls): float
    {
        if (! empty($controls['hide_profit_loss'])) {
            return 0.0;
        }

        return self::maskByPercentage((float) $value, (float) ($controls['profit_visible_percentage'] ?? 100));
    }

    public static function customerValue(float|int $value, array $controls): float
    {
        return self::maskByPercentage((float) $value, (float) ($controls['customer_visible_percentage'] ?? 100));
    }

    public static function hiddenProductIdsForUser(?User $user): array
    {
        $controls = self::configForUser($user);
        $costRanges = self::rangesFromControls($controls, 'hidden_product_cost_price_ranges', ['hidden_purchase_price_ranges']);
        $sellingRanges = self::rangesFromControls($controls, 'hidden_product_selling_price_ranges');

        $ids = collect((array) ($controls['hidden_products'] ?? []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (! empty($costRanges) || ! empty($sellingRanges)) {
            $rangeProductIds = Product::query()
                ->where(function ($query) use ($costRanges, $sellingRanges) {
                    $hasClause = false;
                    $appendRanges = function ($column, $ranges) use (&$hasClause, $query) {
                        foreach ($ranges as $range) {
                            if (! (bool) ($range['hide'] ?? true)) {
                                continue;
                            }

                            $min = (float) ($range['min'] ?? 0);
                            $max = (float) ($range['max'] ?? $min);
                            if ($hasClause) {
                                $query->orWhereBetween($column, [min($min, $max), max($min, $max)]);
                            } else {
                                $query->whereBetween($column, [min($min, $max), max($min, $max)]);
                                $hasClause = true;
                            }
                        }
                    };

                    $appendRanges('cost_price', $costRanges);
                    $appendRanges('selling_price', $sellingRanges);
                })
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $ids = array_values(array_unique(array_merge($ids, $rangeProductIds)));
        }

        return $ids;
    }

    public static function isProductHiddenForUser(int $productId, ?User $user): bool
    {
        return in_array($productId, self::hiddenProductIdsForUser($user), true);
    }

    public static function hiddenSupplierIdsForUser(?User $user): array
    {
        $controls = self::configForUser($user);

        return collect((array) ($controls['hidden_suppliers'] ?? []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    public static function hiddenCustomerIdsForUser(?User $user): array
    {
        $controls = self::configForUser($user);

        return collect((array) ($controls['hidden_customers'] ?? []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    public static function hiddenSaleIdsForUser(?User $user): array
    {
        $controls = self::configForUser($user);

        return collect((array) ($controls['hidden_sales'] ?? []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    public static function hiddenStoreIdsForUser(?User $user): array
    {
        $controls = self::configForUser($user);

        return collect((array) ($controls['hidden_stores'] ?? []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    public static function isSupplierHiddenForUser(int $supplierId, ?User $user): bool
    {
        return in_array($supplierId, self::hiddenSupplierIdsForUser($user), true);
    }

    public static function isCustomerHiddenForUser(int $customerId, ?User $user): bool
    {
        return in_array($customerId, self::hiddenCustomerIdsForUser($user), true);
    }

    public static function isSaleHiddenForUser(int $saleId, ?User $user): bool
    {
        return in_array($saleId, self::hiddenSaleIdsForUser($user), true);
    }

    public static function isStoreHiddenForUser(int $storeId, ?User $user): bool
    {
        return in_array($storeId, self::hiddenStoreIdsForUser($user), true);
    }

    private static function hasSecretTable(): bool
    {
        if (self::$hasSecretTable !== null) {
            return self::$hasSecretTable;
        }

        try {
            self::$hasSecretTable = Schema::hasTable('secret_settings');
        } catch (\Throwable $e) {
            self::$hasSecretTable = false;
        }

        return self::$hasSecretTable;
    }
}
