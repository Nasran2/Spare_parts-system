@extends('layouts.app')

@section('title', 'View Product - ' . $product->name)
@section('page-title', 'Product Details')

@section('content')
@php
    $controls = \App\Services\DashboardVisibilityService::configForUser(auth()->user());
    $priceVisiblePct = (float) ($controls['price_visible_percentage'] ?? 100);
    $stockVisiblePct = (float) ($controls['stock_visible_percentage'] ?? 100);
    $applyPct = function ($value, $pct) {
        return (float) $value * (max(0, min(100, (float) $pct)) / 100);
    };
    $maskMoney = function ($value, $forceHide = false) use ($controls, $priceVisiblePct, $applyPct) {
        if ($forceHide || !empty($controls['hide_price_wise_data'])) {
            return '—';
        }
        $masked = $applyPct((float) $value, $priceVisiblePct);
        $roundToWhole = $priceVisiblePct < 100;
        return number_format($roundToWhole ? round($masked) : $masked, $roundToWhole ? 0 : 2);
    };
    $maskStockQty = function ($value, $forceHide = false) use ($controls, $stockVisiblePct, $applyPct) {
        if ($forceHide || !empty($controls['hide_qty_wise_data']) || !empty($controls['hide_actual_stock_quantity'])) {
            return '—';
        }
        return number_format(round($applyPct((float) $value, $stockVisiblePct)), 0);
    };
@endphp

<div class="space-y-6">

    <!-- Header Actions -->
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-xl font-bold text-gray-800">{{ $product->name }}</h3>
            <p class="text-sm text-gray-600">Product details, per-store quantities, and history logs</p>
        </div>
        <div class="flex items-center gap-3">
            <a 
                href="{{ route('products.index') }}" 
                class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition font-medium text-sm flex items-center"
            >
                <i class="fas fa-arrow-left mr-2"></i>Back to List
            </a>
            @if(auth()->user()?->hasPermission('products.edit'))
            <a 
                href="{{ route('products.edit', $product) }}" 
                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium text-sm flex items-center"
            >
                <i class="fas fa-edit mr-2"></i>Edit Product
            </a>
            @endif
        </div>
    </div>

    <!-- Overview Card (Main Info) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Left: Image & Key info -->
        <div class="bg-white rounded-xl shadow-md p-6 flex flex-col items-center justify-center text-center">
            <div class="w-32 h-32 bg-blue-100 rounded-xl flex items-center justify-center mb-4 overflow-hidden border border-gray-200">
                @if($product->image)
                    <img src="{{ asset('storage/' . $product->image) }}" alt="{{ $product->name }}" class="w-full h-full object-cover">
                @else
                    <i class="fas fa-cog text-blue-600 text-5xl animate-spin-slow"></i>
                @endif
            </div>
            <h4 class="font-bold text-lg text-gray-800">{{ $product->name }}</h4>
            <span class="font-mono text-sm text-gray-500 mt-1 bg-gray-100 px-3 py-1 rounded-full">{{ $product->sku }}</span>

            <div class="w-full grid grid-cols-2 gap-4 mt-6 pt-6 border-t border-gray-100">
                <div>
                    <span class="text-xs text-gray-400 block uppercase font-semibold">Total Stock</span>
                    <span class="text-xl font-bold text-gray-800 mt-1 block">
                        {{ $maskStockQty($product->stock_quantity) }}
                    </span>
                </div>
                <div>
                    <span class="text-xs text-gray-400 block uppercase font-semibold">Alert Level</span>
                    <span class="text-xl font-bold text-gray-800 mt-1 block">
                        {{ $product->alert_quantity }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Right: Detail Fields -->
        <div class="lg:col-span-2 bg-white rounded-xl shadow-md p-6">
            <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-400 mb-4 flex items-center">
                <i class="fas fa-info-circle mr-2 text-blue-500"></i>Product Overview
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                <div>
                    <span class="text-xs text-gray-400 block">Category</span>
                    <span class="text-sm font-semibold text-gray-700 block mt-0.5">
                        {{ $product->categories->pluck('name')->join(', ') ?: 'Uncategorized' }}
                    </span>
                </div>
                <div>
                    <span class="text-xs text-gray-400 block">Brand</span>
                    <span class="text-sm font-semibold text-gray-700 block mt-0.5">
                        {{ $product->brands->pluck('name')->join(', ') ?: 'No Brand' }}
                    </span>
                </div>
                <div>
                    <span class="text-xs text-gray-400 block">Unit Type</span>
                    <span class="text-sm font-semibold text-gray-700 block mt-0.5">
                        {{ $product->unit->name ?? 'None' }} ({{ $product->unit->short_name ?? 'N/A' }})
                    </span>
                </div>
                <div>
                    <span class="text-xs text-gray-400 block">Status</span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold mt-1 {{ $product->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                        {{ $product->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>
                <div>
                    <span class="text-xs text-gray-400 block">Cost Price</span>
                    <span class="text-sm font-bold text-gray-700 block mt-0.5">
                        {{ $currency }}{{ $maskMoney($product->cost_price, !empty($controls['hide_actual_purchase_price']) || !empty($controls['hide_actual_stock_price'])) }}
                    </span>
                </div>
                <div>
                    <span class="text-xs text-gray-400 block">Selling Price</span>
                    <span class="text-sm font-bold text-gray-700 block mt-0.5">
                        {{ $currency }}{{ $maskMoney($product->selling_price, !empty($controls['hide_actual_stock_price'])) }}
                    </span>
                </div>
                <div class="md:col-span-2">
                    <span class="text-xs text-gray-400 block">Description</span>
                    <p class="text-sm text-gray-700 mt-1 bg-gray-50 p-3 rounded-lg border border-gray-100 whitespace-pre-line">
                        {{ $product->description ?: 'No description provided.' }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Store Stocks Breakdown -->
    <div class="bg-white rounded-xl shadow-md p-6">
        <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-400 mb-4 flex items-center">
            <i class="fas fa-store mr-2 text-green-500"></i>Store Stock & Exclusions
        </h3>
        <div class="overflow-x-auto">
            <table class="w-full border-collapse">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Store</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Address</th>
                        <th class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Availability</th>
                        <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Stock Qty</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @php
                        $hasStoreStockEntries = $product->storeStocks->isNotEmpty();
                    @endphp
                    @forelse($stores as $store)
                    @php
                        $isDefault = $store->is_default;
                        $isExcluded = $product->excludedStores->contains($store->id);
                        $storeStock = $product->storeStocks->where('store_id', $store->id)->sum('quantity');
                        if (!$hasStoreStockEntries && $isDefault) {
                            $storeStock = $product->stock_quantity;
                        }
                    @endphp
                    <tr class="{{ $isExcluded ? 'bg-red-50/20 text-gray-400' : 'bg-white text-gray-700' }}">
                        <td class="px-6 py-4">
                            <div class="flex items-center">
                                @if($isDefault)
                                    <span class="mr-1 text-yellow-500" title="Main Store">⭐</span>
                                @endif
                                <span class="font-semibold {{ $isExcluded ? 'line-through text-gray-400' : 'text-gray-800' }}">{{ $store->name }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-xs">
                            {{ $store->address ?: 'No Address' }}
                        </td>
                        <td class="px-6 py-4 text-center">
                            @if($isExcluded)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-red-100 text-red-800">
                                    Excluded
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-green-100 text-green-800">
                                    Available
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right font-bold text-sm">
                            @if($isExcluded)
                                <span class="text-gray-300">—</span>
                            @else
                                {{ $maskStockQty($storeStock) }} {{ $product->unit->short_name ?? 'pcs' }}
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">No stores set up.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Product History Tabs -->
    <div class="bg-white rounded-xl shadow-md p-6">
        <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-400 mb-4 flex items-center">
            <i class="fas fa-history mr-2 text-indigo-500"></i>Product Ledger & History
        </h3>

        <!-- Tab Headers -->
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex space-x-6" aria-label="Tabs" id="historyTabs">
                <button 
                    onclick="switchTab(event, 'tab-timeline')"
                    class="tab-btn border-indigo-500 text-indigo-600 whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm flex items-center"
                    data-target="tab-timeline"
                >
                    <i class="fas fa-stream mr-2"></i>Activity Timeline
                </button>
                <button 
                    onclick="switchTab(event, 'tab-purchases')"
                    class="tab-btn border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm flex items-center"
                    data-target="tab-purchases"
                >
                    <i class="fas fa-shopping-cart mr-2"></i>Purchases ({{ $purchaseItems->count() }})
                </button>
                <button 
                    onclick="switchTab(event, 'tab-sales')"
                    class="tab-btn border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm flex items-center"
                    data-target="tab-sales"
                >
                    <i class="fas fa-receipt mr-2"></i>Sales ({{ $saleItems->count() }})
                </button>
                <button 
                    onclick="switchTab(event, 'tab-transfers')"
                    class="tab-btn border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm flex items-center"
                    data-target="tab-transfers"
                >
                    <i class="fas fa-exchange-alt mr-2"></i>Store Transfers ({{ $transfers->count() }})
                </button>
                <button 
                    onclick="switchTab(event, 'tab-logs')"
                    class="tab-btn border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm flex items-center"
                    data-target="tab-logs"
                >
                    <i class="fas fa-info-circle mr-2"></i>System Activity Logs ({{ $logs->count() }})
                </button>
            </nav>
        </div>

        <!-- Tab Contents -->
        <div class="mt-6">

            <!-- Timeline Tab -->
            <div id="tab-timeline" class="tab-pane">
                <div class="flow-root">
                    <ul role="list" class="-mb-8">
                        @forelse($timeline as $event)
                        <li>
                            <div class="relative pb-8">
                                @if(!$loop->last)
                                    <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
                                @endif
                                <div class="relative flex space-x-3">
                                    <div>
                                        <span class="h-8 w-8 rounded-full flex items-center justify-center ring-8 ring-white {{ $event->icon }}">
                                            <i class="fas {{ explode(' ', $event->icon)[0] }} text-xs"></i>
                                        </span>
                                    </div>
                                    <div class="flex-1 min-w-0 pt-1.5 flex justify-between space-x-4">
                                        <div>
                                            <p class="text-sm text-gray-800 font-semibold">
                                                {{ $event->type }}: 
                                                @if($event->reference_route)
                                                    <a href="{{ $event->reference_route }}" class="text-indigo-600 hover:underline">{{ $event->reference }}</a>
                                                @else
                                                    <span class="text-gray-600">{{ $event->reference }}</span>
                                                @endif
                                            </p>
                                            <p class="text-xs text-gray-500 mt-0.5">
                                                {{ $event->entity }} | <span class="font-medium">Store: {{ $event->store }}</span>
                                            </p>
                                            <p class="text-xs text-gray-400 mt-1 italic">{{ $event->details }}</p>
                                        </div>
                                        <div class="text-right whitespace-nowrap text-xs text-gray-500">
                                            @if($event->qty_change !== null)
                                                <span class="text-sm font-bold block {{ $event->qty_change > 0 ? 'text-green-600' : ($event->qty_change < 0 ? 'text-red-600' : 'text-gray-600') }}">
                                                    {{ $event->qty_change > 0 ? '+' : '' }}{{ $maskStockQty($event->qty_change) }} {{ $product->unit->short_name ?? 'pcs' }}
                                                </span>
                                            @endif
                                            <time datetime="{{ $event->date }}">{{ $event->date->format('M d, Y h:i A') }}</time>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </li>
                        @empty
                        <p class="text-sm text-gray-500 text-center py-6">No product history events found.</p>
                        @endforelse
                    </ul>
                </div>
            </div>

            <!-- Purchases Tab -->
            <div id="tab-purchases" class="tab-pane hidden">
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200">
                                <th class="px-4 py-2 text-left text-xs font-bold text-gray-500 uppercase">Date</th>
                                <th class="px-4 py-2 text-left text-xs font-bold text-gray-500 uppercase">Purchase No</th>
                                <th class="px-4 py-2 text-left text-xs font-bold text-gray-500 uppercase">Supplier</th>
                                <th class="px-4 py-2 text-left text-xs font-bold text-gray-500 uppercase">Store</th>
                                <th class="px-4 py-2 text-right text-xs font-bold text-gray-500 uppercase">Quantity</th>
                                <th class="px-4 py-2 text-right text-xs font-bold text-gray-500 uppercase">Unit Cost</th>
                                <th class="px-4 py-2 text-right text-xs font-bold text-gray-500 uppercase">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($purchaseItems as $item)
                            @php($purchase = $item->purchase)
                            <tr class="hover:bg-gray-50 text-sm text-gray-700">
                                <td class="px-4 py-2">
                                    {{ $purchase->purchase_date ? $purchase->purchase_date->format('Y-m-d') : $purchase->created_at->format('Y-m-d') }}
                                </td>
                                <td class="px-4 py-2">
                                    <a href="{{ route('purchases.show', $purchase) }}" class="text-indigo-600 font-semibold hover:underline">{{ $purchase->purchase_no }}</a>
                                </td>
                                <td class="px-4 py-2">{{ $purchase->supplier->name ?? 'N/A' }}</td>
                                <td class="px-4 py-2">{{ $purchase->store->name ?? 'Main Store' }}</td>
                                <td class="px-4 py-2 text-right">{{ $maskStockQty($item->quantity) }}</td>
                                <td class="px-4 py-2 text-right">{{ $currency }}{{ $maskMoney($item->unit_cost) }}</td>
                                <td class="px-4 py-2 text-right font-semibold">{{ $currency }}{{ $maskMoney($item->total) }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="px-4 py-6 text-center text-sm text-gray-500">No purchases found.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Sales Tab -->
            <div id="tab-sales" class="tab-pane hidden">
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200">
                                <th class="px-4 py-2 text-left text-xs font-bold text-gray-500 uppercase">Date</th>
                                <th class="px-4 py-2 text-left text-xs font-bold text-gray-500 uppercase">Invoice No</th>
                                <th class="px-4 py-2 text-left text-xs font-bold text-gray-500 uppercase">Customer</th>
                                <th class="px-4 py-2 text-left text-xs font-bold text-gray-500 uppercase">Store</th>
                                <th class="px-4 py-2 text-right text-xs font-bold text-gray-500 uppercase">Quantity</th>
                                <th class="px-4 py-2 text-right text-xs font-bold text-gray-500 uppercase">Unit Price</th>
                                <th class="px-4 py-2 text-right text-xs font-bold text-gray-500 uppercase">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($saleItems as $item)
                            @php($sale = $item->sale)
                            <tr class="hover:bg-gray-50 text-sm text-gray-700">
                                <td class="px-4 py-2">
                                    {{ $sale->sale_date ? $sale->sale_date->format('Y-m-d') : $sale->created_at->format('Y-m-d') }}
                                </td>
                                <td class="px-4 py-2">
                                    <a href="{{ route('sales.show', $sale) }}" class="text-indigo-600 font-semibold hover:underline">{{ $sale->sale_no }}</a>
                                </td>
                                <td class="px-4 py-2">{{ $sale->customer->name ?? 'Walk-in Customer' }}</td>
                                <td class="px-4 py-2">{{ $sale->store->name ?? 'Main Store' }}</td>
                                <td class="px-4 py-2 text-right">{{ $maskStockQty($item->quantity) }}</td>
                                <td class="px-4 py-2 text-right">{{ $currency }}{{ $maskMoney($item->unit_price) }}</td>
                                <td class="px-4 py-2 text-right font-semibold">{{ $currency }}{{ $maskMoney($item->total) }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="px-4 py-6 text-center text-sm text-gray-500">No sales found.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Transfers Tab -->
            <div id="tab-transfers" class="tab-pane hidden">
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200">
                                <th class="px-4 py-2 text-left text-xs font-bold text-gray-500 uppercase">Date</th>
                                <th class="px-4 py-2 text-left text-xs font-bold text-gray-500 uppercase">Reference No</th>
                                <th class="px-4 py-2 text-left text-xs font-bold text-gray-500 uppercase">From Store</th>
                                <th class="px-4 py-2 text-left text-xs font-bold text-gray-500 uppercase">To Store</th>
                                <th class="px-4 py-2 text-right text-xs font-bold text-gray-500 uppercase">Quantity</th>
                                <th class="px-4 py-2 text-left text-xs font-bold text-gray-500 uppercase">Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($transfers as $transfer)
                            <tr class="hover:bg-gray-50 text-sm text-gray-700">
                                <td class="px-4 py-2">
                                    {{ $transfer->transfer_date ? $transfer->transfer_date->format('Y-m-d') : $transfer->created_at->format('Y-m-d') }}
                                </td>
                                <td class="px-4 py-2 font-semibold text-gray-800">{{ $transfer->reference_no ?? 'N/A' }}</td>
                                <td class="px-4 py-2">{{ $transfer->fromStore->name ?? 'Unknown' }}</td>
                                <td class="px-4 py-2">{{ $transfer->toStore->name ?? 'Unknown' }}</td>
                                <td class="px-4 py-2 text-right font-bold">{{ $maskStockQty($transfer->quantity) }}</td>
                                <td class="px-4 py-2 text-xs text-gray-500">{{ $transfer->notes ?: '—' }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-sm text-gray-500">No stock transfers found.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- System Activity Logs Tab -->
            <div id="tab-logs" class="tab-pane hidden">
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200">
                                <th class="px-4 py-2 text-left text-xs font-bold text-gray-500 uppercase">Timestamp</th>
                                <th class="px-4 py-2 text-left text-xs font-bold text-gray-500 uppercase">Action</th>
                                <th class="px-4 py-2 text-left text-xs font-bold text-gray-500 uppercase">User</th>
                                <th class="px-4 py-2 text-left text-xs font-bold text-gray-500 uppercase">IP Address</th>
                                <th class="px-4 py-2 text-left text-xs font-bold text-gray-500 uppercase">Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($logs as $log)
                            <tr class="hover:bg-gray-50 text-sm text-gray-700">
                                <td class="px-4 py-2">{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                                <td class="px-4 py-2">
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold uppercase bg-slate-100 text-slate-800">
                                        {{ $log->action }}
                                    </span>
                                </td>
                                <td class="px-4 py-2">{{ $log->user->name ?? 'System' }}</td>
                                <td class="px-4 py-2 font-mono text-xs text-gray-400">{{ $log->ip_address ?: '—' }}</td>
                                <td class="px-4 py-2 text-xs font-medium">{{ $log->description }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500">No activity logs found.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

</div>

<script>
    function switchTab(evt, tabId) {
        // Hide all tab-panes
        document.querySelectorAll('.tab-pane').forEach(el => el.classList.add('hidden'));
        
        // Show current tab-pane
        document.getElementById(tabId).classList.remove('hidden');

        // Remove active class from all tab buttons
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('border-indigo-500', 'text-indigo-600');
            btn.classList.add('border-transparent', 'text-gray-500');
        });

        // Add active class to clicked button
        evt.currentTarget.classList.remove('border-transparent', 'text-gray-500');
        evt.currentTarget.classList.add('border-indigo-500', 'text-indigo-600');
    }
</script>

<style>
    @keyframes spin-slow {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    .animate-spin-slow {
        animation: spin-slow 10s linear infinite;
    }
</style>
@endsection
