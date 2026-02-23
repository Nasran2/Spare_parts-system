@extends('layouts.app')

@section('title', 'POS System')
@section('page-title', 'Point of Sale')

@section('content')

@php
    $posLayout = $posLayout ?? \App\Models\Setting::get('pos_layout', 'default');
    $allUnits = $allUnits ?? \App\Models\Unit::where('is_active', true)
        ->orderBy('name')
        ->get(['id', 'name', 'short_name']);
@endphp

@if($posLayout === 'modern')
    <style>
        /* Fullscreen POS overrides (only for Modern layout) */
        aside.sidebar,
        header,
        footer,
        .floating-pos-btn { display: none !important; }

        main.flex-1 { padding: 0 !important; }
        /* Layout wrapper still reserves sidebar space; reset it */
        .md\:ml-64 { margin-left: 0 !important; }
        main.flex-1 .container,
        main.flex-1 .max-w-7xl,
        main.flex-1 .max-w-screen-xl {
            max-width: 100% !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }
        body { overflow: hidden; }
    </style>

    <div class="h-[100dvh] bg-slate-50 flex flex-col">

        <!-- Modern Sidebar (off-canvas) -->
        <div id="modern-sidebar" class="fixed inset-0 z-[60] hidden">
            <div class="absolute inset-0 bg-black/40" data-close-modern-sidebar></div>
            <div class="absolute left-0 top-0 h-full w-[290px] bg-white shadow-2xl border-r border-slate-200 p-4 flex flex-col">
                <div class="flex items-center justify-between">
                    <div class="text-sm font-extrabold text-slate-900">MENU</div>
                    <button type="button" class="h-9 w-9 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-700" data-close-modern-sidebar title="Close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="mt-4 space-y-2">
                    <a href="{{ route('dashboard') }}" class="w-full flex items-center gap-3 px-3 py-2 rounded-xl hover:bg-slate-50 text-slate-800">
                        <i class="fas fa-home w-5 text-slate-500"></i>
                        <span class="font-semibold">Home</span>
                    </a>
                    <a href="{{ route('settings.pos') }}" class="w-full flex items-center gap-3 px-3 py-2 rounded-xl hover:bg-slate-50 text-slate-800">
                        <i class="fas fa-cog w-5 text-slate-500"></i>
                        <span class="font-semibold">POS Settings</span>
                    </a>
                    <a href="{{ route('expenses.create') }}" class="w-full flex items-center gap-3 px-3 py-2 rounded-xl hover:bg-slate-50 text-slate-800">
                        <i class="fas fa-receipt w-5 text-slate-500"></i>
                        <span class="font-semibold">Add Expense</span>
                    </a>
                </div>

                <div class="mt-auto pt-4 border-t border-slate-200 text-xs text-slate-500">
                    <div class="font-semibold text-slate-700">{{ \App\Models\Setting::get('shop_name', 'Vehicle POS System') }}</div>
                    <div>Modern POS</div>
                </div>
            </div>
        </div>

        <!-- Header (Modern) -->
        <div class="bg-white border-b border-slate-200 px-3 md:px-6 py-2">
            <div class="flex flex-col xl:flex-row xl:items-center gap-2">
                <div class="flex items-center gap-2">
                    <button id="btn-modern-menu" type="button" class="h-10 w-10 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 flex items-center justify-center" title="Menu">
                        <i class="fas fa-bars"></i>
                    </button>

                    <div class="flex items-center gap-2">
                        <div class="text-sm text-slate-500">Location:</div>
                        <select id="pos-location" class="h-10 px-3 rounded-xl border border-slate-200 bg-slate-50 text-sm focus:ring-2 focus:ring-indigo-500">
                            <option>Default Shop</option>
                        </select>
                    </div>

                    <div id="pos-clock" class="h-10 px-4 rounded-xl bg-indigo-600 text-white flex items-center gap-2 text-sm font-semibold shadow-sm">
                        <i class="far fa-calendar-alt"></i>
                        <span id="pos-clock-text"></span>
                    </div>
                </div>

                <div class="flex-1"></div>

                <div class="flex items-center justify-between xl:justify-end gap-2">
                    <div class="flex items-center gap-2">
                        <a href="{{ route('dashboard') }}" class="h-10 w-10 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 flex items-center justify-center" title="Home">
                            <i class="fas fa-home"></i>
                        </a>
                        <button id="btn-modern-holds" type="button" class="h-10 w-10 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-rose-600 flex items-center justify-center" title="Suspend / Holds">
                            <i class="fas fa-pause"></i>
                        </button>
                        <button id="btn-modern-print" type="button" class="h-10 w-10 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 flex items-center justify-center" title="Print">
                            <i class="fas fa-print"></i>
                        </button>
                        <button id="btn-modern-return" type="button" class="h-10 w-10 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-amber-600 flex items-center justify-center" title="Return">
                            <i class="fas fa-undo"></i>
                        </button>
                        <button id="btn-modern-customers" type="button" class="h-10 w-10 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 flex items-center justify-center" title="Customer">
                            <i class="fas fa-user"></i>
                        </button>
                    </div>

                    <a href="{{ route('expenses.create') }}" class="h-10 px-4 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-800 text-sm font-semibold flex items-center gap-2">
                        <i class="fas fa-plus"></i>
                        <span>Add Expense</span>
                    </a>
                </div>
            </div>

            <div class="mt-3 flex flex-col lg:flex-row lg:items-center gap-3">
                <div class="flex items-center gap-2 w-full lg:w-[420px]">
                    <select id="customer-select" class="flex-1 h-11 px-4 border border-slate-200 bg-slate-50 rounded-2xl focus:ring-2 focus:ring-indigo-500">
                        <option value="">Walk-in Customer</option>
                        @isset($customers)
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        @endisset
                    </select>
                    <button id="btn-new-customer" type="button" class="h-11 w-11 bg-indigo-600 text-white rounded-2xl hover:bg-indigo-700" title="Add New Customer">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>

                <div class="flex-1"></div>

                <div class="w-full lg:w-[720px]">
                    <div class="flex items-stretch border-2 border-indigo-500 rounded-2xl overflow-hidden bg-white shadow-sm">
                        <div class="relative flex-1">
                            <input
                                id="product-search"
                                type="text"
                                placeholder="Enter Product name / SKU / Scan bar code"
                                class="w-full h-11 pl-12 pr-4 border-0 focus:ring-0"
                            >
                            <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        </div>
                        <button type="button" class="w-12 bg-indigo-600 text-white hover:bg-indigo-700" title="Search">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="flex-1 overflow-hidden grid grid-cols-1 grid-rows-2 lg:grid-rows-1 lg:grid-cols-12 bg-slate-100">
            <!-- Cart (left) -->
            <div id="pos-cart-panel" class="lg:col-span-9 bg-white lg:border-r border-slate-200 flex flex-col overflow-hidden">
                <div class="px-3 md:px-4 py-2 border-b border-slate-200 flex items-center text-xs font-semibold text-slate-500 bg-white">
                    <div class="w-[55%]">PRODUCT</div>
                    <div class="w-[25%] text-center">QUANTITY</div>
                    <div class="w-[20%] text-right">SUBTOTAL</div>
                </div>
                <div class="flex-1 overflow-y-auto">
                    <div id="cart-items" class="divide-y divide-slate-100"></div>
                </div>

                <div class="border-t border-slate-200 bg-slate-50 px-3 md:px-4 py-3">
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-2 text-sm">
                        <div class="text-slate-600">Items: <span id="items-count" class="font-semibold">0</span></div>
                        <div class="text-slate-600">Subtotal: <span id="subtotal" class="font-semibold">{{ $currency }} 0.00</span></div>
                        <div class="text-slate-600">Discount: <span id="discount-amount" class="font-semibold text-red-600">{{ $currency }} 0.00</span></div>
                        <div class="text-slate-600">Tax: <span id="tax-amount" class="font-semibold">{{ $currency }} 0.00</span></div>
                        <div class="text-slate-600">Total: <span id="total" class="font-bold text-indigo-700">{{ $currency }} 0.00</span></div>
                    </div>

                    <div class="mt-3 flex flex-col md:flex-row md:items-end gap-2">
                        <div class="flex items-center gap-3">
                            <label class="inline-flex items-center gap-2 text-xs text-slate-700">
                                <input type="radio" name="discount_type" value="fixed" class="discount-type" checked>
                                <span>Fixed</span>
                            </label>
                            <label class="inline-flex items-center gap-2 text-xs text-slate-700">
                                <input type="radio" name="discount_type" value="percent" class="discount-type">
                                <span>Percent</span>
                            </label>
                        </div>
                        <input id="discount-value" type="number" step="0.01" min="0" class="w-full md:w-56 px-3 py-2 border border-slate-300 rounded-lg" placeholder="Discount value">
                    </div>
                    <div id="card-fee-row" class="mt-2 text-sm flex justify-between hidden">
                        <span id="card-fee-label" class="text-slate-600">Card Fee</span>
                        <span id="card-fee-amount" class="font-semibold">{{ $currency }} 0.00</span>
                    </div>
                    <div id="payable-row" class="mt-1 text-sm flex justify-between">
                        <span class="font-semibold text-slate-800">Total Payable</span>
                        <span id="total-payable" class="font-bold text-slate-900">{{ $currency }} 0.00</span>
                    </div>
                </div>
            </div>

            <!-- Products (right) -->
            <div id="pos-products-panel" class="lg:col-span-3 bg-slate-100 flex flex-col overflow-hidden">
                <div class="p-3 border-b border-slate-200 bg-white">
                    <button type="button" class="w-full bg-indigo-600 text-white rounded-xl py-2.5 text-sm font-semibold flex items-center justify-center gap-2 shadow-sm">
                        <i class="fas fa-layer-group"></i>
                        <span>All Categories</span>
                    </button>
                    <div id="pos-category-tabs" class="mt-3 flex gap-2 overflow-x-auto pb-1"></div>
                </div>
                <div class="flex-1 overflow-y-auto p-3">
                    <div id="product-grid" class="grid grid-cols-2 gap-3"></div>
                </div>
            </div>
        </div>

        <!-- Bottom Bar -->
        <div class="bg-white border-t border-slate-200 px-3 md:px-4 py-2">
            <div class="lg:hidden grid grid-cols-2 gap-2 mb-2">
                <button id="btn-view-products" type="button" class="h-11 rounded-xl border border-slate-200 bg-white text-slate-800 font-semibold flex items-center justify-center gap-2">
                    <i class="fas fa-th-large text-slate-500"></i>
                    <span>Products</span>
                </button>
                <button id="btn-view-cart" type="button" class="h-11 rounded-xl border border-slate-200 bg-white text-slate-800 font-semibold flex items-center justify-center gap-2">
                    <i class="fas fa-shopping-cart text-slate-500"></i>
                    <span>Cart</span>
                    <span id="mobile-cart-badge" class="ml-1 inline-flex items-center justify-center min-w-[26px] h-6 px-2 rounded-full bg-indigo-600 text-white text-xs font-bold">0</span>
                </button>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-2 items-center">
                <div class="lg:col-span-4 flex items-center gap-2 flex-wrap">
                    <button id="btn-draft" class="px-3 py-2 text-sm bg-white border border-slate-200 text-slate-700 rounded-lg hover:bg-slate-50" title="Save as Quotation">
                        <i class="fas fa-file-alt mr-1 text-slate-500"></i>Quotation
                    </button>
                    <button id="btn-print-quotation" class="px-3 py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 hidden" title="Print quotation">
                        <i class="fas fa-print mr-1"></i>Print
                    </button>
                    <button id="btn-open-holds" class="px-3 py-2 text-sm bg-white border border-slate-200 text-slate-700 rounded-lg hover:bg-slate-50" title="Suspend / Holds">
                        <i class="fas fa-pause mr-1 text-slate-500"></i>Suspend (<span id="hold-count">0</span>)
                    </button>
                    <button id="btn-return-mode" class="px-3 py-2 text-sm bg-white border border-slate-200 text-slate-700 rounded-lg hover:bg-slate-50" title="Return / Exchange">
                        <i class="fas fa-undo mr-1 text-slate-500"></i>Return
                    </button>
                </div>

                <div class="lg:col-span-5 flex items-center justify-start lg:justify-center gap-2 flex-wrap">
                    <button id="btn-pay-multi" type="button" class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 shadow-sm" title="Multiple Pay">
                        <i class="fas fa-layer-group mr-1"></i>Multiple Pay
                    </button>
                    <button id="btn-pay-cash" type="button" class="px-5 py-2 text-sm bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 shadow-sm">
                        <i class="fas fa-money-bill mr-1"></i>Cash
                    </button>
                    <button id="btn-pay-card" type="button" class="px-5 py-2 text-sm bg-slate-900 text-white rounded-lg hover:bg-slate-800 shadow-sm">
                        <i class="fas fa-credit-card mr-1"></i>Card
                    </button>
                    <button id="btn-clear" class="px-5 py-2 text-sm bg-rose-600 text-white rounded-lg hover:bg-rose-700 shadow-sm" title="Clear Cart">
                        <i class="fas fa-times mr-1"></i>Cancel
                    </button>
                </div>

                <div class="lg:col-span-3 flex items-center justify-between lg:justify-end gap-3">
                    <div class="text-right">
                        <div class="text-xs text-slate-500">TOTAL PAYABLE</div>
                        <div id="total-payable-bottom" class="text-2xl font-extrabold text-slate-900">{{ $currency }} 0.00</div>
                    </div>
                    <button type="button" class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 shadow-sm">
                        <i class="fas fa-clock mr-1"></i>Recent Transactions
                    </button>
                </div>
            </div>
        </div>

        <!-- Hidden inputs for compatibility with existing JS -->
        <select id="payment-method" class="hidden">
            <option value="cash">Cash</option>
            <option value="card">Card</option>
        </select>
        <input id="cash-amount" type="number" class="hidden" value="0">
        <div id="change-display" class="hidden"></div>
        <div id="due-display" class="hidden"></div>
        <span id="change-amount" class="hidden"></span>
        <span id="due-amount" class="hidden"></span>
        <div id="customer-due" class="hidden"></div>
        <span id="customer-due-amount" class="hidden"></span>
        <button id="btn-checkout" class="hidden"></button>
        <button id="btn-hold" class="hidden"></button>
    </div>

@else

<div class="space-y-4">
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Products Section -->
        <div class="lg:col-span-2 space-y-4">
            <!-- Search -->
            <div class="bg-white rounded-xl shadow-md p-4">
                <div class="relative">
                    <input 
                        id="product-search"
                        type="text" 
                        placeholder="Search products by name, SKU, or barcode..."
                        class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                </div>
            </div>

            <!-- Products Grid -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">
                    <i class="fas fa-box text-blue-600 mr-2"></i>Products
                </h3>
                @php
                    // Provided by POSController@index for both layouts
                    $productPayload = $productPayload ?? collect();
                @endphp
                <div id="product-grid" class="grid grid-cols-2 md:grid-cols-3 gap-4 max-h-[600px] overflow-y-auto">
                    <div class="col-span-full text-center py-12 text-gray-500">
                        <i class="fas fa-spinner fa-spin text-blue-500 text-3xl mb-2"></i>
                        Loading products...
                    </div>
                </div>
            </div>
        </div>

        <!-- Cart Section -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-md p-6 sticky top-4">
                <h3 class="text-lg font-bold text-gray-800 mb-4">
                    <i class="fas fa-shopping-cart text-green-600 mr-2"></i>Cart
                </h3>

                <!-- Cart Items -->
                <div id="cart-items" class="space-y-3 mb-4 max-h-64 overflow-y-auto"></div>

                <!-- Cart Summary -->
                <div class="border-t border-gray-200 pt-4 space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Subtotal:</span>
                        <span id="subtotal" class="font-semibold">{{ $currency }} 0.00</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Tax (0%):</span>
                        <span id="tax-amount" class="font-semibold">{{ $currency }} 0.00</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Discount:</span>
                        <span id="discount-amount" class="font-semibold text-red-600">{{ $currency }} 0.00</span>
                    </div>
                    <div class="border-t border-gray-200 pt-2 flex justify-between">
                        <span class="font-bold text-lg">Total:</span>
                        <span id="total" class="font-bold text-2xl text-blue-600">{{ $currency }} 0.00</span>
                    </div>
                    <div id="card-fee-row" class="flex justify-between text-sm hidden">
                        <span id="card-fee-label" class="text-gray-600">Card Fee:</span>
                        <span id="card-fee-amount" class="font-semibold">{{ $currency }} 0.00</span>
                    </div>
                    <div id="payable-row" class="pt-1 flex justify-between">
                        <span class="font-bold text-sm">Total Payable:</span>
                        <span id="total-payable" class="font-bold text-lg text-gray-900">{{ $currency }} 0.00</span>
                    </div>
                </div>

                <!-- Customer Selection -->
                <div class="mt-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Customer</label>
                    <div class="flex items-center space-x-2 mb-2">
                        <label class="text-xs font-semibold text-gray-600">Customer</label>
                        <button id="btn-new-customer" type="button" class="text-xs px-2 py-1 bg-emerald-100 text-emerald-700 rounded hover:bg-emerald-200" title="Add New Customer">
                            <i class="fas fa-user-plus"></i>
                        </button>
                    </div>
                    <select id="customer-select" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">Walk-in Customer</option>
                        @isset($customers)
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        @endisset
                    </select>
                    <div id="customer-due" class="mt-2 hidden text-sm p-2 rounded border bg-yellow-50 border-yellow-200 text-yellow-800">
                        Outstanding Due: <span id="customer-due-amount">{{ $currency }} 0.00</span>
                    </div>
                </div>

                <!-- Discount Controls -->
                <div class="mt-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Discount</label>
                    <div class="flex items-center space-x-3 mb-2">
                        <label class="inline-flex items-center space-x-2">
                            <input type="radio" name="discount_type" value="fixed" class="discount-type" checked>
                            <span>Fixed</span>
                        </label>
                        <label class="inline-flex items-center space-x-2">
                            <input type="radio" name="discount_type" value="percent" class="discount-type">
                            <span>Percent</span>
                        </label>
                    </div>
                    <input type="number" step="0.01" min="0" id="discount-value" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="Enter discount value">
                    <p class="text-xs text-gray-500 mt-1">When Percent is selected, value is treated as % of subtotal.</p>
                </div>

                <!-- Payment Section -->
                <div class="mt-4 border-2 border-emerald-300 rounded-xl bg-emerald-50 p-4">
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-bold text-emerald-800">Payment</label>
                        <span class="text-xs text-emerald-700">Highlight</span>
                    </div>
                    <label class="block text-xs font-semibold text-emerald-800 mb-1">Payment Method</label>
                    <select id="payment-method" class="w-full mb-2 px-3 py-2 border-2 border-emerald-300 rounded-lg focus:ring-2 focus:ring-emerald-500">
                        <option value="cash">Cash</option>
                        <option value="card">Card</option>
                    </select>
                    <input type="number" step="0.01" min="0" id="cash-amount" class="w-full px-3 py-2 border-2 border-emerald-300 rounded-lg focus:ring-2 focus:ring-emerald-500" placeholder="Enter cash amount">
                    <div class="grid grid-cols-2 gap-2 mt-3">
                        <div id="change-display" class="p-2 bg-green-50 border border-green-200 rounded-lg hidden">
                            <div class="flex justify-between items-center">
                                <span class="text-xs font-semibold text-gray-700">Change</span>
                                <span id="change-amount" class="text-base font-bold text-green-600">Rs 0.00</span>
                            </div>
                        </div>
                        <div id="due-display" class="p-2 bg-amber-50 border border-amber-200 rounded-lg hidden">
                            <div class="flex justify-between items-center">
                                <span class="text-xs font-semibold text-gray-700">Due</span>
                                <span id="due-amount" class="text-base font-bold text-amber-600">Rs 0.00</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="mt-6 space-y-2">
                    <button id="btn-checkout" class="w-full py-3 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-lg hover:from-green-700 hover:to-green-800 transition shadow-lg font-semibold">
                        <i class="fas fa-check-circle mr-2"></i>Complete Sale
                    </button>
                    <button id="btn-hold" class="w-full py-2 bg-amber-100 text-amber-800 rounded-lg hover:bg-amber-200 transition font-semibold">
                        <i class="fas fa-pause-circle mr-2"></i>Hold Bill
                    </button>
                    <button id="btn-open-holds" class="w-full py-2 bg-blue-50 text-blue-800 rounded-lg hover:bg-blue-100 transition font-semibold">
                        <i class="fas fa-archive mr-2"></i>Held Bills (<span id="hold-count">0</span>)
                    </button>
                    <button id="btn-return-mode" class="w-full py-2 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition" title="Load a previous sale for return/exchange">
                        <i class="fas fa-undo mr-2"></i>Return / Exchange
                    </button>
                    <button id="btn-draft" class="w-full py-2 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition" title="Save cart as a Quotation without affecting stock">
                        <i class="fas fa-file-invoice mr-2"></i>Save as Quotation
                    </button>
                    <button id="btn-print-quotation" class="w-full py-2 bg-indigo-500 text-white rounded-lg hover:bg-indigo-600 transition hidden" title="Print the last saved quotation">
                        <i class="fas fa-print mr-2"></i>Print Quotation
                    </button>
                    <button id="btn-clear" class="w-full py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                        <i class="fas fa-times mr-2"></i>Clear Cart
                    </button>
                </div>
            </div>
        </div>

    </div>

</div>

@endif

<!-- Multiple Pay Modal -->
<div id="multi-pay-modal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-3xl mx-4 overflow-hidden">
        <div class="p-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
            <h3 class="text-lg font-bold text-gray-800">Payment</h3>
            <button class="text-gray-500 hover:text-gray-700" data-close-multi-pay>
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="p-5 grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="lg:col-span-2 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Amount*</label>
                        <input id="multi-pay-amount" type="text" readonly class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50" value="{{ $currency }} 0.00">
                        <div id="multi-pay-card-fee" class="mt-2 text-xs text-gray-600 hidden">
                            <span class="font-semibold">Card Fee:</span> <span id="multi-pay-card-fee-amount">{{ $currency }} 0.00</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Total Paying*</label>
                        <input id="multi-pay-total-paying" type="text" readonly class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50" value="{{ $currency }} 0.00">
                    </div>
                </div>

                <div class="border rounded-lg overflow-hidden">
                    <div class="px-4 py-2 bg-gray-50 border-b flex items-center justify-between">
                        <div class="text-sm font-semibold text-gray-700">Split Payments</div>
                        <button type="button" id="btn-add-payment-row" class="text-sm px-3 py-1.5 bg-indigo-600 text-white rounded hover:bg-indigo-700">
                            <i class="fas fa-plus mr-1"></i>Add
                        </button>
                    </div>
                    <div id="multi-pay-rows" class="p-4 space-y-3"></div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Sell note</label>
                        <textarea id="multi-pay-sell-note" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="Sell note"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Staff note</label>
                        <textarea id="multi-pay-staff-note" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="Staff note"></textarea>
                    </div>
                </div>
            </div>

            <div class="bg-slate-50 border border-slate-200 rounded-lg p-4 space-y-3">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Total Items:</span>
                    <span id="multi-pay-items" class="font-semibold">0</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Total Payable:</span>
                    <span id="multi-pay-payable" class="font-semibold text-indigo-700">{{ $currency }} 0.00</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Change Return:</span>
                    <span id="multi-pay-change" class="font-semibold text-emerald-700">{{ $currency }} 0.00</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Balance:</span>
                    <span id="multi-pay-balance" class="font-semibold text-red-700">{{ $currency }} 0.00</span>
                </div>

                <div class="pt-3 flex items-center justify-end gap-2">
                    <button type="button" data-close-multi-pay class="px-4 py-2 bg-gray-100 text-gray-700 rounded hover:bg-gray-200">Close</button>
                    <button type="button" id="btn-finalize-multi-pay" class="px-4 py-2 bg-emerald-600 text-white rounded hover:bg-emerald-700">
                        Finalize Payment
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Return/Exchange Modal -->
<div id="return-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl mx-4 overflow-hidden">
        <div class="p-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
            <h3 class="text-lg font-bold text-gray-800">Return / Exchange</h3>
            <button id="close-return-modal" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-6 space-y-4">
            <div class="flex space-x-2">
                <input type="text" id="return-search-term" placeholder="Enter Sale ID or Invoice Number (e.g. INV-2025...)" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                <button id="btn-search-sale" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    Search
                </button>
            </div>
            
            <div id="return-search-results" class="hidden">
                <div class="bg-blue-50 p-3 rounded-lg mb-3 text-sm text-blue-800 flex justify-between">
                    <span id="return-sale-info"></span>
                </div>
                <div class="overflow-y-auto max-h-64 border rounded-lg">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-gray-100 text-gray-600 font-semibold">
                            <tr>
                                <th class="px-4 py-2">Product</th>
                                <th class="px-4 py-2 text-right">Sold</th>
                                <th class="px-4 py-2 text-right">Returned</th>
                                <th class="px-4 py-2 text-right">Remaining</th>
                                <th class="px-4 py-2 text-right">Price</th>
                                <th class="px-4 py-2">Action</th>
                            </tr>
                        </thead>
                        <tbody id="return-items-list" class="divide-y divide-gray-200">
                            <!-- Items will be injected here -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hold Bills Modal -->
<div id="hold-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl mx-4 overflow-hidden">
        <div class="p-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
            <h3 class="text-lg font-bold text-gray-800">Held Bills</h3>
            <button class="text-gray-500 hover:text-gray-700" data-close-hold>
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-6 space-y-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Hold Label</label>
                <input id="hold-label" type="text" class="w-full px-4 py-2 border rounded-lg" placeholder="Optional label (e.g. Customer name)">
            </div>
            <button id="confirm-hold" class="w-full py-3 bg-amber-500 text-white rounded-lg hover:bg-amber-600 transition font-semibold">
                <i class="fas fa-pause-circle mr-2"></i>Hold Current Bill
            </button>
            <div class="border-t border-gray-200 pt-4">
                <h4 class="text-sm font-semibold text-gray-700 mb-3">Existing Holds</h4>
                <div id="hold-list" class="space-y-3 max-h-64 overflow-y-auto">
                    <div class="text-sm text-gray-500 text-center py-6">
                        <i class="fas fa-archive text-3xl mb-2"></i>
                        No holds yet
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Item Discount / Edit Modal -->
<div id="item-discount-modal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg mx-4 overflow-hidden">
        <div class="p-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
            <div>
                <h3 class="text-lg font-bold text-gray-800">Item Discount</h3>
                <div id="item-discount-product" class="text-xs text-gray-600 mt-0.5"></div>
            </div>
            <button class="text-gray-500 hover:text-gray-700" data-close-item-discount>
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-5 space-y-4">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Unit Price</label>
                    <input id="item-discount-unit-price" type="number" step="0.01" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg" />
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Quantity</label>
                    <input id="item-discount-qty" type="text" readonly class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50" />
                </div>
            </div>

            <div id="item-discount-unit-wrap" class="hidden">
                <label class="block text-xs font-semibold text-gray-700 mb-1">Unit</label>
                <select id="item-discount-unit-id" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white"></select>
                <div class="text-[11px] text-slate-500 mt-1">Unit change will update the item price automatically unless you override Unit Price.</div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-2">Discount Type</label>
                <div class="grid grid-cols-2 gap-2">
                    <button type="button" id="item-discount-type-fixed" class="h-10 rounded-lg border border-slate-200 bg-white font-semibold">Fixed</button>
                    <button type="button" id="item-discount-type-percent" class="h-10 rounded-lg border border-slate-200 bg-white font-semibold">Percent</button>
                </div>
                <input id="item-discount-value" type="number" step="0.01" min="0" class="mt-2 w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="Discount value" />
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Description (optional)</label>
                <textarea id="item-discount-description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="e.g. Special offer / damaged pack"></textarea>
            </div>

            <div class="border rounded-lg p-3 bg-slate-50">
                <div class="flex justify-between text-sm">
                    <span class="text-slate-600">Line Subtotal</span>
                    <span id="item-discount-subtotal" class="font-semibold">{{ $currency }} 0.00</span>
                </div>
                <div class="flex justify-between text-sm mt-1">
                    <span class="text-slate-600">Line Discount</span>
                    <span id="item-discount-amount" class="font-semibold text-rose-600">{{ $currency }} 0.00</span>
                </div>
                <div class="flex justify-between text-sm mt-1">
                    <span class="text-slate-800 font-semibold">Line Total</span>
                    <span id="item-discount-total" class="font-bold text-slate-900">{{ $currency }} 0.00</span>
                </div>
            </div>
        </div>
        <div class="p-4 border-t border-gray-200 bg-white flex items-center justify-end gap-2">
            <button type="button" class="px-4 py-2 bg-gray-100 text-gray-700 rounded hover:bg-gray-200" data-close-item-discount>Cancel</button>
            <button type="button" id="btn-apply-item-discount" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 font-semibold">Apply</button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    // Defensive: wrap everything so syntax error inside template literals cannot break page
    (function(){
    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    function currency(v){ return '{{ $currency }} ' + Number(v).toFixed(2); }

    const POS_LAYOUT = @json($posLayout ?? 'default');
    const POS_CARD_FEE = @json($posCardFee ?? ['enabled' => false, 'rate' => 0, 'mode' => 'customer']);

    // Modern sidebar toggle
    (function bindModernSidebar(){
        const sidebar = document.getElementById('modern-sidebar');
        const openBtn = document.getElementById('btn-modern-menu');
        if (!sidebar || !openBtn) return;

        const open = () => {
            sidebar.classList.remove('hidden');
        };
        const close = () => {
            sidebar.classList.add('hidden');
        };

        openBtn.addEventListener('click', (e) => {
            e.preventDefault();
            open();
        });
        sidebar.querySelectorAll('[data-close-modern-sidebar]').forEach(el => {
            el.addEventListener('click', (e) => {
                e.preventDefault();
                close();
            });
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') close();
        });
    })();

    function readNumberFromText(text){
        return parseFloat(String(text || '').replace(/[^0-9.]/g,'') || '0');
    }

    function readBaseTotal(){
        const el = document.getElementById('total');
        return readNumberFromText(el?.textContent);
    }

    function getPaymentMethod(){
        const el = document.getElementById('payment-method');
        const v = (el?.value || 'cash').toLowerCase();
        return (v === 'card') ? 'card' : 'cash';
    }

    function computeCardFee(baseTotal, paymentMethod, cardUsedOverride = null){
        const total = Math.max(0, Number(baseTotal || 0));
        const cardUsed = (cardUsedOverride === null) ? (paymentMethod === 'card') : Boolean(cardUsedOverride);
        if (!cardUsed) return 0;
        if (!POS_CARD_FEE || !POS_CARD_FEE.enabled) return 0;
        const rate = Number(POS_CARD_FEE.rate || 0);
        if (!rate || rate <= 0) return 0;
        return Math.round((total * (rate / 100)) * 100) / 100;
    }

    function computeCardFeeAmount(amount){
        const total = Math.max(0, Number(amount || 0));
        if (!POS_CARD_FEE || !POS_CARD_FEE.enabled) return 0;
        const rate = Number(POS_CARD_FEE.rate || 0);
        if (!rate || rate <= 0) return 0;
        return Math.round((total * (rate / 100)) * 100) / 100;
    }

    function computePayableTotal(baseTotal, cardUsedOverride = null){
        const method = getPaymentMethod();
        const fee = computeCardFee(baseTotal, method, cardUsedOverride);
        const cardUsed = (cardUsedOverride === null) ? (method === 'card') : Boolean(cardUsedOverride);
        if (cardUsed && fee > 0 && (POS_CARD_FEE.mode || 'customer') === 'customer') {
            return Math.round((Number(baseTotal || 0) + fee) * 100) / 100;
        }
        return Math.round(Number(baseTotal || 0) * 100) / 100;
    }

    function computePayableTotalSplit(baseTotal, cardAmount){
        const fee = computeCardFeeAmount(cardAmount);
        if (cardAmount > 0 && fee > 0 && (POS_CARD_FEE.mode || 'customer') === 'customer') {
            return Math.round((Number(baseTotal || 0) + fee) * 100) / 100;
        }
        return Math.round(Number(baseTotal || 0) * 100) / 100;
    }

    function updatePayableUI(baseTotal, cardUsedOverride = null){
        const method = getPaymentMethod();
        const cardUsed = (cardUsedOverride === null) ? (method === 'card') : Boolean(cardUsedOverride);
        const fee = computeCardFee(baseTotal, method, cardUsedOverride);
        const feeRow = document.getElementById('card-fee-row');
        const feeAmount = document.getElementById('card-fee-amount');
        const feeLabel = document.getElementById('card-fee-label');
        const totalPayableEl = document.getElementById('total-payable');
        const totalPayableBottomEl = document.getElementById('total-payable-bottom');

        if (feeRow && feeAmount && feeLabel) {
            if (cardUsed && fee > 0) {
                feeRow.classList.remove('hidden');
                feeAmount.textContent = currency(fee);
                feeLabel.textContent = (POS_CARD_FEE.mode === 'seller') ? 'Card Fee (Seller):' : 'Card Fee:';
            } else {
                feeRow.classList.add('hidden');
            }
        }

        if (totalPayableEl) {
            totalPayableEl.textContent = currency(computePayableTotal(baseTotal, cardUsedOverride));
        }
        if (totalPayableBottomEl) {
            totalPayableBottomEl.textContent = currency(computePayableTotal(baseTotal, cardUsedOverride));
        }
    }

    // Modern layout clock
    (function initPosClock(){
        const el = document.getElementById('pos-clock-text') || document.getElementById('pos-clock');
        if (!el) return;
        const tick = () => {
            const d = new Date();
            el.textContent = d.toLocaleString();
        };
        tick();
        setInterval(tick, 1000);
    })();

    // Modern header actions
    (function initModernHeaderActions(){
        if ((POS_LAYOUT || 'default') !== 'modern') return;
        document.getElementById('btn-modern-holds')?.addEventListener('click', () => {
            document.getElementById('btn-open-holds')?.click();
        });
        document.getElementById('btn-modern-return')?.addEventListener('click', () => {
            document.getElementById('btn-return-mode')?.click();
        });
        document.getElementById('btn-modern-customers')?.addEventListener('click', () => {
            document.getElementById('customer-select')?.focus();
        });
        document.getElementById('btn-modern-print')?.addEventListener('click', async () => {
            if (window.__lastReceiptSaleData) {
                try {
                    await showPrintReceipt(window.__lastReceiptSaleData);
                    return;
                } catch (e) {
                    // fallthrough
                }
            }
            // If there is a quotation ready to print, use that.
            const printQuotationBtn = document.getElementById('btn-print-quotation');
            if (printQuotationBtn && !printQuotationBtn.classList.contains('hidden')) {
                printQuotationBtn.click();
                return;
            }
            showToast('warning', 'Nothing to print yet');
        });
    })();

    // Units map for quick lookup (populated from backend)
    const UNITS = @json($allUnits ?? []);
    window.unitsMap = {};
    UNITS.forEach(u => { window.unitsMap[u.id] = u; });

    const PRELOADED_PRODUCTS = @json($productPayload ?? []);
    const productGridElement = document.getElementById('product-grid');
    const productSearchInput = document.getElementById('product-search');
    const SEARCH_PRODUCTS_URL = '{{ route('pos.search-products') }}';
    let activeProductList = PRELOADED_PRODUCTS;

    // Enforce: Walk-in customer (no customer selected) cannot have due amount
    function updateCheckoutState(){
        const checkoutBtn = document.getElementById('btn-checkout');
        if(!checkoutBtn) return;
        const customerSelectEl = document.getElementById('customer-select');
        const isWalkIn = customerSelectEl && !customerSelectEl.value;
        const baseTotal = readBaseTotal();
        const payableTotal = computePayableTotal(baseTotal);
        const cashVal = parseFloat(document.getElementById('cash-amount')?.value || '0');
        const due = Math.max(0, payableTotal - cashVal);
        if(isWalkIn && due > 0){
            checkoutBtn.disabled = true;
            checkoutBtn.classList.add('opacity-50','cursor-not-allowed');
            checkoutBtn.setAttribute('title','Select a customer to allow due / partial payment');
        } else {
            checkoutBtn.disabled = false;
            checkoutBtn.classList.remove('opacity-50','cursor-not-allowed');
            checkoutBtn.removeAttribute('title');
        }
    }

    function renderCart(cart){
        window.__posCartSnapshot = cart;
        const itemsWrap = document.getElementById('cart-items');
        itemsWrap.innerHTML = '';
        const items = cart.items || {};
        if(Object.keys(items).length === 0){
            itemsWrap.innerHTML = `<div class="text-center py-8 text-gray-400">
                <i class="fas fa-shopping-cart text-4xl mb-2"></i>
                <p class="text-sm">Cart is empty</p>
            </div>`;
        } else {
            Object.values(items).forEach(it => {
                const row = document.createElement('div');
                const isReturn = it.qty < 0;
                const isOutOfStock = !isReturn && (typeof it.stock_quantity !== 'undefined') && Number(it.stock_quantity) <= 0;
                row.className = `grid grid-cols-12 items-center gap-3 px-3 md:px-4 py-3 border-b border-slate-100 ${isReturn ? 'bg-rose-50' : (isOutOfStock ? 'bg-rose-50' : 'bg-white')} hover:bg-slate-50 transition`;
                const cartKey = it.cart_key || it.id;
                
                // Build unit selection UI (disabled for returns)
                const vUnits = it.visible_units || [];
                let unitControl = '';
                if (!isReturn && vUnits.length > 1) {
                    unitControl = '<select class="unit-select text-xs border rounded px-2 py-1 bg-white" data-cart-key="' + cartKey + '\">' +
                        vUnits.map(uid => {
                            const u = window.unitsMap[uid];
                            if (!u) return '';
                            const sel = uid === it.unit_id ? 'selected' : '';
                            return '<option value="' + uid + '" ' + sel + '>' + (u.short_name || u.name) + '</option>';
                        }).join('') + '</select>';
                } else if (vUnits.length === 1 || isReturn) {
                    const u = it.unit_id ? window.unitsMap[it.unit_id] : null;
                    if (u) unitControl = '<span class="text-xs px-2 py-1 bg-blue-50 border border-blue-200 rounded">' + (u.short_name || u.name) + '</span>';
                }
                
                // Quantity Controls
                let qtyControls = '';
                if (isReturn) {
                    qtyControls = `
                        <div class="flex items-center gap-2 justify-center">
                            <span class="text-xs font-bold text-rose-600 uppercase">Return</span>
                            <span class="font-mono font-bold text-rose-700">${it.qty}</span>
                        </div>
                    `;
                } else {
                    qtyControls = `
                        <div class="flex flex-col items-center gap-1">
                            <div class="flex items-center gap-2">
                                <button class="w-8 h-8 rounded bg-rose-100 text-rose-700 hover:bg-rose-200 qty-dec" data-id="${cartKey}">-</button>
                                <input type="number" min="1" value="${it.qty}" class="w-14 h-8 text-center border border-slate-200 rounded qty-input" data-id="${cartKey}">
                                <button class="w-8 h-8 rounded bg-emerald-100 text-emerald-700 hover:bg-emerald-200 qty-inc" data-id="${cartKey}">+</button>
                            </div>
                            ${unitControl ? `<div class="mt-1">${unitControl}</div>` : ''}
                            <button type="button" class="text-[11px] text-indigo-700 hover:underline cart-item-unit-edit" data-cart-key="${cartKey}" title="Edit unit / quantity">Edit</button>
                        </div>
                    `;
                }

                const computedLineTotal = (it.line_total !== undefined && it.line_total !== null)
                    ? Number(it.line_total)
                    : (Number(it.qty) * Number(it.price || 0));
                const lineTotalAbs = isReturn ? Math.abs(computedLineTotal) : computedLineTotal;
                const lineDiscountAmount = Number(it.line_discount_amount || 0);
                const lineDiscountText = (!isReturn && lineDiscountAmount > 0)
                    ? `<div class="text-[11px] text-rose-600 font-semibold">Discount: -${currency(lineDiscountAmount)}</div>`
                    : '';
                const descText = (!isReturn && it.description)
                    ? `<div class="text-[11px] text-slate-500 truncate" title="${escapeAttr(it.description)}">${escapeHtml(it.description)}</div>`
                    : '';

                row.innerHTML = `
                    <div class="col-span-7 flex items-center gap-3 min-w-0">
                        <div class="w-10 h-10 rounded-lg bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-400">
                            <i class="far fa-image"></i>
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 min-w-0">
                                <button type="button" class="cart-item-name text-sm font-semibold text-indigo-700 truncate text-left hover:underline" data-cart-key="${cartKey}" title="Discount / notes">${escapeHtml(it.name)}</button>
                                ${!isReturn ? `<button type="button" class="cart-item-edit h-7 w-7 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 flex items-center justify-center" data-cart-key="${cartKey}" title="Discount / notes">
                                    <i class="fas fa-tag text-xs"></i>
                                </button>` : ''}
                            </div>
                            <div class="text-xs text-slate-500">
                                ${currency(it.price)}${it.unit_multiplier && it.unit_multiplier>1 ? ' (x'+it.unit_multiplier+')' : ''}
                                ${isOutOfStock ? '<span class="ml-2 font-semibold text-rose-600 uppercase">Out of stock</span>' : ''}
                            </div>
                            ${lineDiscountText}
                            ${descText}
                        </div>
                    </div>
                    <div class="col-span-3 text-center">
                        ${qtyControls}
                        <button class="mt-1 text-xs text-rose-600 hover:underline remove-item" data-id="${cartKey}">Remove</button>
                    </div>
                    <div class="col-span-2 text-right font-semibold text-slate-800">${currency(lineTotalAbs)}</div>
                `;
                itemsWrap.appendChild(row);
            });
        }

        // Optional: items count in Modern layout
        const itemsCountEl = document.getElementById('items-count');
        if (itemsCountEl) {
            itemsCountEl.textContent = String(Object.keys(items).length);
        }
        const mobileBadge = document.getElementById('mobile-cart-badge');
        if (mobileBadge) {
            mobileBadge.textContent = String(Object.keys(items).length);
        }

        document.getElementById('subtotal').textContent = currency(cart.totals.subtotal);
        document.getElementById('discount-amount').textContent = currency(cart.totals.discount_amount);
        document.getElementById('tax-amount').textContent = currency(cart.totals.tax_amount);
        document.getElementById('total').textContent = currency(cart.totals.total);

        updatePayableUI(cart.totals.total);

        bindCartRowEvents();
        updateCheckoutState();
    }
    
    // Expose renderCart to window for use in modal
    window.renderCart = renderCart;

    async function postJSON(url, data){
        const res = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: JSON.stringify(data || {})
        });
        if (!res.ok) {
            let message = `Request failed (${res.status})`;
            try { const j = await res.json(); if (j && j.message) message = j.message; } catch(_) {}
            throw new Error(message);
        }
        return await res.json();
    }

    // Toasts
    const toastHost = document.createElement('div');
    toastHost.className = 'fixed top-4 right-4 z-50 space-y-2';
    document.body.appendChild(toastHost);
    function showToast(type, message){
        const color = type === 'error' ? 'bg-red-600' : (type === 'warning' ? 'bg-amber-500' : 'bg-emerald-600');
        const el = document.createElement('div');
        el.className = `${color} text-white px-4 py-3 rounded shadow-lg transition-opacity`;
        el.textContent = message;
        toastHost.appendChild(el);
        setTimeout(() => { el.style.opacity = '0'; setTimeout(() => el.remove(), 300); }, 2500);
    }

    // Make toast available to other scripts (outside this IIFE)
    window.showToast = showToast;

    const escapeHtml = (value = '') => String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

    const escapeAttr = (value = '') => String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

    function buildProductCard(product){
        const isOutOfStock = (product.stock_quantity ?? 0) <= 0;
        const visibleUnits = Array.isArray(product.visible_units) ? product.visible_units : [];
        const categories = (product.categories || []).join(', ');
        const brands = (product.brands || []).join(', ');
        const unitShort = product.unit && product.unit.short_name ? product.unit.short_name : null;
        const visibleUnitsAttr = escapeAttr(JSON.stringify(visibleUnits));
        const unitHtml = unitShort ? `<p class="text-xs text-gray-600">Base: ${escapeHtml(unitShort)}</p>` : '';
        const categoriesHtml = categories ? `<p class="text-[11px] text-slate-500 truncate" title="${escapeAttr(categories)}">${escapeHtml(categories)}</p>` : '';
        const brandsHtml = brands ? `<p class="text-[11px] text-slate-500 truncate" title="${escapeAttr(brands)}">${escapeHtml(brands)}</p>` : '';
        const imageContent = product.image
            ? `<img src="${escapeAttr(product.image)}" alt="${escapeAttr(product.name)}" class="w-full h-full object-cover">`
            : `<div class="w-full h-full flex items-center justify-center text-slate-300"><i class="far fa-image text-3xl"></i></div>`;
        const cardBorderClass = isOutOfStock
            ? 'border-rose-300 bg-rose-50/40 shadow-[0_0_0_4px_rgba(248,113,113,0.22)]'
            : 'border-slate-200 bg-white hover:border-indigo-300 hover:shadow-sm';
        return `
            <div class="rounded-xl border ${cardBorderClass} cursor-pointer transition add-to-cart"
                 data-product-id="${product.id}"
                 data-product-name="${escapeAttr(product.name)}"
                 data-product-price="${product.selling_price}"
                 data-visible-units="${visibleUnitsAttr}"
                 data-stock-quantity="${product.stock_quantity ?? 0}">
                <div class="w-full aspect-square rounded-t-xl overflow-hidden bg-slate-50">
                    ${imageContent}
                </div>
                <div class="p-3">
                    <div class="text-sm font-semibold text-indigo-700 truncate">${escapeHtml(product.name)}</div>
                    <div class="mt-1 text-lg font-extrabold text-slate-900">${currency(product.selling_price ?? 0)}</div>
                    <div class="text-[12px] mt-0.5 ${isOutOfStock ? 'text-rose-600 font-bold' : 'text-slate-500'}">
                        Stock: ${product.stock_quantity ?? 0}
                        ${isOutOfStock ? '<span class="ml-2 uppercase font-extrabold">OUT OF STOCK</span>' : ''}
                    </div>
                    ${categoriesHtml}
                    ${brandsHtml}
                    ${unitHtml}
                </div>
            </div>
        `;
    }

    function renderProductGrid(products = []){
        if (!productGridElement) return;
        if (!products.length) {
            productGridElement.innerHTML = `
                <div class="col-span-full text-center py-12 text-gray-500">
                    <i class="fas fa-box-open text-4xl mb-2"></i>
                    No products found
                </div>
            `;
            return;
        }
        productGridElement.innerHTML = products.map(buildProductCard).join('');
    }

    async function fetchProducts(term = ''){
        const url = new URL(SEARCH_PRODUCTS_URL, window.location.origin);
        if (term) url.searchParams.set('term', term);
        const res = await fetch(url, { credentials: 'same-origin' });
        if (!res.ok) {
            const payload = await res.json().catch(() => ({}));
            throw new Error(payload.message || 'Unable to load products');
        }
        return await res.json();
    }

    async function loadProducts(term = ''){
        if (!term) {
            activeProductList = PRELOADED_PRODUCTS;
            renderProductGrid(activeProductList);
            return;
        }
        try {
            const data = await fetchProducts(term);
            activeProductList = Array.isArray(data) ? data : [];
            renderProductGrid(activeProductList);
        } catch(err) {
            console.error('Product search failed', err);
            showToast('error', err.message || 'Product search failed');
        }
    }

    let productSearchTimer;
    if (productSearchInput) {
        productSearchInput.addEventListener('input', () => {
            const term = productSearchInput.value.trim();
            clearTimeout(productSearchTimer);
            productSearchTimer = setTimeout(() => {
                loadProducts(term);
            }, 250);
        });

        const findByCode = (list, term) => {
            const needle = String(term || '').trim().toLowerCase();
            if (!needle) return null;
            return (list || []).find(p => {
                const sku = String(p.sku || '').toLowerCase();
                const barcode = String(p.barcode || '').toLowerCase();
                return sku === needle || barcode === needle;
            }) || null;
        };

        const tryAddByCode = async (term) => {
            const needle = String(term || '').trim();
            if (!needle) return false;

            let product = findByCode(activeProductList, needle) || findByCode(PRELOADED_PRODUCTS, needle);
            if (!product) {
                try {
                    const data = await fetchProducts(needle);
                    if (Array.isArray(data)) {
                        product = findByCode(data, needle) || (data.length === 1 ? data[0] : null);
                    }
                } catch (err) {
                    console.error('Barcode search failed', err);
                }
            }

            if (!product) return false;

            const stockQty = Number(product.stock_quantity ?? 0);
            if (Number.isFinite(stockQty) && stockQty <= 0) {
                (window.showToast || showToast)?.('warning', 'Out of stock');
                return true;
            }

            if (typeof window.addProductToCart !== 'function') {
                return false;
            }
            await window.addProductToCart(product.id, 1, null);
            return true;
        };

        productSearchInput.addEventListener('keydown', async (e) => {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            const term = productSearchInput.value.trim();
            const added = await tryAddByCode(term);
            if (added) {
                productSearchInput.value = '';
                loadProducts('');
            }
        });
    }

    renderProductGrid(activeProductList);

    // Category tabs (Modern layout)
    (function initCategoryTabs(){
        const tabs = document.getElementById('pos-category-tabs');
        if (!tabs) return;
        const escHtml = (value = '') => String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
        const escAttr = (value = '') => String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
        const counts = new Map();
        PRELOADED_PRODUCTS.forEach(p => {
            (p.categories || []).forEach(c => {
                const name = String(c || '').trim();
                if (!name) return;
                counts.set(name, (counts.get(name) || 0) + 1);
            });
        });
        const all = [{ name: 'All', count: PRELOADED_PRODUCTS.length }]
            .concat(Array.from(counts.entries()).sort((a,b) => a[0].localeCompare(b[0])).map(([name, count]) => ({ name, count })));
        let active = 'All';
        const render = () => {
            tabs.innerHTML = all.map(c => {
                const isActive = c.name === active;
                const cls = isActive
                    ? 'bg-indigo-600 text-white border-indigo-600'
                    : 'bg-white text-slate-700 border-slate-200 hover:border-indigo-300 hover:text-indigo-700';
                return `<button type="button" class="whitespace-nowrap text-xs px-3 py-1.5 rounded-full border ${cls}" data-cat="${escAttr(c.name)}">${escHtml(c.name)} (${c.count})</button>`;
            }).join('');
        };
        const applyFilter = () => {
            if (active === 'All') {
                renderProductGrid(activeProductList);
                return;
            }
            const filtered = (activeProductList || []).filter(p => (p.categories || []).includes(active));
            renderProductGrid(filtered);
        };
        tabs.addEventListener('click', (e) => {
            const btn = e.target.closest('button[data-cat]');
            if (!btn) return;
            active = btn.dataset.cat;
            render();
            applyFilter();
        });
        render();
        applyFilter();
    })();

    const holdModal = document.getElementById('hold-modal');
    const holdLabelInput = document.getElementById('hold-label');
    const holdListEl = document.getElementById('hold-list');
    const holdCountBadge = document.getElementById('hold-count');
    const holdEndpoint = '{{ route('pos.cart.hold') }}';
    const holdListEndpoint = '{{ route('pos.cart.holds') }}';
    const holdLoadEndpoint = '{{ route('pos.cart.holds.load') }}';
    const holdRemoveEndpoint = '{{ route('pos.cart.holds.remove') }}';
    const quotationPdfTemplate = '{{ route('quotations.pdf', ['sale' => '__SALE_ID__']) }}';
    const printQuotationBtn = document.getElementById('btn-print-quotation');
    const printModal = document.getElementById('quotation-print-modal');
    const previewIframe = document.getElementById('quotation-preview-iframe');
    const confirmPrintBtn = document.getElementById('confirm-print-quotation');
    const cancelPrintBtn = document.getElementById('cancel-print-quotation');
    let currentQuotationUrl = '';
    function buildQuotationUrl(saleId){
        return quotationPdfTemplate.replace('__SALE_ID__', encodeURIComponent(saleId));
    }

    async function fetchHoldList(){
        const res = await fetch(holdListEndpoint, { credentials: 'same-origin' });
        if (!res.ok) { return []; }
        return await res.json();
    }

    function renderHoldList(holds){
        if (!holdListEl) return;
        if (!holds.length) {
            holdListEl.innerHTML = `<div class="text-sm text-gray-500 text-center py-6"><i class="fas fa-archive text-3xl mb-2"></i>No holds yet</div>`;
            if (holdCountBadge) holdCountBadge.textContent = '0';
            return;
        }
        holdListEl.innerHTML = holds.map(hold => `
            <div class="border border-gray-200 rounded-lg p-4 flex justify-between space-x-4 bg-white shadow-sm">
                <div>
                    <p class="text-sm font-semibold text-gray-800">${hold.label}</p>
                    <p class="text-xs text-gray-500">${hold.item_count} item(s) · ${new Date(hold.created_at).toLocaleString()}</p>
                </div>
                <div class="text-right space-y-1">
                    <p class="font-semibold text-blue-600">${currency(hold.total)}</p>
                    <div class="flex justify-end gap-2">
                        <button class="text-xs text-white bg-blue-600 rounded px-3 py-1 hover:bg-blue-700 load-hold" data-id="${hold.id}" type="button">Continue</button>
                        <button class="text-xs text-red-600 border border-red-200 rounded px-3 py-1 hover:bg-red-50 delete-hold" data-id="${hold.id}" type="button">Delete</button>
                    </div>
                </div>
            </div>
        `).join('');
        if (holdCountBadge) holdCountBadge.textContent = String(holds.length);
    }

    async function refreshHoldList(){
        const holds = await fetchHoldList().catch(() => []);
        renderHoldList(holds);
    }

    function closePrintModal(){
        printModal?.classList.add('hidden');
        if (previewIframe) {
            previewIframe.src = 'about:blank';
        }
        currentQuotationUrl = '';
    }

    function openQuotationPreview(url){
        currentQuotationUrl = url;
        if (previewIframe) {
            previewIframe.src = url;
        }
        printModal?.classList.remove('hidden');
    }

    printQuotationBtn?.addEventListener('click', () => {
        const saleId = printQuotationBtn.dataset.quotationId;
        if (!saleId) {
            showToast('warning', 'No quotation available to print');
            return;
        }
        openQuotationPreview(buildQuotationUrl(saleId));
    });

    confirmPrintBtn?.addEventListener('click', () => {
        if (!currentQuotationUrl) return;
        const printWindow = window.open(currentQuotationUrl, '_blank');
        if (printWindow) {
            printWindow.focus();
            printWindow.onload = () => printWindow.print();
        }
        closePrintModal();
    });

    cancelPrintBtn?.addEventListener('click', closePrintModal);
    document.querySelectorAll('[data-close-print-modal]').forEach(btn => btn.addEventListener('click', closePrintModal));

    const openHoldModal = async () => {
        await refreshHoldList();
        holdModal?.classList.remove('hidden');
    };

    const closeHoldModal = () => {
        holdModal?.classList.add('hidden');
    };

    document.querySelectorAll('[data-close-hold]').forEach(btn => btn.addEventListener('click', closeHoldModal));
    document.getElementById('btn-hold')?.addEventListener('click', openHoldModal);
    document.getElementById('btn-open-holds')?.addEventListener('click', openHoldModal);

    const confirmHoldBtn = document.getElementById('confirm-hold');
    confirmHoldBtn?.addEventListener('click', async () => {
        const label = holdLabelInput?.value.trim();
        try {
            const payload = await postJSON(holdEndpoint, { label });
            renderCart(payload.cart);
            if (holdLabelInput) holdLabelInput.value = '';
            showToast('success', 'Bill held successfully');
            await refreshHoldList();
            closeHoldModal();
        } catch(err){
            showToast('error', err.message);
        }
    });

    holdListEl?.addEventListener('click', async (event) => {
        const button = event.target.closest('button');
        if (!button) return;
        const holdId = button.dataset.id;
        if (!holdId) return;
            if (button.classList.contains('load-hold')) {
            try {
                const payload = await postJSON(holdLoadEndpoint, { hold_id: holdId });
                renderCart(payload.cart);
                await refreshHoldList();
                closeHoldModal();
                showToast('success', 'Hold loaded');
            } catch(err){
                showToast('error', err.message);
            }
        } else if (button.classList.contains('delete-hold')) {
            try {
                await postJSON(holdRemoveEndpoint, { hold_id: holdId });
                await refreshHoldList();
                showToast('success', 'Hold deleted');
            } catch(err){
                showToast('error', err.message);
            }
        }
    });

    function bindCartRowEvents(){
        document.querySelectorAll('.qty-inc').forEach(btn => btn.onclick = async (e) => {
            const cartKey = e.currentTarget.dataset.id;
            const input = document.querySelector(`.qty-input[data-id="${cartKey}"]`);
            const qty = parseInt(input.value || '1') + 1;
            const cart = await postJSON('{{ route('pos.cart.update') }}', { cart_key: cartKey, qty });
            renderCart(cart);
        });
        document.querySelectorAll('.qty-dec').forEach(btn => btn.onclick = async (e) => {
            const cartKey = e.currentTarget.dataset.id;
            const input = document.querySelector(`.qty-input[data-id="${cartKey}"]`);
            const qty = Math.max(1, parseInt(input.value || '1') - 1);
            const cart = await postJSON('{{ route('pos.cart.update') }}', { cart_key: cartKey, qty });
            renderCart(cart);
        });
        document.querySelectorAll('.qty-input').forEach(inp => inp.onchange = async (e) => {
            const cartKey = e.currentTarget.dataset.id;
            const qty = Math.max(1, parseInt(e.currentTarget.value || '1'));
            const cart = await postJSON('{{ route('pos.cart.update') }}', { cart_key: cartKey, qty });
            renderCart(cart);
        });
        document.querySelectorAll('.remove-item').forEach(btn => btn.onclick = async (e) => {
            const cartKey = e.currentTarget.dataset.id;
            const cart = await postJSON('{{ route('pos.cart.remove') }}', { cart_key: cartKey });
            renderCart(cart);
        });
        // Unit select handlers
        document.querySelectorAll('.unit-select').forEach(sel => sel.onchange = async (e) => {
            const cartKey = e.currentTarget.dataset.cartKey;
            const unitId = parseInt(e.currentTarget.value || '0');
            if (!unitId) return;
            try {
                const cart = await postJSON('{{ route('pos.cart.unit') }}', { cart_key: cartKey, unit_id: unitId });
                renderCart(cart);
            } catch(err){
                showToast('error','Failed to change unit');
            }
        });

        // Item discount modal
        document.querySelectorAll('.cart-item-edit').forEach(btn => btn.onclick = (e) => {
            const cartKey = e.currentTarget.dataset.cartKey;
            if (!cartKey) return;
            openItemDiscountModal(cartKey);
        });

        // Clicking product name opens discount modal
        document.querySelectorAll('.cart-item-name').forEach(btn => btn.onclick = (e) => {
            const cartKey = e.currentTarget.dataset.cartKey;
            if (!cartKey) return;
            openItemDiscountModal(cartKey);
        });
    }

    // Item discount modal logic
    const itemDiscountModal = document.getElementById('item-discount-modal');
    const itemDiscountProduct = document.getElementById('item-discount-product');
    const itemDiscountUnitPrice = document.getElementById('item-discount-unit-price');
    const itemDiscountQty = document.getElementById('item-discount-qty');
    const itemDiscountUnitWrap = document.getElementById('item-discount-unit-wrap');
    const itemDiscountUnitId = document.getElementById('item-discount-unit-id');
    const itemDiscountValue = document.getElementById('item-discount-value');
    const itemDiscountDesc = document.getElementById('item-discount-description');
    const itemDiscountSubtotal = document.getElementById('item-discount-subtotal');
    const itemDiscountAmount = document.getElementById('item-discount-amount');
    const itemDiscountTotal = document.getElementById('item-discount-total');
    const btnApplyItemDiscount = document.getElementById('btn-apply-item-discount');
    const btnTypeFixed = document.getElementById('item-discount-type-fixed');
    const btnTypePercent = document.getElementById('item-discount-type-percent');

    let itemDiscountCartKey = null;
    let itemDiscountType = 'fixed';
    let itemDiscountProductId = null;

    function setItemDiscountType(type){
        itemDiscountType = (type === 'percent') ? 'percent' : 'fixed';
        const activeCls = ['bg-indigo-600','text-white','border-indigo-600'];
        const inactiveCls = ['bg-white','text-slate-800','border-slate-200'];

        [btnTypeFixed, btnTypePercent].forEach(b => {
            if (!b) return;
            b.classList.remove(...activeCls);
            b.classList.remove(...inactiveCls);
            b.classList.add(...inactiveCls);
        });
        const activeBtn = itemDiscountType === 'percent' ? btnTypePercent : btnTypeFixed;
        if (activeBtn) {
            activeBtn.classList.remove(...inactiveCls);
            activeBtn.classList.add(...activeCls);
        }
        recomputeItemDiscountPreview();
    }

    function recomputeItemDiscountPreview(){
        if (!itemDiscountUnitPrice || !itemDiscountQty) return;
        const qty = Number(String(itemDiscountQty.value || '0').replace(/[^0-9.-]/g,'')) || 0;
        const unitPrice = Number(itemDiscountUnitPrice.value || 0);
        let discVal = Number(itemDiscountValue?.value || 0);
        if (discVal < 0) discVal = 0;

        const subtotal = Math.round((qty * unitPrice) * 100) / 100;
        let discAmt = 0;
        if (itemDiscountType === 'percent') {
            discVal = Math.min(100, discVal);
            discAmt = Math.round((subtotal * (discVal / 100)) * 100) / 100;
        } else {
            discAmt = Math.round(discVal * 100) / 100;
        }
        discAmt = Math.min(Math.max(0, discAmt), Math.max(0, subtotal));
        const total = Math.round((subtotal - discAmt) * 100) / 100;

        if (itemDiscountSubtotal) itemDiscountSubtotal.textContent = currency(subtotal);
        if (itemDiscountAmount) itemDiscountAmount.textContent = currency(discAmt);
        if (itemDiscountTotal) itemDiscountTotal.textContent = currency(total);
    }

    function closeItemDiscountModal(){
        itemDiscountModal?.classList.add('hidden');
        itemDiscountCartKey = null;
        itemDiscountProductId = null;
    }

    function openItemDiscountModal(cartKey){
        if (!itemDiscountModal) return;
        const cart = window.__posCartSnapshot || {};
        const it = (cart.items || {})[cartKey];
        if (!it || Number(it.qty || 0) < 0) return;

        itemDiscountCartKey = cartKey;
        itemDiscountProductId = Number(it.id || 0) || null;
        if (itemDiscountProduct) itemDiscountProduct.textContent = String(it.name || '');
        if (itemDiscountUnitPrice) itemDiscountUnitPrice.value = String(Number(it.price || 0));
        if (itemDiscountQty) itemDiscountQty.value = String(Number(it.qty || 0));

        // Units dropdown
        const vUnits = Array.isArray(it.visible_units) ? it.visible_units.map(v => Number(v)) : [];
        const currentUnitId = Number(it.unit_id || 0);
        if (itemDiscountUnitWrap && itemDiscountUnitId && vUnits.length > 1) {
            itemDiscountUnitId.innerHTML = vUnits.map(uid => {
                const u = window.unitsMap ? window.unitsMap[uid] : null;
                if (!u) return '';
                const label = (u.short_name || u.name);
                const sel = (uid === currentUnitId) ? 'selected' : '';
                return `<option value="${uid}" ${sel}>${escapeHtml(label)}</option>`;
            }).join('');
            itemDiscountUnitWrap.classList.remove('hidden');
        } else {
            itemDiscountUnitWrap?.classList.add('hidden');
            if (itemDiscountUnitId) itemDiscountUnitId.innerHTML = '';
        }

        const t = (it.line_discount && it.line_discount.type) ? String(it.line_discount.type) : 'fixed';
        setItemDiscountType(t);
        if (itemDiscountValue) itemDiscountValue.value = String(Number(it.line_discount?.value || 0));
        if (itemDiscountDesc) itemDiscountDesc.value = String(it.description || '');
        recomputeItemDiscountPreview();
        itemDiscountModal.classList.remove('hidden');
        itemDiscountUnitPrice?.focus();
    }

    btnTypeFixed?.addEventListener('click', () => setItemDiscountType('fixed'));
    btnTypePercent?.addEventListener('click', () => setItemDiscountType('percent'));
    itemDiscountUnitPrice?.addEventListener('input', recomputeItemDiscountPreview);
    itemDiscountValue?.addEventListener('input', recomputeItemDiscountPreview);

    itemDiscountModal?.addEventListener('click', (e) => {
        if (e.target === itemDiscountModal) closeItemDiscountModal();
    });
    document.querySelectorAll('[data-close-item-discount]').forEach(btn => btn.addEventListener('click', closeItemDiscountModal));

    btnApplyItemDiscount?.addEventListener('click', async () => {
        if (!itemDiscountCartKey) return;
        try {
            const unitPrice = Number(itemDiscountUnitPrice?.value || 0);
            const discountValue = Number(itemDiscountValue?.value || 0);
            const description = String(itemDiscountDesc?.value || '');

            let effectiveKey = itemDiscountCartKey;

            // Optional unit change first
            const requestedUnitId = Number(itemDiscountUnitId?.value || 0);
            const currentCart = window.__posCartSnapshot || {};
            const currentIt = (currentCart.items || {})[effectiveKey];
            const currentUnitId = Number(currentIt?.unit_id || 0);
            const canChangeUnit = !!requestedUnitId && !!currentUnitId && (requestedUnitId !== currentUnitId);

            if (canChangeUnit) {
                const cartAfterUnit = await postJSON('{{ route('pos.cart.unit') }}', { cart_key: effectiveKey, unit_id: requestedUnitId });

                // Determine the new key (unit route uses productId_unit_unitId)
                const productId = itemDiscountProductId || Number(currentIt?.id || 0);
                const newKey = productId ? `${productId}_unit_${requestedUnitId}` : null;
                if (newKey && cartAfterUnit?.items && cartAfterUnit.items[newKey]) {
                    effectiveKey = newKey;
                } else {
                    // Fallback: if merged key differs, try to find matching item
                    if (newKey && cartAfterUnit?.items && Object.prototype.hasOwnProperty.call(cartAfterUnit.items, newKey)) {
                        effectiveKey = newKey;
                    }
                }
                window.__posCartSnapshot = cartAfterUnit;
            }

            const cart = await postJSON('{{ route('pos.cart.item.update') }}', {
                cart_key: effectiveKey,
                unit_price: unitPrice,
                discount_type: itemDiscountType,
                discount_value: discountValue,
                description: description,
            });

            renderCart(cart);
            showToast('success', 'Discount updated');
            closeItemDiscountModal();
        } catch (err) {
            showToast('error', err?.message || 'Failed to update discount');
        }
    });

    // Mobile navigation: toggle Products/Cart on small screens
    const cartPanel = document.getElementById('pos-cart-panel');
    const productsPanel = document.getElementById('pos-products-panel');
    const btnViewProducts = document.getElementById('btn-view-products');
    const btnViewCart = document.getElementById('btn-view-cart');

    let mobileView = (localStorage.getItem('pos_mobile_view') || 'products');

    function applyMobileView(){
        if (!cartPanel || !productsPanel || !btnViewProducts || !btnViewCart) return;
        const isMobile = window.matchMedia('(max-width: 1023px)').matches;
        if (!isMobile) {
            cartPanel.classList.remove('hidden', 'row-span-2');
            productsPanel.classList.remove('hidden', 'row-span-2');
            btnViewProducts.classList.remove('bg-indigo-600', 'text-white', 'border-indigo-600');
            btnViewCart.classList.remove('bg-indigo-600', 'text-white', 'border-indigo-600');
            return;
        }

        const activeBtnCls = ['bg-indigo-600','text-white','border-indigo-600'];
        const inactiveBtnCls = ['bg-white','text-slate-800','border-slate-200'];

        if (mobileView === 'cart') {
            cartPanel.classList.remove('hidden');
            cartPanel.classList.add('row-span-2');
            productsPanel.classList.add('hidden');
            productsPanel.classList.remove('row-span-2');

            btnViewCart.classList.remove(...inactiveBtnCls);
            btnViewCart.classList.add(...activeBtnCls);
            btnViewProducts.classList.remove(...activeBtnCls);
            btnViewProducts.classList.add(...inactiveBtnCls);
        } else {
            productsPanel.classList.remove('hidden');
            productsPanel.classList.add('row-span-2');
            cartPanel.classList.add('hidden');
            cartPanel.classList.remove('row-span-2');

            btnViewProducts.classList.remove(...inactiveBtnCls);
            btnViewProducts.classList.add(...activeBtnCls);
            btnViewCart.classList.remove(...activeBtnCls);
            btnViewCart.classList.add(...inactiveBtnCls);
        }
    }

    btnViewProducts?.addEventListener('click', () => {
        mobileView = 'products';
        localStorage.setItem('pos_mobile_view', mobileView);
        applyMobileView();
    });
    btnViewCart?.addEventListener('click', () => {
        mobileView = 'cart';
        localStorage.setItem('pos_mobile_view', mobileView);
        applyMobileView();
    });
    window.addEventListener('resize', applyMobileView);
    applyMobileView();

    // Product click -> handled by unit selection modal now
    // See modal script at the bottom of the page

    // Discount change
    const discountInputs = document.querySelectorAll('.discount-type');
    const discountValue = document.getElementById('discount-value');
    async function pushDiscount(){
        const type = document.querySelector('.discount-type:checked').value;
        const value = parseFloat(discountValue.value || '0');
        const cart = await postJSON('{{ route('pos.cart.discount') }}', { type, value });
        renderCart(cart);
    }
    discountInputs.forEach(r => r.addEventListener('change', pushDiscount));
    discountValue.addEventListener('change', pushDiscount);

    // Cash amount change calculation + due calculation
    const cashInput = document.getElementById('cash-amount');
    const changeDisplay = document.getElementById('change-display');
    const changeAmount = document.getElementById('change-amount');
    const dueDisplay = document.getElementById('due-display');
    const dueAmountEl = document.getElementById('due-amount');

    const paymentMethodEl = document.getElementById('payment-method');
    paymentMethodEl?.addEventListener('change', () => {
        updatePayableUI(readBaseTotal());
        // Recompute due/change for new payable
        if (cashInput) cashInput.dispatchEvent(new Event('input'));
        updateCheckoutState();
    });
    
    cashInput?.addEventListener('input', () => {
        const cash = parseFloat(cashInput.value || '0');
        const baseTotal = readBaseTotal();
        const payableTotal = computePayableTotal(baseTotal);
        const change = cash - payableTotal;
        const due = Math.max(0, payableTotal - cash);
        
        if (cash > 0 && change >= 0) {
            changeDisplay?.classList.remove('hidden');
            if (changeAmount) changeAmount.textContent = currency(change);
        } else {
            changeDisplay?.classList.add('hidden');
        }

        if (due > 0) {
            dueDisplay?.classList.remove('hidden');
            if (dueAmountEl) dueAmountEl.textContent = currency(due);
        } else {
            dueDisplay?.classList.add('hidden');
        }
        updateCheckoutState();
    });

    // Customer due fetch
    const customerSelect = document.getElementById('customer-select');
    const customerDueWrap = document.getElementById('customer-due');
    const customerDueAmount = document.getElementById('customer-due-amount');
    if (customerSelect) {
        customerSelect.addEventListener('change', async () => {
            const id = customerSelect.value;
            if (!id) { customerDueWrap.classList.add('hidden'); return; }
            try {
                const res = await fetch(`/api/customer-due/${id}`);
                if (res.ok) {
                    const data = await res.json();
                    customerDueAmount.textContent = currency(data.outstanding_due || 0);
                    customerDueWrap.classList.remove('hidden');
                }
            } catch(_){}
            updateCheckoutState();
        });
        // Initial state
        updateCheckoutState();
    }

    // New Customer Modal
    const newCustomerBtn = document.getElementById('btn-new-customer');
    let customerModal;
    function ensureCustomerModal(){
        if (customerModal) return customerModal;
        customerModal = document.createElement('div');
        customerModal.className = 'fixed inset-0 z-50 flex items-center justify-center bg-black/40';
        customerModal.innerHTML = `
            <div class="bg-white w-full max-w-md rounded-xl shadow-lg p-6 relative">
                <button class="absolute top-2 right-2 text-gray-500 hover:text-gray-700" data-close>&times;</button>
                <h3 class="text-lg font-semibold mb-4">New Customer</h3>
                <form id="new-customer-form" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Name *</label>
                        <input name="name" required class="mt-1 w-full px-3 py-2 border rounded" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Email</label>
                        <input type="email" name="email" class="mt-1 w-full px-3 py-2 border rounded" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Phone</label>
                        <input name="phone" class="mt-1 w-full px-3 py-2 border rounded" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Address</label>
                        <textarea name="address" rows="2" class="mt-1 w-full px-3 py-2 border rounded"></textarea>
                    </div>
                    <div class="flex justify-end space-x-2 pt-2">
                        <button type="button" data-close class="px-4 py-2 bg-gray-100 text-gray-700 rounded">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded">Save</button>
                    </div>
                </form>
            </div>`;
        document.body.appendChild(customerModal);
        customerModal.addEventListener('click', e => {
            if (e.target === customerModal || e.target.hasAttribute('data-close')) {
                customerModal.classList.add('hidden');
            }
        });
        document.getElementById('new-customer-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const form = e.currentTarget;
            const formData = new FormData(form);
            const payload = Object.fromEntries(formData.entries());
            try {
                const res = await fetch('{{ route('customers.store') }}', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    body: formData
                });
                if(!res.ok){ throw new Error('Failed to create customer'); }
                const data = await res.json();
                const c = data.customer;
                // Append to select
                const opt = document.createElement('option');
                opt.value = c.id; opt.textContent = c.name;
                customerSelect.appendChild(opt);
                customerSelect.value = c.id;
                customerModal.classList.add('hidden');
                showToast('success', 'Customer added');
            } catch(err){ showToast('error', err.message); }
        });
        return customerModal;
    }
    if(newCustomerBtn){
        newCustomerBtn.addEventListener('click', () => {
            ensureCustomerModal();
            customerModal.classList.remove('hidden');
        });
    }

    // Clear
    document.getElementById('btn-clear').addEventListener('click', async () => {
        const cart = await postJSON('{{ route('pos.cart.clear') }}');
        renderCart(cart);
    });

    // Draft
    document.getElementById('btn-draft').addEventListener('click', async () => {
        try {
            const res = await postJSON('{{ route('pos.draft') }}', {});
            if(res && res.sale_id){
                const label = res.sale_no ? res.sale_no : ('#' + res.sale_id);
                showToast('success', 'Quotation saved ' + label);
                if (printQuotationBtn) {
                    printQuotationBtn.dataset.quotationId = res.sale_id;
                    printQuotationBtn.dataset.saleNo = label;
                    printQuotationBtn.title = 'Print ' + label;
                    printQuotationBtn.classList.remove('hidden');
                }
                openQuotationPreview(buildQuotationUrl(res.sale_id));
            }
        } catch(err){ showToast('error', err.message || 'Failed to save draft'); }
    });

    async function doCheckout(payloadExtra = {}){
        const cashAmount = parseFloat(document.getElementById('cash-amount')?.value || '0');
        const baseTotal = readBaseTotal();
        const payableTotal = computePayableTotal(baseTotal);
        const due = Math.max(0, payableTotal - cashAmount);
        const customerId = document.getElementById('customer-select') ? document.getElementById('customer-select').value : '';
        if (due > 0 && !customerId) {
            showToast('warning', 'Customer is required when there is a due amount.');
            if (document.getElementById('customer-select')) {
                document.getElementById('customer-select').classList.add('ring-2','ring-amber-500');
                setTimeout(()=>document.getElementById('customer-select').classList.remove('ring-2','ring-amber-500'), 1500);
            }
            return;
        }
        
        let res;
        try {
            res = await postJSON('{{ route('pos.checkout') }}', {
                paid_amount: cashAmount,
                customer_id: customerId ? Number(customerId) : null,
                payment_method: getPaymentMethod(),
                ...payloadExtra
            });
        } catch(err){ showToast('error', err.message || 'Checkout failed'); return; }
        
        if(res && res.sale_id){
            // Fetch full receipt data so bill prints correctly
            let receiptData = null;
            try {
                const receiptRes = await fetch('{{ url('api/sale-receipt') }}/' + encodeURIComponent(res.sale_id), {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' }
                });
                if (receiptRes.ok) {
                    receiptData = await receiptRes.json();
                }
            } catch (_) {
                // ignore, fallback below
            }

            const saleForPrint = receiptData || res.sale || { id: res.sale_id, sale_no: res.sale_id };
            window.__lastReceiptSaleData = saleForPrint;
            showPrintReceipt(saleForPrint);
            const cart = await postJSON('{{ route('pos.cart.clear') }}');
            renderCart(cart);
            document.getElementById('cash-amount').value = '';
            changeDisplay?.classList.add('hidden');
            dueDisplay?.classList.add('hidden');
            showToast('success', 'Sale completed #' + res.sale_id);
        } else if(res && res.message){
            showToast('error', res.message);
        }
    }

    // Checkout (default layout)
    document.getElementById('btn-checkout')?.addEventListener('click', () => doCheckout());

    // Quick pay buttons (modern layout)
    function quickPay(method){
        const paymentEl = document.getElementById('payment-method');
        if (paymentEl) paymentEl.value = method;
        updatePayableUI(readBaseTotal());
        const baseTotal = readBaseTotal();
        const payable = computePayableTotal(baseTotal);
        const cashEl = document.getElementById('cash-amount');
        if (cashEl) {
            cashEl.value = String(payable);
            cashEl.dispatchEvent(new Event('input'));
        }
        doCheckout();
    }
    document.getElementById('btn-pay-cash')?.addEventListener('click', () => quickPay('cash'));
    document.getElementById('btn-pay-card')?.addEventListener('click', () => quickPay('card'));

    /* ----------------------- Multiple Pay Workflow ----------------------- */
    const multiPayModal = document.getElementById('multi-pay-modal');
    const multiPayRows = document.getElementById('multi-pay-rows');
    const addPaymentRowBtn = document.getElementById('btn-add-payment-row');
    const finalizeMultiPayBtn = document.getElementById('btn-finalize-multi-pay');
    const openMultiPayBtn = document.getElementById('btn-pay-multi');

    function closeMultiPay(){
        multiPayModal?.classList.add('hidden');
    }

    document.querySelectorAll('[data-close-multi-pay]').forEach(btn => btn.addEventListener('click', closeMultiPay));
    multiPayModal?.addEventListener('click', (e) => {
        if (e.target === multiPayModal) closeMultiPay();
    });

    function buildPaymentRow(method = 'cash', amount = ''){
        const row = document.createElement('div');
        row.className = 'grid grid-cols-12 gap-2 items-center';
        row.innerHTML = `
            <div class="col-span-6">
                <select class="w-full px-3 py-2 border border-gray-300 rounded-lg payment-method">
                    <option value="cash">Cash</option>
                    <option value="card">Card</option>
                    <option value="bank_transfer">Bank Transfer</option>
                    <option value="mobile_payment">Mobile Payment</option>
                </select>
            </div>
            <div class="col-span-5">
                <input type="number" step="0.01" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg payment-amount" placeholder="Amount" />
            </div>
            <div class="col-span-1 text-right">
                <button type="button" class="text-red-600 hover:text-red-800 btn-remove-pay" title="Remove">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        `;
        const sel = row.querySelector('select.payment-method');
        const inp = row.querySelector('input.payment-amount');
        sel.value = method;
        inp.value = amount;
        sel.addEventListener('change', updateMultiPaySummary);
        inp.addEventListener('input', updateMultiPaySummary);
        row.querySelector('.btn-remove-pay')?.addEventListener('click', () => {
            row.remove();
            if (multiPayRows && multiPayRows.children.length === 0) {
                multiPayRows.appendChild(buildPaymentRow('cash',''));
            }
            updateMultiPaySummary();
        });
        return row;
    }

    function getMultiPayPayments(){
        const payments = [];
        let cardUsed = false;
        let cardAmount = 0;
        multiPayRows?.querySelectorAll(':scope > div').forEach(row => {
            const method = row.querySelector('select.payment-method')?.value;
            const amount = parseFloat(row.querySelector('input.payment-amount')?.value || '0');
            if (!method) return;
            if (!amount || amount <= 0) return;
            if (method === 'card') {
                cardUsed = true;
                cardAmount += amount;
            }
            payments.push({ method, amount: Math.round(amount * 100) / 100 });
        });
        return { payments, cardUsed, cardAmount: Math.round(cardAmount * 100) / 100 };
    }

    function updateMultiPaySummary(){
        const baseTotal = readBaseTotal();
        const { payments, cardUsed, cardAmount } = getMultiPayPayments();
        const paying = payments.reduce((s,p) => s + (Number(p.amount)||0), 0);
        const payable = computePayableTotalSplit(baseTotal, cardAmount);
        const fee = computeCardFeeAmount(cardAmount);

        const itemsEl = document.getElementById('multi-pay-items');
        const payableEl = document.getElementById('multi-pay-payable');
        const amountEl = document.getElementById('multi-pay-amount');
        const totalPayingEl = document.getElementById('multi-pay-total-paying');
        const changeEl = document.getElementById('multi-pay-change');
        const balanceEl = document.getElementById('multi-pay-balance');

        const itemCount = (document.getElementById('items-count')?.textContent || '').trim();
        if (itemsEl) itemsEl.textContent = itemCount || '0';
        if (amountEl) amountEl.value = currency(payable);
        if (payableEl) payableEl.textContent = currency(payable);
        if (totalPayingEl) totalPayingEl.value = currency(paying);

        const change = Math.max(0, paying - payable);
        const balance = Math.max(0, payable - paying);
        if (changeEl) changeEl.textContent = currency(change);
        if (balanceEl) balanceEl.textContent = currency(balance);

        const feeWrap = document.getElementById('multi-pay-card-fee');
        const feeAmount = document.getElementById('multi-pay-card-fee-amount');
        if (feeWrap && feeAmount) {
            if (cardUsed && fee > 0) {
                feeWrap.classList.remove('hidden');
                feeAmount.textContent = currency(fee);
            } else {
                feeWrap.classList.add('hidden');
            }
        }
    }

    function openMultiPay(){
        if (!multiPayModal || !multiPayRows) return;
        multiPayRows.innerHTML = '';
        multiPayRows.appendChild(buildPaymentRow('cash',''));
        multiPayModal.classList.remove('hidden');
        updateMultiPaySummary();
    }

    openMultiPayBtn?.addEventListener('click', openMultiPay);
    addPaymentRowBtn?.addEventListener('click', () => {
        multiPayRows?.appendChild(buildPaymentRow('cash',''));
        updateMultiPaySummary();
    });

    finalizeMultiPayBtn?.addEventListener('click', async () => {
        const { payments, cardUsed, cardAmount } = getMultiPayPayments();
        if (!payments.length) {
            showToast('warning', 'Please enter at least one payment amount');
            return;
        }
        const baseTotal = readBaseTotal();
        const payable = computePayableTotalSplit(baseTotal, cardAmount);
        const paying = payments.reduce((s,p) => s + (Number(p.amount)||0), 0);

        // Put sum into the hidden cash amount so existing validations + UI still work
        const cashEl = document.getElementById('cash-amount');
        if (cashEl) {
            cashEl.value = String(paying);
            cashEl.dispatchEvent(new Event('input'));
        }

        const sellNote = document.getElementById('multi-pay-sell-note')?.value || '';
        const staffNote = document.getElementById('multi-pay-staff-note')?.value || '';
        const notes = [sellNote, staffNote].filter(Boolean).join(' | ');

        // Proceed with checkout using payments payload
        await doCheckout({ payments, notes, _multi_pay_payable: payable });
        closeMultiPay();
    });

    // Print Receipt Function
    async function showPrintReceipt(saleData) {
        const PAPER_SIZE = '{{ $invoicePaperSize ?? "a4" }}';
        const CURRENCY = '{{ $currency }} ';
        const VAT_ENABLED = {{ \App\Models\Setting::get('vat_enabled', false) ? 'true' : 'false' }};
        const VAT_RATE = {{ (float) \App\Models\Setting::get('vat_rate', 0) }};
        const DEV_NAME = '{{ config('services.developer.name') }}';
        const DEV_WEB = '{{ config('services.developer.website') }}';
        const DEV_PHONE = '{{ config('services.developer.phone') }}';
        
        // Paper size configurations
        const paperSizes = {
            'a4': { width: '210mm', maxWidth: '210mm', padding: '16px' },
            'letter': { width: '8.5in', maxWidth: '8.5in', padding: '16px' },
            '80mm': { width: '80mm', maxWidth: '300px', padding: '8px' },
            '58mm': { width: '58mm', maxWidth: '220px', padding: '6px' }
        };
        
        const config = paperSizes[PAPER_SIZE] || paperSizes['a4'];
        
        // Build items HTML
        let itemsHTML = '';
        if (saleData.items && saleData.items.length > 0) {
            itemsHTML = saleData.items.map(item => {
                return '<tr>' +
                    '<td>' + (item.product_name || '') + '</td>' +
                    '<td class="text-right">' + Number(item.quantity || 0) + '</td>' +
                    '<td class="text-right">' + CURRENCY + Number(item.unit_price || 0).toFixed(2) + '</td>' +
                    '<td class="text-right">' + CURRENCY + Number(item.total || 0).toFixed(2) + '</td>' +
                    '</tr>';
            }).join('');
        }
        
        // Customer row if exists
        const customerRow = saleData.customer_name ? 
            '<div><span>Customer:</span><span>' + saleData.customer_name + '</span></div>' : '';

        const saleDateText = saleData.sale_date
            ? new Date(saleData.sale_date).toLocaleString()
            : new Date().toLocaleString();

        const cashierName = saleData.cashier_name || 'Admin';
        const paymentMethodLabel = (saleData.payment_method || '').toString().replace(/_/g, ' ').toUpperCase() || 'CASH';
        const paidAmount = Number(saleData.paid_amount || 0);
        const totalAmount = Number(saleData.total_amount || 0);
        const dueAmount = Number(saleData.due_amount || 0);
        const paymentsList = Array.isArray(saleData.payments) ? saleData.payments : [];
        const hasCashPayment = paymentsList.length > 0
            ? paymentsList.some(p => (p.method || '').toString().toLowerCase() === 'cash')
            : ((saleData.payment_method || 'cash') === 'cash');
        const summedTendered = paymentsList.reduce((sum, p) => sum + Number(p.amount || 0), 0);
        const tenderedAmount = Number(saleData.tendered_amount || 0) || summedTendered || paidAmount;
        const changeAmount = hasCashPayment ? Math.max(0, tenderedAmount - totalAmount) : 0;
        const showPaidRow = dueAmount > 0 || Math.abs(paidAmount - totalAmount) > 0.0001;

        let paymentLinesHtml = '';
        if (paymentsList.length > 0) {
            paymentLinesHtml = paymentsList.map(p => {
                const m = (p.method || '').toString().replace(/_/g, ' ').toUpperCase();
                return '<div class="payment-row"><span>' + m + ':</span><span>' + CURRENCY + Number(p.amount || 0).toFixed(2) + '</span></div>';
            }).join('');
        }
        
        const printWindow = window.open('', '_blank', 'width=800,height=600');
        
        const taxRowHtml = VAT_ENABLED ? (
'            <div class="totals-row">' +
'                <span>VAT' + (VAT_RATE ? ' (' + VAT_RATE + '%)' : '') + ':</span>' +
'                <span>' + CURRENCY + Number(saleData.tax || 0).toFixed(2) + '</span>' +
'            </div>'
        ) : '';

        const phoneDigits = (DEV_PHONE || '').replace(/[^0-9]/g, '');
        const poweredByHtml = DEV_WEB ? (
            'Powered by <a href="https://' + DEV_WEB + '" target="_blank" style="color:inherit; text-decoration:none; font-weight:600;">' + DEV_WEB + '</a>'
        ) : phoneDigits ? (
            'Powered by <a href="https://wa.me/' + phoneDigits + '" target="_blank" style="color:inherit; text-decoration:none; font-weight:600;">' + (DEV_NAME || phoneDigits) + '</a>'
        ) : (
            'Powered by ' + (DEV_NAME || 'Developer')
        );

        const receiptHTML = '<!DOCTYPE html>' +
'<html>' +
'<head>' +
'    <meta charset="UTF-8">' +
'    <title>Receipt #' + (saleData.sale_no || saleData.id) + '</title>' +
'    <style>' +
'        * { margin: 0; padding: 0; box-sizing: border-box; }' +
'        body { font-family: Arial, Helvetica, sans-serif; background: #fff; padding: 16px; color: #000; font-size: 12px; }' +
'        .receipt-container { max-width: 420px; margin: 0 auto; background: #fff; padding: 16px; border: 1px solid #000; }' +
'        .header { text-align: center; border-bottom: 1px dashed #000; padding-bottom: 10px; margin-bottom: 12px; }' +
'        .logo { font-size: 18px; font-weight: 700; }' +
'        .shop-details { font-size: 11px; line-height: 1.4; margin-top: 4px; }' +
'        .receipt-title { font-size: 14px; font-weight: 700; margin: 10px 0; text-align: center; }' +
'        .receipt-info { font-size: 11px; margin-bottom: 10px; }' +
'        .receipt-info div { display: flex; justify-content: space-between; margin-bottom: 4px; }' +
'        table { width: 100%; border-collapse: collapse; margin: 10px 0; }' +
'        th, td { padding: 6px 4px; font-size: 11px; border-bottom: 1px solid #000; }' +
'        th { font-weight: 700; text-align: left; }' +
'        .text-right { text-align: right; }' +
'        .totals { margin-top: 10px; border-top: 1px solid #000; padding-top: 8px; }' +
'        .totals-row { display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 12px; }' +
'        .totals-row.grand-total { font-weight: 700; font-size: 14px; border-top: 1px dashed #000; padding-top: 6px; margin-top: 6px; }' +
'        .payment-info { margin-top: 10px; padding: 8px; border: 1px solid #000; }' +
'        .payment-row { display: flex; justify-content: space-between; margin-bottom: 4px; font-size: 12px; }' +
'        .footer { margin-top: 14px; padding-top: 10px; border-top: 1px dashed #000; text-align: center; font-size: 11px; }' +
'        @media print { body { padding: 0; } }' +
'    </style>' +
'</head>' +
'<body>' +
'    <div class="receipt-container">' +
'        <div class="header">' +
'            <div class="logo">{{ \App\Models\Setting::get("shop_name", "Vehicle POS System") }}</div>' +
'            <div class="shop-details">' +
'                {{ \App\Models\Setting::get("shop_address", "") }}<br>' +
'                Tel: {{ \App\Models\Setting::get("shop_phone", "") }}<br>' +
'                Email: {{ \App\Models\Setting::get("shop_email", "") }}' +
'            </div>' +
'        </div>' +
'        <div class="receipt-title">SALES RECEIPT</div>' +
'        <div class="receipt-info">' +
'            <div><span>Receipt #:</span><span>' + (saleData.sale_no || saleData.id) + '</span></div>' +
'            <div><span>Date:</span><span>' + saleDateText + '</span></div>' +
'            <div><span>Cashier:</span><span>' + cashierName + '</span></div>' +
            customerRow +
'        </div>' +
'        <table>' +
'            <thead>' +
'                <tr>' +
'                    <th>Item</th>' +
'                    <th class="text-right">Qty</th>' +
'                    <th class="text-right">Price</th>' +
'                    <th class="text-right">Total</th>' +
'                </tr>' +
'            </thead>' +
'            <tbody>' +
                itemsHTML +
'            </tbody>' +
'        </table>' +
'        <div class="totals">' +
'            <div class="totals-row">' +
'                <span>Subtotal:</span>' +
'                <span>' + CURRENCY + Number(saleData.subtotal || 0).toFixed(2) + '</span>' +
'            </div>' +
'            <div class="totals-row">' +
'                <span>Discount:</span>' +
'                <span>' + CURRENCY + Number(saleData.discount || 0).toFixed(2) + '</span>' +
'            </div>' +
            taxRowHtml +
'            <div class="totals-row grand-total">' +
'                <span>TOTAL:</span>' +
'                <span>' + CURRENCY + Number(saleData.total_amount || 0).toFixed(2) + '</span>' +
'            </div>' +
'        </div>' +
'        <div class="payment-info">' +
'            <div class="payment-row">' +
'                <span>Payment Method:</span>' +
'                <span>' + paymentMethodLabel + '</span>' +
'            </div>' +
                (paymentLinesHtml ? paymentLinesHtml : '') +
                (showPaidRow ? (
'            <div class="payment-row">' +
'                <span>Paid:</span>' +
'                <span>' + CURRENCY + paidAmount.toFixed(2) + '</span>' +
'            </div>'
                ) : '') +
                (dueAmount > 0 ? (
'            <div class="payment-row">' +
'                <span>Due:</span>' +
'                <span>' + CURRENCY + dueAmount.toFixed(2) + '</span>' +
'            </div>'
                ) : '') +
                (hasCashPayment && changeAmount > 0 ? (
'            <div class="payment-row">' +
'                <span>Change:</span>' +
'                <span>' + CURRENCY + changeAmount.toFixed(2) + '</span>' +
'            </div>'
                ) : '') +
'        </div>' +
'        <div class="footer">' +
'            <div>Thank You For Your Purchase!</div>' +
'            <div>Please visit us again</div>' +
            '<div style="margin-top: 10px;">' + poweredByHtml + '</div>' +
'        </div>' +
'    </div>' +
'    <script>' +
'        setTimeout(function() { window.print(); window.close(); }, 500);' +
'    <\/script>' +
'</body>' +
'</html>';
        
        printWindow.document.write(receiptHTML);
        printWindow.document.close();
    }

    // Initialize: fetch current empty cart by clearing then rendering
    (async function init(){
        try {
            const cart = await postJSON('{{ route('pos.cart.clear') }}');
            renderCart(cart);
        } catch(err){ console.error('Init cart failed', err); }
    })();

    })(); // end IIFE
</script>
@endpush

<!-- Quotation Print Confirmation Modal -->
<div id="quotation-print-modal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center px-4" style="z-index: 9999;">
    <div class="bg-white rounded-2xl w-full max-w-3xl shadow-2xl overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800">Print Quotation</h3>
            <button type="button" data-close-print-modal class="text-gray-500 hover:text-gray-800">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-6 space-y-4">
            <p class="text-sm text-gray-600">Would you like to open the quotation in print view? The document will be rendered in a new tab.</p>
            <div class="h-64 border border-gray-200 rounded-lg overflow-hidden">
                <iframe id="quotation-preview-iframe" class="w-full h-full" src="about:blank" loading="lazy"></iframe>
            </div>
            <div class="flex justify-end gap-2">
                <button id="cancel-print-quotation" type="button" class="px-4 py-2 rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-100">Cancel</button>
                <button id="confirm-print-quotation" type="button" class="px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">Open Print View</button>
            </div>
        </div>
    </div>
</div>

<!-- Unit Selection Modal -->
<div id="unit-selection-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4">
        <div class="bg-gradient-to-r from-blue-600 to-blue-700 text-white p-6 rounded-t-xl">
            <div class="flex justify-between items-center">
                <h3 class="text-xl font-bold">
                    <i class="fas fa-box-open mr-2"></i>Select Unit
                </h3>
                <button type="button" id="close-unit-modal" class="text-white hover:text-gray-200 text-2xl">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <p id="modal-product-name" class="text-blue-100 mt-2 text-sm"></p>
        </div>
        
        <div class="p-6">
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-ruler-combined mr-1"></i>Select Unit Type
                </label>
                <div id="unit-options" class="grid grid-cols-2 gap-3"></div>
            </div>
            
            <div class="mb-6">
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-sort-numeric-up mr-1"></i>Quantity
                </label>
                <input 
                    type="number" 
                    id="modal-quantity" 
                    min="1" 
                    value="1" 
                    class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-lg"
                    placeholder="Enter quantity"
                >
            </div>
            
            <div class="flex space-x-3">
                <button 
                    type="button" 
                    id="confirm-add-to-cart" 
                    class="flex-1 bg-gradient-to-r from-green-600 to-green-700 text-white px-6 py-3 rounded-lg hover:from-green-700 hover:to-green-800 transition font-semibold shadow-lg">
                    <i class="fas fa-cart-plus mr-2"></i>Add to Cart
                </button>
                <button 
                    type="button" 
                    id="cancel-unit-modal" 
                    class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition font-semibold">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function() {
    const modal = document.getElementById('unit-selection-modal');
    const closeBtn = document.getElementById('close-unit-modal');
    const cancelBtn = document.getElementById('cancel-unit-modal');
    const confirmBtn = document.getElementById('confirm-add-to-cart');
    const productNameEl = document.getElementById('modal-product-name');
    const unitOptionsEl = document.getElementById('unit-options');
    const quantityInput = document.getElementById('modal-quantity');
    
    let selectedProductId = null;
    let selectedProductPrice = null;
    let selectedUnit = null;
    let availableUnits = @json($allUnits ?? []);
    let editingCartKey = null;
    
    // Close modal handlers
    const closeModal = () => {
        modal.classList.add('hidden');
        selectedProductId = null;
        selectedProductPrice = null;
        selectedUnit = null;
        editingCartKey = null;
        unitOptionsEl.innerHTML = '';
        quantityInput.value = 1;
    };
    
    closeBtn.addEventListener('click', closeModal);
    cancelBtn.addEventListener('click', closeModal);
    
    // Click outside modal to close
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });
    
    // Click product card: auto-add default unit (no unit modal)
    document.addEventListener('click', (e) => {
        const productCard = e.target.closest('.add-to-cart');
        if (!productCard) return;

        e.preventDefault();
        e.stopPropagation();

        const productId = productCard.dataset.productId;
        const stockQty = Number(productCard.dataset.stockQuantity || '0');
        if (Number.isFinite(stockQty) && stockQty <= 0) {
            (window.showToast || showToast)?.('warning', 'Out of stock');
            return;
        }
        addProductToCart(productId, 1, null);
    });

    // Click cart item "Edit": open unit/qty modal
    document.getElementById('cart-items')?.addEventListener('click', (e) => {
        const btn = e.target.closest('.cart-item-unit-edit');
        if (!btn) return;
        const cartKey = btn.dataset.cartKey;
        const cart = window.__posCartSnapshot || {};
        const items = cart.items || {};
        const it = items[cartKey];
        if (!it || (Number(it.qty || 0) < 0)) return;

        editingCartKey = cartKey;
        selectedProductId = it.id;
        selectedUnit = null;
        selectedProductPrice = Number(it.price || 0);

        productNameEl.textContent = String(it.name || '');
        quantityInput.value = Math.max(1, parseInt(it.qty || '1'));

        const visibleUnits = Array.isArray(it.visible_units) ? it.visible_units.map(v => Number(v)) : [];
        const filteredUnits = availableUnits.filter(unit => visibleUnits.includes(Number(unit.id)));

        unitOptionsEl.innerHTML = '';
        if (!filteredUnits.length) {
            // If no units are configured, just allow qty editing.
            unitOptionsEl.innerHTML = '<div class="text-sm text-slate-600">No unit options for this product</div>';
            modal.classList.remove('hidden');
            quantityInput.focus();
            return;
        }

        filteredUnits.forEach(unit => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'unit-option-btn px-4 py-3 border-2 border-gray-300 rounded-lg hover:border-blue-500 hover:bg-blue-50 transition text-center';
            btn.dataset.unitId = unit.id;
            btn.dataset.unitName = unit.short_name || unit.name;
            btn.innerHTML = `
                <div class="font-semibold text-gray-800">${unit.short_name || unit.name}</div>
                <div class="text-xs text-gray-500">${unit.name}</div>
            `;

            btn.addEventListener('click', () => {
                document.querySelectorAll('.unit-option-btn').forEach(b => {
                    b.classList.remove('border-blue-500', 'bg-blue-50', 'ring-2', 'ring-blue-300');
                });
                btn.classList.add('border-blue-500', 'bg-blue-50', 'ring-2', 'ring-blue-300');
                selectedUnit = {
                    id: Number(unit.id),
                    name: unit.short_name || unit.name
                };
            });

            unitOptionsEl.appendChild(btn);
        });

        // Auto-select current unit if possible, else first
        const currentUnitId = Number(it.unit_id || 0);
        const targetId = currentUnitId || Number(filteredUnits[0].id);
        const targetBtn = unitOptionsEl.querySelector(`.unit-option-btn[data-unit-id="${targetId}"]`) || unitOptionsEl.querySelector('.unit-option-btn');
        targetBtn?.click();

        modal.classList.remove('hidden');
        quantityInput.focus();
    });
    
    // Confirm add to cart
    confirmBtn.addEventListener('click', async () => {
        const quantity = parseInt(quantityInput.value) || 1;
        if (quantity < 1) {
            (window.showToast || showToast)?.('warning', 'Quantity must be at least 1');
            return;
        }

        // Edit mode: update unit/qty of existing cart row
        if (editingCartKey) {
            try {
                let currentKey = editingCartKey;
                const cart = window.__posCartSnapshot || {};
                const it = (cart.items || {})[currentKey];
                const currentUnitId = Number(it?.unit_id || 0);
                const newUnitId = Number(selectedUnit?.id || 0);

                if (newUnitId && currentUnitId && newUnitId !== currentUnitId) {
                    const cartAfterUnit = await postJSON('{{ route('pos.cart.unit') }}', { cart_key: currentKey, unit_id: newUnitId });
                    renderCart(cartAfterUnit);
                    const newKey = `${selectedProductId}_unit_${newUnitId}`;
                    if (cartAfterUnit?.items && cartAfterUnit.items[newKey]) {
                        currentKey = newKey;
                    }
                }

                const cartAfterQty = await postJSON('{{ route('pos.cart.update') }}', { cart_key: currentKey, qty: quantity });
                renderCart(cartAfterQty);
                (window.showToast || showToast)?.('success', 'Item updated');
                closeModal();
            } catch (err) {
                (window.showToast || showToast)?.('error', err?.message || 'Failed to update item');
            }
            return;
        }

        (window.showToast || showToast)?.('warning', 'Select an item to edit');
    });
    
    // Add product to cart function
    async function addProductToCart(productId, quantity = 1, unit = null) {
        try {
            const response = await fetch('{{ route('pos.cart.add') }}', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ 
                    product_id: productId,
                    quantity: quantity,
                    unit: unit
                })
            });
            
            if (!response.ok) {
                let message = 'Failed to add to cart';
                try {
                    const payload = await response.json();
                    if (payload && payload.message) message = payload.message;
                } catch (_) {
                    // ignore
                }
                if (typeof showToast === 'function') showToast('error', message);
                throw new Error(message);
            }
            
            const cart = await response.json();
            // Trigger cart render (assuming renderCart is available in parent scope)
            if (window.renderCart) {
                window.renderCart(cart);
            } else {
                location.reload();
            }
        } catch (error) {
            console.error('Error adding to cart:', error);
            if (typeof showToast === 'function') {
                showToast('error', error.message || 'Failed to add product to cart');
            } else {
                (window.showToast || (()=>{}))('error', error.message || 'Failed to add product to cart');
            }
        }
    }

    window.addProductToCart = addProductToCart;
    
    // Enter key to confirm
    quantityInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            confirmBtn.click();
        }
    });
})();
</script>
@endpush
