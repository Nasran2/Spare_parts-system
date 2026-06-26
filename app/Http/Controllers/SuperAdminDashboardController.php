<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\PrivacyModeSetting;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Store;
use App\Models\Supplier;
use App\Services\DashboardVisibilityService;
use App\Services\PrivacyModeService;
use Illuminate\Http\Request;

class SuperAdminDashboardController extends Controller
{
    private array $sections = [
        'overview' => 'Overview',
        'dashboard' => 'Dashboard Controls',
        'inventory' => 'Product & Stock',
        'records' => 'Hide Records',
        'privacy' => 'Privacy Mode',
    ];

    public function index(Request $request)
    {
        $section = (string) $request->route('section', 'overview');
        if (! array_key_exists($section, $this->sections)) {
            abort(404);
        }

        $controls = DashboardVisibilityService::configRaw();
        $rangeInputKeys = [
            'hidden_sales_price_ranges',
            'hidden_product_cost_price_ranges',
            'hidden_product_selling_price_ranges',
            'hidden_purchase_price_ranges',
            'hidden_customer_purchase_price_ranges',
        ];

        $rangeInputs = [];
        foreach ($rangeInputKeys as $key) {
            $fallback = $key === 'hidden_sales_price_ranges' ? ['hidden_price_ranges'] : [];
            $rangeInputs[$key] = DashboardVisibilityService::rangesToInputString(
                DashboardVisibilityService::rangesFromControls($controls, $key, $fallback)
            );
        }

        $hiddenProductIds = collect((array) ($controls['hidden_products'] ?? []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        $hiddenSupplierIds = collect((array) ($controls['hidden_suppliers'] ?? []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        $hiddenCustomerIds = collect((array) ($controls['hidden_customers'] ?? []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        $hiddenSaleIds = collect((array) ($controls['hidden_sales'] ?? []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        $hiddenStoreIds = collect((array) ($controls['hidden_stores'] ?? []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->toArray();

        $affectedStoreIds = collect((array) ($controls['affected_stores'] ?? []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->toArray();

        $hiddenProducts = Product::query()
            ->whereIn('id', $hiddenProductIds)
            ->get(['id', 'name', 'sku', 'barcode'])
            ->map(fn ($p) => [
                'id' => (int) $p->id,
                'label' => trim($p->name.' | SKU: '.($p->sku ?: '-').' | Barcode: '.($p->barcode ?: '-')),
            ])
            ->values();

        $hiddenSuppliers = Supplier::query()
            ->whereIn('id', $hiddenSupplierIds)
            ->get(['id', 'name', 'phone'])
            ->map(fn ($s) => [
                'id' => (int) $s->id,
                'label' => trim($s->name.' | Phone: '.($s->phone ?: '-')),
            ])
            ->values();

        $hiddenCustomers = Customer::query()
            ->whereIn('id', $hiddenCustomerIds)
            ->get(['id', 'name', 'phone'])
            ->map(fn ($c) => [
                'id' => (int) $c->id,
                'label' => trim($c->name.' | Phone: '.($c->phone ?: '-')),
            ])
            ->values();

        $hiddenSales = Sale::query()
            ->whereIn('id', $hiddenSaleIds)
            ->get(['id', 'sale_no', 'customer_id', 'total_amount', 'sale_date'])
            ->map(fn ($s) => [
                'id' => (int) $s->id,
                'label' => trim(($s->sale_no ?: ('#'.$s->id)).' | Rs '.number_format((float) $s->total_amount, 2).' | '.optional($s->sale_date)->format('Y-m-d')),
            ])
            ->values();

        $stores = Store::where('is_active', true)->get();

        $summary = [
            'total_sales' => (float) Sale::query()->where('sale_type', 'sale')->sum('total_amount'),
            'total_purchases' => (float) Purchase::query()->sum('total_amount'),
            'total_expenses' => (float) Expense::query()->sum('amount'),
            'total_products' => (int) Product::query()->count(),
        ];

        return view('fun.dashboard', [
            'controls' => $controls,
            'section' => $section,
            'sections' => $this->sections,
            'summary' => $summary,
            'hiddenProducts' => $hiddenProducts,
            'hiddenSuppliers' => $hiddenSuppliers,
            'hiddenCustomers' => $hiddenCustomers,
            'hiddenSales' => $hiddenSales,
            'stores' => $stores,
            'hiddenStoreIds' => $hiddenStoreIds,
            'affectedStoreIds' => $affectedStoreIds,
            'rangeInputs' => $rangeInputs,
            'privacyModeSetting' => PrivacyModeService::getSettings(),
        ]);
    }

    public function searchProducts(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return response()->json(['items' => []]);
        }

        $items = Product::query()
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('sku', 'like', "%{$q}%")
                    ->orWhere('barcode', 'like', "%{$q}%");
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'sku', 'barcode'])
            ->map(fn ($p) => [
                'id' => (int) $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'barcode' => $p->barcode,
                'label' => trim($p->name.' | SKU: '.($p->sku ?: '-').' | Barcode: '.($p->barcode ?: '-')),
            ])
            ->values();

        return response()->json(['items' => $items]);
    }

    public function searchSuppliers(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return response()->json(['items' => []]);
        }

        $items = Supplier::query()
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'phone'])
            ->map(fn ($s) => [
                'id' => (int) $s->id,
                'name' => $s->name,
                'phone' => $s->phone,
                'label' => trim($s->name.' | Phone: '.($s->phone ?: '-')),
            ])
            ->values();

        return response()->json(['items' => $items]);
    }

    public function searchCustomers(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return response()->json(['items' => []]);
        }

        $items = Customer::query()
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'phone', 'email'])
            ->map(fn ($c) => [
                'id' => (int) $c->id,
                'name' => $c->name,
                'phone' => $c->phone,
                'label' => trim($c->name.' | Phone: '.($c->phone ?: '-').' | Email: '.($c->email ?: '-')),
            ])
            ->values();

        return response()->json(['items' => $items]);
    }

    public function searchSales(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return response()->json(['items' => []]);
        }

        $items = Sale::query()
            ->with('customer:id,name')
            ->where(function ($query) use ($q) {
                $query->where('sale_no', 'like', "%{$q}%")
                    ->orWhere('id', $q)
                    ->orWhereHas('customer', fn ($customerQuery) => $customerQuery->where('name', 'like', "%{$q}%"));
            })
            ->orderByDesc('sale_date')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get(['id', 'sale_no', 'customer_id', 'total_amount', 'sale_date'])
            ->map(fn ($s) => [
                'id' => (int) $s->id,
                'label' => trim(($s->sale_no ?: ('#'.$s->id)).' | '.($s->customer?->name ?: 'Walk-in').' | Rs '.number_format((float) $s->total_amount, 2).' | '.optional($s->sale_date)->format('Y-m-d')),
            ])
            ->values();

        return response()->json(['items' => $items]);
    }

    public function save(Request $request)
    {
        $request->validate([
            'stock_visible_percentage' => 'nullable|numeric|min:0|max:100',
            'price_visible_percentage' => 'nullable|numeric|min:0|max:100',
            'profit_visible_percentage' => 'nullable|numeric|min:0|max:100',
            'customer_visible_percentage' => 'nullable|numeric|min:0|max:100',
            'hidden_products' => 'nullable|string',
            'hidden_suppliers' => 'nullable|string',
            'hidden_customers' => 'nullable|string',
            'hidden_sales' => 'nullable|string',
            'hidden_stores' => 'nullable|array',
            'hidden_stores.*' => 'nullable|string',
            'affected_stores' => 'nullable|array',
            'affected_stores.*' => 'nullable|string',
            'hidden_sales_price_ranges' => 'nullable|string',
            'hidden_product_cost_price_ranges' => 'nullable|string',
            'hidden_product_selling_price_ranges' => 'nullable|string',
            'hidden_purchase_price_ranges' => 'nullable|string',
            'hidden_customer_purchase_price_ranges' => 'nullable|string',
            'hidden_widgets' => 'nullable|string',
        ]);

        $sharedInventoryVisiblePct = (float) $request->input(
            'stock_visible_percentage',
            $request->input('qty_visible_percentage', 100)
        );

        $toArray = function (?string $csv): array {
            $parts = array_map('trim', explode(',', (string) $csv));

            return array_values(array_filter($parts, fn ($x) => $x !== ''));
        };

        $section = (string) $request->input('section', '');
        $rangeKeys = [
            'hidden_sales_price_ranges',
            'hidden_product_cost_price_ranges',
            'hidden_product_selling_price_ranges',
            'hidden_purchase_price_ranges',
            'hidden_customer_purchase_price_ranges',
        ];

        $defaults = DashboardVisibilityService::defaults();
        $payload = $section ? DashboardVisibilityService::configRaw() : [];
        $keysForSection = [
            'dashboard' => [
                'affected_stores', 'hide_dashboard_cards', 'hide_total_sales', 'hide_total_purchase', 'hide_profit_loss',
                'hide_stock_values', 'hide_actual_stock_count', 'hide_charts', 'hide_tables',
                'hide_widgets', 'hide_reports', 'hidden_widgets', 'profit_visible_percentage',
            ],
            'inventory' => [
                'affected_stores', 'stock_visible_percentage', 'qty_visible_percentage', 'price_visible_percentage', 'pos_price_percentage_enabled',
                'hide_actual_stock_quantity', 'hide_actual_stock_price', 'hide_actual_purchase_price',
                'hide_price_wise_data', 'hide_qty_wise_data', 'hide_product_wise_data',
                'hidden_products', 'hidden_product_cost_price_ranges',
                'hidden_product_selling_price_ranges',
            ],
            'records' => [
                'hide_supplier_names', 'hide_supplier_payments', 'hide_invoice_details',
                'hide_weekly_purchases', 'hide_monthly_purchases', 'hide_genuine_stock',
                'hidden_suppliers', 'hidden_customers', 'hidden_sales', 'hidden_stores',
                'affected_stores', 'customer_visible_percentage', 'hidden_price_ranges', 'hidden_sales_price_ranges', 'hidden_purchase_price_ranges',
                'hidden_customer_purchase_price_ranges',
            ],
            'privacy' => [],
        ];
        $allowedKeys = $section && isset($keysForSection[$section])
            ? $keysForSection[$section]
            : array_keys($defaults);

        foreach (array_keys($defaults) as $key) {
            if (! in_array($key, $allowedKeys, true)) {
                continue;
            }

            if (str_ends_with($key, '_percentage')) {
                if (in_array($key, ['stock_visible_percentage', 'qty_visible_percentage'], true)) {
                    $payload[$key] = $sharedInventoryVisiblePct;

                    continue;
                }

                $payload[$key] = (float) $request->input($key, $defaults[$key]);

                continue;
            }

            if (in_array($key, ['hidden_stores', 'affected_stores'], true)) {
                $payload[$key] = $request->input($key, []);

                continue;
            }

            if (in_array($key, ['hidden_products', 'hidden_suppliers', 'hidden_customers', 'hidden_sales', 'hidden_widgets'], true)) {
                $payload[$key] = $toArray($request->input($key, ''));

                continue;
            }

            if (in_array($key, $rangeKeys, true)) {
                $payload[$key] = DashboardVisibilityService::parseRangeInput($request->input($key, ''));

                continue;
            }

            if ($key === 'hidden_price_ranges') {
                $payload[$key] = DashboardVisibilityService::parseRangeInput($request->input('hidden_sales_price_ranges', ''));

                continue;
            }

            $payload[$key] = $request->boolean($key);
        }

        if ($section !== 'privacy') {
            DashboardVisibilityService::save($payload);
        }

        if (! $section || $section === 'privacy') {
            $privacySetting = PrivacyModeSetting::first() ?? new PrivacyModeSetting;
            $privacySetting->fill([
                'is_enabled' => $request->boolean('privacy_mode_enabled'),
                'shortcut_key' => $request->input('privacy_mode_shortcut', 'Alt+S') ?: 'Alt+S',
                'shortcut_key_mac' => $request->input('privacy_mode_shortcut_mac', 'Cmd+X') ?: 'Cmd+X',
                'visible_invoice_limit' => (int) $request->input('privacy_mode_limit', 10),
                'masking_type' => $request->input('privacy_mode_mask_type', 'hide'),
                'apply_to_pos' => $request->boolean('privacy_mode_pos'),
                'apply_to_sales_list' => $request->boolean('privacy_mode_sales'),
                'apply_to_reports' => $request->boolean('privacy_mode_reports'),
                'apply_to_dashboard' => $request->boolean('privacy_mode_dashboard'),
                'apply_to_customer_history' => $request->boolean('privacy_mode_customer'),
            ]);
            if (! $privacySetting->exists) {
                $privacySetting->created_by = $request->user()?->id;
            }
            $privacySetting->updated_by = $request->user()?->id;
            $privacySetting->save();
        }

        return back()->with('success', 'Secret Dashboard settings saved successfully.');
    }
}
