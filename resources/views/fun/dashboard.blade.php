@extends('layouts.app')

@section('title', 'Secret Dashboard')
@section('page-title', 'Secret Dashboard')

@section('content')
@php
    $section = $section ?? 'overview';
    $sections = $sections ?? [
        'overview' => 'Overview',
        'dashboard' => 'Dashboard Controls',
        'inventory' => 'Product & Stock',
        'records' => 'Hide Records',
        'privacy' => 'Privacy Mode',
    ];

    $sectionMeta = [
        'overview' => ['icon' => 'fa-gauge-high', 'help' => 'See what this panel controls and jump to the right setting page.'],
        'dashboard' => ['icon' => 'fa-chart-pie', 'help' => 'Control dashboard cards, widgets, reports, charts, and summary values.'],
        'inventory' => ['icon' => 'fa-boxes-stacked', 'help' => 'Control product, stock, price, and quantity visibility.'],
        'records' => ['icon' => 'fa-eye-slash', 'help' => 'Hide exact suppliers, customers, bills, products, or value ranges from normal users.'],
        'privacy' => ['icon' => 'fa-keyboard', 'help' => 'Configure the POS shortcut and temporary demo/privacy display behavior.'],
    ];

    $dashboardToggles = [
        'hide_dashboard_cards' => ['Hide top summary cards', 'Removes the main financial cards from the normal dashboard.'],
        'hide_total_sales' => ['Hide total sales value', 'Shows sales total as hidden or zero in normal views and reports.'],
        'hide_total_purchase' => ['Hide total purchase value', 'Hides purchase total values from normal users.'],
        'hide_profit_loss' => ['Hide profit and loss', 'Protects profit, margin, and loss values.'],
        'hide_stock_values' => ['Hide stock money values', 'Hides stock cost/selling value totals.'],
        'hide_actual_stock_count' => ['Hide stock item counts', 'Hides exact stock counts on dashboard summaries.'],
        'hide_charts' => ['Hide charts and graphs', 'Removes dashboard charts from normal users.'],
        'hide_tables' => ['Hide data tables', 'Hides dashboard tables and report tables where supported.'],
        'hide_widgets' => ['Hide small widgets', 'Hides smaller dashboard widgets.'],
        'hide_reports' => ['Disable reports pages', 'Blocks report pages for normal users.'],
    ];

    $inventoryToggles = [
        'hide_actual_stock_quantity' => ['Hide exact stock quantity', 'Shows stock as hidden instead of exact quantities.'],
        'hide_actual_stock_price' => ['Hide exact selling price', 'Hides product selling prices where this rule applies.'],
        'hide_actual_purchase_price' => ['Hide exact purchase cost', 'Hides cost/purchase prices from normal users.'],
        'hide_price_wise_data' => ['Hide all price data', 'Hides all price-based fields controlled by this dashboard.'],
        'hide_qty_wise_data' => ['Hide all quantity data', 'Hides all quantity-based fields controlled by this dashboard.'],
        'hide_product_wise_data' => ['Hide product-level data', 'Replaces product names/details where supported.'],
    ];

    $recordToggles = [
        'hide_supplier_names' => ['Mask supplier/customer names', 'Shows names as Hidden where records are still visible.'],
        'hide_supplier_payments' => ['Mask payment amounts', 'Hides paid/due payment amounts where supported.'],
        'hide_invoice_details' => ['Mask invoice numbers/details', 'Masks invoice numbers instead of showing the real number.'],
        'hide_weekly_purchases' => ['Hide weekly purchase info', 'Protects weekly purchase summaries.'],
        'hide_monthly_purchases' => ['Hide monthly purchase info', 'Protects monthly purchase summaries.'],
        'hide_genuine_stock' => ['Hide genuine stock values', 'Hides genuine stock metrics where supported.'],
    ];

    $toggle = function ($key, $title, $help) use ($controls, $privacyModeSetting) {
        $privacyMap = [
            'privacy_mode_enabled' => 'is_enabled',
            'privacy_mode_pos' => 'apply_to_pos',
            'privacy_mode_sales' => 'apply_to_sales_list',
            'privacy_mode_reports' => 'apply_to_reports',
            'privacy_mode_dashboard' => 'apply_to_dashboard',
            'privacy_mode_customer' => 'apply_to_customer_history',
        ];
        $isChecked = array_key_exists($key, $privacyMap)
            ? !empty($privacyModeSetting->{$privacyMap[$key]})
            : !empty($controls[$key]);
        $checked = $isChecked ? 'checked' : '';
        return <<<HTML
            <label class="secret-toggle">
                <span>
                    <strong>{$title}</strong>
                    <small>{$help}</small>
                </span>
                <span class="switch">
                    <input type="checkbox" name="{$key}" value="1" {$checked}>
                    <i></i>
                </span>
            </label>
        HTML;
    };

    $sectionUrl = fn ($key) => $key === 'overview' ? route('fun.dashboard') : route('fun.dashboard.section', $key);
@endphp

<style>
    .secret-shell { background: #f6f8fb; border: 1px solid #e5edf6; }
    .secret-hero { background: #ffffff; border: 1px solid #e6edf5; }
    .secret-nav a { border: 1px solid #e2e8f0; background: #fff; color: #334155; }
    .secret-nav a.active { border-color: #2563eb; background: #eff6ff; color: #1d4ed8; }
    .secret-panel { background: #fff; border: 1px solid #e6edf5; }
    .secret-toggle { display:flex; align-items:center; justify-content:space-between; gap:16px; padding:14px 16px; border:1px solid #e2e8f0; border-radius:12px; background:#f8fafc; cursor:pointer; }
    .secret-toggle strong { display:block; color:#172033; font-size:14px; }
    .secret-toggle small { display:block; color:#64748b; font-size:12px; margin-top:2px; line-height:1.35; }
    .switch { position:relative; width:46px; height:26px; flex:0 0 auto; }
    .switch input { opacity:0; width:0; height:0; }
    .switch i { position:absolute; inset:0; border-radius:999px; background:#cbd5e1; transition:.18s; }
    .switch i:before { content:""; position:absolute; width:18px; height:18px; left:4px; top:4px; background:white; border-radius:50%; transition:.18s; box-shadow:0 1px 3px rgba(15,23,42,.24); }
    .switch input:checked + i { background:#10b981; }
    .switch input:checked + i:before { transform:translateX(20px); }
    .field-label { display:block; font-size:13px; font-weight:800; color:#243044; margin-bottom:6px; }
    .field-help { font-size:12px; color:#64748b; margin-top:5px; }
    .text-field { width:100%; border:1px solid #dbe4ef; border-radius:12px; padding:11px 13px; outline:none; background:white; }
    .text-field:focus { border-color:#38bdf8; box-shadow:0 0 0 3px rgba(56,189,248,.18); }
    .picker-results { position:relative; z-index:20; }
</style>

<div class="secret-shell rounded-3xl p-5 md:p-7 space-y-5">
    @if(session('success'))
        <div class="px-4 py-3 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 font-semibold">{{ session('success') }}</div>
    @endif

    <div class="secret-hero rounded-2xl shadow-sm p-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <p class="text-xs font-black tracking-[0.16em] uppercase text-blue-700">Super Admin Only</p>
                <h2 class="text-2xl md:text-3xl font-black text-slate-900 mt-1">Secret Dashboard</h2>
                <p class="text-sm text-slate-600 mt-1 max-w-3xl">Choose a section below. Super Admin always sees the real data; these settings only change what normal users and admins can see.</p>
            </div>
            <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-slate-900 text-white rounded-xl hover:bg-slate-800 transition">
                <i class="fas fa-arrow-left"></i>
                Normal Dashboard
            </a>
        </div>
    </div>

    <div class="secret-nav grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-3">
        @foreach($sections as $key => $label)
            <a href="{{ $sectionUrl($key) }}" class="rounded-2xl p-4 transition hover:border-blue-300 {{ $section === $key ? 'active' : '' }}">
                <div class="flex items-center gap-3">
                    <i class="fas {{ $sectionMeta[$key]['icon'] ?? 'fa-circle' }} text-lg"></i>
                    <strong class="text-sm">{{ $label }}</strong>
                </div>
                <p class="text-xs mt-2 text-slate-500">{{ $sectionMeta[$key]['help'] ?? '' }}</p>
            </a>
        @endforeach
    </div>

    @if($section === 'overview')
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
            <div class="secret-panel rounded-2xl p-5">
                <p class="text-xs font-bold text-blue-700 uppercase">Actual Sales</p>
                <p class="text-3xl font-black text-slate-900 mt-1">{{ number_format($summary['total_sales'], 2) }}</p>
            </div>
            <div class="secret-panel rounded-2xl p-5">
                <p class="text-xs font-bold text-amber-700 uppercase">Actual Purchases</p>
                <p class="text-3xl font-black text-slate-900 mt-1">{{ number_format($summary['total_purchases'], 2) }}</p>
            </div>
            <div class="secret-panel rounded-2xl p-5">
                <p class="text-xs font-bold text-rose-700 uppercase">Actual Expenses</p>
                <p class="text-3xl font-black text-slate-900 mt-1">{{ number_format($summary['total_expenses'], 2) }}</p>
            </div>
            <div class="secret-panel rounded-2xl p-5">
                <p class="text-xs font-bold text-emerald-700 uppercase">Products</p>
                <p class="text-3xl font-black text-slate-900 mt-1">{{ number_format($summary['total_products']) }}</p>
            </div>
        </div>

        <div class="secret-panel rounded-2xl p-6">
            <h3 class="text-xl font-black text-slate-900">What should I use?</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mt-4">
                <a href="{{ route('fun.dashboard.section', 'dashboard') }}" class="rounded-xl border border-slate-200 p-4 hover:border-blue-300">
                    <strong class="text-slate-900">Dashboard Controls</strong>
                    <p class="text-sm text-slate-500 mt-1">Hide dashboard values, charts, report pages, or widgets.</p>
                </a>
                <a href="{{ route('fun.dashboard.section', 'inventory') }}" class="rounded-xl border border-slate-200 p-4 hover:border-blue-300">
                    <strong class="text-slate-900">Product & Stock</strong>
                    <p class="text-sm text-slate-500 mt-1">Hide products, prices, stock quantities, or stock values.</p>
                </a>
                <a href="{{ route('fun.dashboard.section', 'records') }}" class="rounded-xl border border-slate-200 p-4 hover:border-blue-300">
                    <strong class="text-slate-900">Hide Records</strong>
                    <p class="text-sm text-slate-500 mt-1">Hide exact suppliers, customers, or bills by search.</p>
                </a>
                <a href="{{ route('fun.dashboard.section', 'privacy') }}" class="rounded-xl border border-slate-200 p-4 hover:border-blue-300">
                    <strong class="text-slate-900">Privacy Mode</strong>
                    <p class="text-sm text-slate-500 mt-1">Set the POS shortcut and temporary display rules.</p>
                </a>
            </div>
        </div>
    @else
        <form method="POST" action="{{ route('fun.dashboard.save') }}" class="space-y-5">
            @csrf
            <input type="hidden" name="section" value="{{ $section }}">

            @if(in_array($section, ['dashboard', 'inventory', 'records']))
                <div class="secret-panel rounded-2xl p-6 border-blue-200 bg-blue-50/30">
                    <h3 class="text-lg font-black text-blue-900">Target Stores</h3>
                    <p class="text-sm text-blue-800 mt-1 mb-4">Select specific stores if you only want the secret dashboard rules to affect users from these branches. Leave blank to apply rules to all normal users.</p>
                    <div class="relative">
                        <select name="affected_stores[]" multiple class="text-field border-blue-200" style="height: 100px;">
                            @foreach($stores as $store)
                                <option value="{{ $store->id }}" {{ in_array($store->id, $affectedStoreIds ?? []) ? 'selected' : '' }}>
                                    {{ $store->name }} {{ $store->code ? "({$store->code})" : '' }}
                                </option>
                            @endforeach
                        </select>
                        <p class="field-help mt-2 text-blue-700">Hold Ctrl/Cmd to select multiple stores.</p>
                    </div>
                </div>
            @endif

            @if($section === 'dashboard')
                <div class="secret-panel rounded-2xl p-6">
                    <h3 class="text-xl font-black text-slate-900">Dashboard Controls</h3>
                    <p class="text-sm text-slate-500 mt-1 mb-5">Use these switches to decide what normal users can see on dashboards and reports. Super Admin is not affected.</p>
                    <div class="mb-5">
                        <label class="field-label">Visible profit percentage</label>
                        <input type="number" min="0" max="100" step="1" name="profit_visible_percentage" value="{{ $controls['profit_visible_percentage'] ?? 100 }}" class="text-field">
                        <p class="field-help">100 means show full profit. 50 means show half of profit values. Use 0 or the hide switch to remove profit from normal users.</p>
                    </div>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                        @foreach($dashboardToggles as $key => [$title, $help])
                            {!! $toggle($key, $title, $help) !!}
                        @endforeach
                    </div>
                    <div class="mt-5">
                        <label class="field-label">Hidden widget keys</label>
                        <input type="text" name="hidden_widgets" value="{{ implode(',', (array) ($controls['hidden_widgets'] ?? [])) }}" class="text-field" placeholder="Example: top_products,recent_sales">
                        <p class="field-help">Optional advanced setting. Use only if a developer gave you exact widget keys.</p>
                    </div>
                </div>
            @endif

            @if($section === 'inventory')
                <div class="secret-panel rounded-2xl p-6">
                    <h3 class="text-xl font-black text-slate-900">Product & Stock Visibility</h3>
                    <p class="text-sm text-slate-500 mt-1 mb-5">Control product prices, cost prices, stock quantities, and exact products normal users should not see.</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="field-label">Visible stock/quantity percentage</label>
                            <input type="number" min="0" max="100" step="1" name="stock_visible_percentage" value="{{ $controls['stock_visible_percentage'] ?? ($controls['qty_visible_percentage'] ?? 100) }}" class="text-field">
                            <p class="field-help">100 means show the full quantity. 50 means show half.</p>
                        </div>
                        <div>
                            <label class="field-label">Visible price percentage</label>
                            <input type="number" min="0" max="100" step="1" name="price_visible_percentage" value="{{ $controls['price_visible_percentage'] ?? 100 }}" class="text-field mb-3">
                            {!! $toggle('pos_price_percentage_enabled', 'Enable POS price percentage', 'If disabled, POS screen will show real prices regardless of the percentage above.') !!}
                        </div>
                    </div>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 mt-5">
                        @foreach($inventoryToggles as $key => [$title, $help])
                            {!! $toggle($key, $title, $help) !!}
                        @endforeach
                    </div>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mt-6">
                        <div>
                            <label class="field-label">Hide products by search</label>
                            <p class="field-help mb-2">Search product name, SKU, or barcode. Hidden products disappear from product lists, POS, sales, purchase screens, and reports for normal users.</p>
                            <div class="flex gap-2">
                                <input type="text" id="hiddenProductSearch" autocomplete="off" placeholder="Type product name / SKU / barcode" class="text-field">
                                <button type="button" id="addHiddenProductBtn" class="px-4 py-2.5 bg-blue-600 text-white rounded-xl">Add</button>
                            </div>
                            <div id="hiddenProductResults" class="picker-results mt-2 border border-slate-200 rounded-xl bg-white hidden max-h-48 overflow-y-auto"></div>
                            <div id="hiddenProductsSelected" class="mt-2 flex flex-wrap gap-2"></div>
                            <input type="hidden" id="hiddenProductsInput" name="hidden_products" value="{{ implode(',', (array) ($controls['hidden_products'] ?? [])) }}">
                        </div>
                        <div class="space-y-4">
                            <div>
                                <label class="field-label">Hide product cost price ranges</label>
                                <input type="text" name="hidden_product_cost_price_ranges" value="{{ $rangeInputs['hidden_product_cost_price_ranges'] ?? '' }}" class="text-field" placeholder="Example: 0-50,120-200">
                            </div>
                            <div>
                                <label class="field-label">Hide product selling price ranges</label>
                                <input type="text" name="hidden_product_selling_price_ranges" value="{{ $rangeInputs['hidden_product_selling_price_ranges'] ?? '' }}" class="text-field" placeholder="Example: 0-100,250-400">
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @if($section === 'records')
                <div class="secret-panel rounded-2xl p-6">
                    <h3 class="text-xl font-black text-slate-900">Hide Records</h3>
                    <p class="text-sm text-slate-500 mt-1 mb-5">Use search to hide exact suppliers, customers, or sales bills. Hidden records are removed from normal users, admins, lists, dashboards, and reports. Super Admin still sees everything.</p>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                        @foreach($recordToggles as $key => [$title, $help])
                            {!! $toggle($key, $title, $help) !!}
                        @endforeach
                    </div>

                    <div class="mt-6">
                        <label class="field-label">Visible customer amount percentage</label>
                        <input type="number" min="0" max="100" step="1" name="customer_visible_percentage" value="{{ $controls['customer_visible_percentage'] ?? 100 }}" class="text-field">
                        <p class="field-help">Controls customer balances, purchase history, customer due reports, and customer payment views for normal users. Example: 50 shows half, 70 shows 70%.</p>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-4 gap-5 mt-6">
                        <div>
                            <label class="field-label">Hide suppliers</label>
                            <p class="field-help mb-2">Search by supplier name or phone number.</p>
                            <div class="flex gap-2">
                                <input type="text" id="hiddenSupplierSearch" autocomplete="off" placeholder="Supplier name / phone" class="text-field">
                                <button type="button" id="addHiddenSupplierBtn" class="px-4 py-2.5 bg-blue-600 text-white rounded-xl">Add</button>
                            </div>
                            <div id="hiddenSupplierResults" class="picker-results mt-2 border border-slate-200 rounded-xl bg-white hidden max-h-48 overflow-y-auto"></div>
                            <div id="hiddenSuppliersSelected" class="mt-2 flex flex-wrap gap-2"></div>
                            <input type="hidden" id="hiddenSuppliersInput" name="hidden_suppliers" value="{{ implode(',', (array) ($controls['hidden_suppliers'] ?? [])) }}">
                        </div>
                        <div>
                            <label class="field-label">Hide customers</label>
                            <p class="field-help mb-2">Search by customer name, phone, or email.</p>
                            <div class="flex gap-2">
                                <input type="text" id="hiddenCustomerSearch" autocomplete="off" placeholder="Customer name / phone" class="text-field">
                                <button type="button" id="addHiddenCustomerBtn" class="px-4 py-2.5 bg-blue-600 text-white rounded-xl">Add</button>
                            </div>
                            <div id="hiddenCustomerResults" class="picker-results mt-2 border border-slate-200 rounded-xl bg-white hidden max-h-48 overflow-y-auto"></div>
                            <div id="hiddenCustomersSelected" class="mt-2 flex flex-wrap gap-2"></div>
                            <input type="hidden" id="hiddenCustomersInput" name="hidden_customers" value="{{ implode(',', (array) ($controls['hidden_customers'] ?? [])) }}">
                        </div>
                        <div>
                            <label class="field-label">Hide sales bills</label>
                            <p class="field-help mb-2">Search by invoice number, bill ID, or customer name.</p>
                            <div class="flex gap-2">
                                <input type="text" id="hiddenSaleSearch" autocomplete="off" placeholder="Invoice / bill / customer" class="text-field">
                                <button type="button" id="addHiddenSaleBtn" class="px-4 py-2.5 bg-blue-600 text-white rounded-xl">Add</button>
                            </div>
                            <div id="hiddenSaleResults" class="picker-results mt-2 border border-slate-200 rounded-xl bg-white hidden max-h-48 overflow-y-auto"></div>
                            <div id="hiddenSalesSelected" class="mt-2 flex flex-wrap gap-2"></div>
                            <input type="hidden" id="hiddenSalesInput" name="hidden_sales" value="{{ implode(',', (array) ($controls['hidden_sales'] ?? [])) }}">
                        </div>
                        <div>
                            <label class="field-label">Hide stores</label>
                            <p class="field-help mb-2">Select stores to completely hide them and all their data.</p>
                            <div class="flex gap-2">
                                <select name="hidden_stores[]" multiple class="text-field" style="height: 120px;">
                                    @foreach($stores as $store)
                                        <option value="{{ $store->id }}" {{ in_array($store->id, $hiddenStoreIds ?? []) ? 'selected' : '' }}>
                                            {{ $store->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <p class="text-xs text-slate-500 mt-2">Hold Ctrl/Cmd to select multiple stores.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mt-6">
                        <div>
                            <label class="field-label">Hide sales amount ranges</label>
                            <input type="text" name="hidden_sales_price_ranges" value="{{ $rangeInputs['hidden_sales_price_ranges'] ?? '' }}" class="text-field" placeholder="Example: 0-100,500-1000">
                        </div>
                        <div>
                            <label class="field-label">Hide purchase amount ranges</label>
                            <input type="text" name="hidden_purchase_price_ranges" value="{{ $rangeInputs['hidden_purchase_price_ranges'] ?? '' }}" class="text-field" placeholder="Example: 100-500,1000-5000">
                        </div>
                        <div>
                            <label class="field-label">Hide customer purchase ranges</label>
                            <input type="text" name="hidden_customer_purchase_price_ranges" value="{{ $rangeInputs['hidden_customer_purchase_price_ranges'] ?? '' }}" class="text-field" placeholder="Example: 100-1000,3000-10000">
                        </div>
                    </div>
                </div>
            @endif

            @if($section === 'privacy')
                <div class="secret-panel rounded-2xl p-6">
                    <h3 class="text-xl font-black text-slate-900">Privacy Mode / Demo Mode</h3>
                    <p class="text-sm text-slate-500 mt-1 mb-5">This is only for temporary screen privacy. It never changes real sales, payments, invoice numbers, or reports in the database.</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {!! $toggle('privacy_mode_enabled', 'Enable Privacy Mode shortcut', 'Allows permitted POS users to switch the display mode from the POS screen.') !!}
                        <div>
                            <label class="field-label">Shortcut for Windows / Linux</label>
                            <input type="text" name="privacy_mode_shortcut" value="{{ $privacyModeSetting->shortcut_key ?? 'Alt+S' }}" class="text-field" placeholder="Example: Ctrl+Shift+H">
                        </div>
                        <div>
                            <label class="field-label">Shortcut for Mac</label>
                            <input type="text" name="privacy_mode_shortcut_mac" value="{{ $privacyModeSetting->shortcut_key_mac ?? 'Cmd+X' }}" class="text-field" placeholder="Example: Cmd+X">
                        </div>
                        <div>
                            <label class="field-label">Visible bills in Sales List (%)</label>
                            <input type="number" min="1" max="100" step="1" name="privacy_mode_limit" value="{{ $privacyModeSetting->visible_invoice_limit ?? 10 }}" class="text-field">
                        </div>
                        <div>
                            <label class="field-label">Sales List Percentage Mode</label>
                            <select name="sales_list_percentage_mode" class="text-field">
                                <option value="each_day" {{ ($privacyModeSetting->sales_list_percentage_mode ?? 'each_day') === 'each_day' ? 'selected' : '' }}>Each day</option>
                                <option value="all_matching_bills" {{ ($privacyModeSetting->sales_list_percentage_mode ?? 'each_day') === 'all_matching_bills' ? 'selected' : '' }}>All matching bills</option>
                            </select>
                        </div>
                        <div>
                            <label class="field-label">Amount masking style</label>
                            <select name="privacy_mode_mask_type" class="text-field">
                                <option value="hide" {{ ($privacyModeSetting->masking_type ?? 'hide') === 'hide' ? 'selected' : '' }}>Hide amount completely</option>
                                <option value="low_amount" {{ ($privacyModeSetting->masking_type ?? 'hide') === 'low_amount' ? 'selected' : '' }}>Show low display amount</option>
                                <option value="blur" {{ ($privacyModeSetting->masking_type ?? 'hide') === 'blur' ? 'selected' : '' }}>Show Rs ****</option>
                                <option value="hidden" {{ ($privacyModeSetting->masking_type ?? 'hide') === 'hidden' ? 'selected' : '' }}>Show Hidden</option>
                            </select>
                        </div>
                    </div>
                    <h4 class="text-sm font-black text-slate-900 mt-6 mb-3">Where Privacy Mode applies</h4>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                        {!! $toggle('privacy_mode_pos', 'POS screen', 'Allow shortcut behavior on POS screen.') !!}
                        {!! $toggle('privacy_mode_sales', 'Sales List', 'Apply bill count and display labels to Sales List.') !!}
                        {!! $toggle('privacy_mode_reports', 'Reports', 'Apply configured report masking rules.') !!}
                        {!! $toggle('privacy_mode_dashboard', 'Dashboard widgets', 'Apply dashboard widget masking rules.') !!}
                        {!! $toggle('privacy_mode_customer', 'Customer purchase history', 'Apply configured customer history display rules.') !!}
                    </div>
                </div>
            @endif

            <div class="sticky bottom-4 z-10">
                <div class="bg-slate-900/95 text-white rounded-2xl shadow-2xl px-4 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <p class="text-sm text-slate-200">Saving affects normal users and admins immediately. Super Admin stays unrestricted.</p>
                    <button type="submit" class="px-6 py-2.5 bg-emerald-500 text-slate-900 font-bold rounded-xl hover:bg-emerald-400 transition">Save This Section</button>
                </div>
            </div>
        </form>
    @endif
</div>

<script>
(() => {
    const initialProducts = @json($hiddenProducts ?? []);
    const initialSuppliers = @json($hiddenSuppliers ?? []);
    const initialCustomers = @json($hiddenCustomers ?? []);
    const initialSales = @json($hiddenSales ?? []);

    function setupPicker(config) {
        const searchInput = document.getElementById(config.searchInputId);
        const addBtn = document.getElementById(config.addBtnId);
        const resultsBox = document.getElementById(config.resultsBoxId);
        const selectedBox = document.getElementById(config.selectedBoxId);
        const hiddenInput = document.getElementById(config.hiddenInputId);

        if (!searchInput || !addBtn || !resultsBox || !selectedBox || !hiddenInput) return;

        const selectedMap = new Map();
        (config.initialItems || []).forEach(item => {
            if (item && item.id) selectedMap.set(String(item.id), item.label || String(item.id));
        });

        let latestResults = [];
        let highlightedIndex = -1;
        let debounceTimer = null;

        function escapeHtml(value) {
            return String(value || '').replace(/[&<>"']/g, char => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
            }[char]));
        }

        function syncHiddenInput() {
            hiddenInput.value = Array.from(selectedMap.keys()).join(',');
        }

        function renderSelected() {
            selectedBox.innerHTML = '';
            if (selectedMap.size === 0) {
                selectedBox.innerHTML = '<span class="text-xs text-slate-500">No items added yet.</span>';
                syncHiddenInput();
                return;
            }

            selectedMap.forEach((label, id) => {
                const chip = document.createElement('span');
                chip.className = 'inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-50 text-blue-800 text-xs border border-blue-200';
                chip.innerHTML = `<span>${escapeHtml(label)}</span><button type="button" data-remove-id="${escapeHtml(id)}" class="text-red-600 hover:text-red-800 font-bold">x</button>`;
                selectedBox.appendChild(chip);
            });
            syncHiddenInput();
        }

        function hideResults() {
            resultsBox.classList.add('hidden');
            resultsBox.innerHTML = '';
            highlightedIndex = -1;
        }

        function renderResults(items) {
            latestResults = items;
            if (!items.length) {
                resultsBox.innerHTML = '<div class="px-3 py-2 text-xs text-slate-500">No results found.</div>';
                resultsBox.classList.remove('hidden');
                highlightedIndex = -1;
                return;
            }

            resultsBox.innerHTML = items.map((item, index) => (
                `<button type="button" data-index="${index}" class="w-full text-left px-3 py-2 text-sm hover:bg-slate-100 border-b last:border-b-0">${escapeHtml(item.label)}</button>`
            )).join('');
            resultsBox.classList.remove('hidden');
            highlightedIndex = 0;
        }

        function addItem(item) {
            if (!item || !item.id) return;
            selectedMap.set(String(item.id), item.label || String(item.id));
            renderSelected();
            searchInput.value = '';
            latestResults = [];
            hideResults();
        }

        async function doSearch() {
            const q = searchInput.value.trim();
            if (q.length < 1) {
                hideResults();
                return;
            }

            try {
                const resp = await fetch(`${config.searchUrl}?q=${encodeURIComponent(q)}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await resp.json();
                renderResults(Array.isArray(data.items) ? data.items : []);
            } catch (e) {
                resultsBox.innerHTML = '<div class="px-3 py-2 text-xs text-red-600">Search failed. Try again.</div>';
                resultsBox.classList.remove('hidden');
            }
        }

        searchInput.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(doSearch, 250);
        });

        searchInput.addEventListener('keydown', (e) => {
            if (resultsBox.classList.contains('hidden') || latestResults.length === 0) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                highlightedIndex = Math.min(latestResults.length - 1, highlightedIndex + 1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                highlightedIndex = Math.max(0, highlightedIndex - 1);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (latestResults[highlightedIndex]) addItem(latestResults[highlightedIndex]);
            }

            [...resultsBox.querySelectorAll('button[data-index]')].forEach((el, i) => {
                el.classList.toggle('bg-slate-100', i === highlightedIndex);
            });
        });

        addBtn.addEventListener('click', () => {
            if (latestResults[0]) addItem(latestResults[0]);
        });

        resultsBox.addEventListener('click', (e) => {
            const btn = e.target.closest('button[data-index]');
            if (!btn) return;
            const index = Number(btn.getAttribute('data-index'));
            if (latestResults[index]) addItem(latestResults[index]);
        });

        selectedBox.addEventListener('click', (e) => {
            const btn = e.target.closest('button[data-remove-id]');
            if (!btn) return;
            selectedMap.delete(String(btn.getAttribute('data-remove-id')));
            renderSelected();
        });

        document.addEventListener('click', (e) => {
            if (!resultsBox.contains(e.target) && e.target !== searchInput) hideResults();
        });

        renderSelected();
    }

    setupPicker({
        searchInputId: 'hiddenProductSearch',
        addBtnId: 'addHiddenProductBtn',
        resultsBoxId: 'hiddenProductResults',
        selectedBoxId: 'hiddenProductsSelected',
        hiddenInputId: 'hiddenProductsInput',
        searchUrl: @json(route('fun.search.products')),
        initialItems: initialProducts,
    });

    setupPicker({
        searchInputId: 'hiddenSupplierSearch',
        addBtnId: 'addHiddenSupplierBtn',
        resultsBoxId: 'hiddenSupplierResults',
        selectedBoxId: 'hiddenSuppliersSelected',
        hiddenInputId: 'hiddenSuppliersInput',
        searchUrl: @json(route('fun.search.suppliers')),
        initialItems: initialSuppliers,
    });

    setupPicker({
        searchInputId: 'hiddenCustomerSearch',
        addBtnId: 'addHiddenCustomerBtn',
        resultsBoxId: 'hiddenCustomerResults',
        selectedBoxId: 'hiddenCustomersSelected',
        hiddenInputId: 'hiddenCustomersInput',
        searchUrl: @json(route('fun.search.customers')),
        initialItems: initialCustomers,
    });

    setupPicker({
        searchInputId: 'hiddenSaleSearch',
        addBtnId: 'addHiddenSaleBtn',
        resultsBoxId: 'hiddenSaleResults',
        selectedBoxId: 'hiddenSalesSelected',
        hiddenInputId: 'hiddenSalesInput',
        searchUrl: @json(route('fun.search.sales')),
        initialItems: initialSales,
    });
})();
</script>
@endsection
