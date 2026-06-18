@extends('layouts.app')

@section('title', 'Stock Report')
@section('page-title', 'Stock Report')

@section('content')
@php
    $controls = is_array($controls ?? null) ? $controls : [];
    $priceVisiblePct = (float) ($controls['price_visible_percentage'] ?? 100);
    $profitVisiblePct = (float) ($controls['profit_visible_percentage'] ?? 100);
    $qtyVisiblePct = (float) ($controls['qty_visible_percentage'] ?? 100);
    $stockVisiblePct = (float) ($controls['stock_visible_percentage'] ?? 100);
    $inventoryQtyPct = min($qtyVisiblePct, $stockVisiblePct);
    $applyPct = function ($value, $pct) {
        $pct = max(0, min(100, (float) $pct));
        return (float) $value * ($pct / 100);
    };
    $maskMoney = function ($value, $forceHide = false) use ($controls, $priceVisiblePct, $applyPct) {
        if (\App\Services\PrivacyModeService::isActiveForUser(auth()->user()) && \App\Services\PrivacyModeService::shouldMaskForCurrentPage()) {
            return \App\Services\PrivacyModeService::maskAmount((float) $value);
        }
        if ($forceHide || !empty($controls['hide_price_wise_data'])) {
            return '—';
        }

        $masked = $applyPct((float) $value, $priceVisiblePct);
        $roundToWhole = $priceVisiblePct < 100;

        return number_format($roundToWhole ? round($masked) : $masked, $roundToWhole ? 0 : 2);
    };
    $maskQty = function ($value, $forceHide = false) use ($controls, $qtyVisiblePct, $applyPct) {
        if ($forceHide || !empty($controls['hide_qty_wise_data'])) {
            return '—';
        }

        return number_format(round($applyPct((float) $value, $qtyVisiblePct)), 0);
    };
    $maskInventoryQty = function ($value, $forceHide = false) use ($controls, $inventoryQtyPct, $applyPct) {
        if ($forceHide || !empty($controls['hide_qty_wise_data'])) {
            return '—';
        }

        return number_format(round($applyPct((float) $value, $inventoryQtyPct)), 0);
    };
    $maskStockQty = function ($value, $forceHide = false) use ($controls, $inventoryQtyPct, $applyPct) {
        if ($forceHide || !empty($controls['hide_qty_wise_data']) || !empty($controls['hide_actual_stock_quantity'])) {
            return '—';
        }

        return number_format(round($applyPct((float) $value, $inventoryQtyPct)), 0);
    };
    $maskStockMoney = function ($value, $forceHide = false) use ($controls, $inventoryQtyPct, $priceVisiblePct, $applyPct) {
        if ($forceHide || !empty($controls['hide_stock_values']) || !empty($controls['hide_price_wise_data'])) {
            return '—';
        }

        $stockAdjusted = $applyPct((float) $value, $inventoryQtyPct);
        $masked = $applyPct($stockAdjusted, $priceVisiblePct);
        $roundToWhole = $inventoryQtyPct < 100 || $priceVisiblePct < 100;

        return number_format($roundToWhole ? round($masked) : $masked, $roundToWhole ? 0 : 2);
    };
    $maskProfitMoney = function ($value, $forceHide = false) use ($controls, $profitVisiblePct, $applyPct) {
        if ($forceHide || !empty($controls['hide_profit_loss'])) {
            return '—';
        }

        $masked = $applyPct((float) $value, $profitVisiblePct);
        $roundToWhole = $profitVisiblePct < 100;

        return number_format($roundToWhole ? round($masked) : $masked, $roundToWhole ? 0 : 2);
    };
@endphp
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div></div>
        <div class="flex items-center gap-2">
            <a href="{{ route('reports.stock.csv', request()->query()) }}" target="_blank" rel="noopener" class="px-3 py-2 bg-emerald-600 text-white rounded text-sm"><i class="fas fa-file-excel mr-1"></i>Excel</a>
            <a href="{{ route('reports.stock.pdf', request()->query()) }}" target="_blank" rel="noopener" class="px-3 py-2 bg-blue-600 text-white rounded text-sm"><i class="fas fa-file-pdf mr-1"></i>PDF</a>
        </div>
    </div>

    <div class="bg-white p-4 rounded shadow">
        <form id="stockReportFilters" method="GET" action="{{ route('reports.stock') }}" class="grid grid-cols-1 md:grid-cols-6 gap-3 items-end">
        <div>
            <label class="text-sm font-medium text-gray-600">Store</label>
            <select name="store_id" class="mt-1 border rounded px-3 py-2 text-sm w-48 bg-white">
                <option value="">All Stores</option>
                @if(isset($stores))
                    @foreach($stores as $s)
                        <option value="{{ $s->id }}" @selected(request('store_id') == $s->id)>{{ $s->name }}</option>
                    @endforeach
                @endif
            </select>
        </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Stock Filter</label>
                <select name="low_stock" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    <option value="">All Products</option>
                    <option value="1" {{ !empty($lowStockOnly) ? 'selected' : '' }}>Low Stock Only</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                <input
                    id="stockReportSearch"
                    type="text"
                    name="search"
                    value="{{ $search ?? '' }}"
                    placeholder="Name or barcode"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg"
                    autocomplete="off"
                />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                <select name="category_id" id="stockMainCategory" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    <option value="">All Categories</option>
                    @foreach(($categories ?? collect()) as $category)
                        <option value="{{ $category->id }}" {{ (int) ($selectedCategoryId ?? 0) === (int) $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Sub Category</label>
                <select name="subcategory_id" id="stockSubCategory" class="w-full px-3 py-2 border border-gray-300 rounded-lg" data-selected="{{ $selectedSubcategoryId ?? '' }}">
                    <option value="">All Sub Categories</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Brand</label>
                <select name="brand_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    <option value="">All Brands</option>
                    @foreach(($brands ?? collect()) as $brand)
                        <option value="{{ $brand->id }}" {{ (int) ($selectedBrandId ?? 0) === (int) $brand->id ? 'selected' : '' }}>{{ $brand->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm">Apply</button>
                <a href="{{ route('reports.stock') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm">Reset</a>
            </div>
        </form>
    </div>

    <script>
        async function stockFetchSubcategories(parentId) {
            const resp = await fetch(`{{ url('categories') }}/${parentId}/children`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await resp.json();
            return Array.isArray(data.children) ? data.children : [];
        }

        async function stockRefreshSubcategories() {
            const main = document.getElementById('stockMainCategory');
            const sub = document.getElementById('stockSubCategory');
            if (!main || !sub) return;

            const parentId = (main.value || '').trim();
            const selected = (sub.dataset.selected || '').trim();
            sub.innerHTML = '<option value="">All Sub Categories</option>';

            if (!parentId) {
                return;
            }

            let children = [];
            try {
                children = await stockFetchSubcategories(parentId);
            } catch (e) {
                children = [];
            }

            children.forEach(child => {
                sub.add(new Option(child.name, child.id, false, false));
            });

            if (selected) {
                sub.value = String(selected);
            }
        }

        document.getElementById('stockMainCategory')?.addEventListener('change', () => {
            const sub = document.getElementById('stockSubCategory');
            if (sub) sub.dataset.selected = '';
            stockRefreshSubcategories();
        });

        stockRefreshSubcategories();
    </script>

    @if(empty($controls['hide_widgets']))
    <div class="grid md:grid-cols-6 gap-4">
        <div class="bg-white p-4 rounded shadow">
            <p class="text-xs text-gray-500">Total Products</p>
            <p class="text-xl font-semibold">{{ $summary['total_products'] }}</p>
        </div>
        <div class="bg-white p-4 rounded shadow">
            <p class="text-xs text-gray-500">Low Stock</p>
            <p class="text-xl font-semibold text-red-600">{{ !empty($controls['hide_actual_stock_count']) ? '—' : $summary['low_stock'] }}</p>
        </div>
        <div class="bg-white p-4 rounded shadow">
            <p class="text-xs text-gray-500">Total Units In Stock</p>
            <p class="text-xl font-semibold">{{ $maskStockQty(($summary['total_stock'] ?? 0), !empty($controls['hide_actual_stock_quantity'])) }}</p>
        </div>
        <div class="bg-white p-4 rounded shadow">
            <p class="text-xs text-gray-500">Total Cost Price</p>
            <p class="text-xl font-semibold">{{ $maskStockMoney(($summary['total_cost_value'] ?? 0), !empty($controls['hide_stock_values']) || !empty($controls['hide_actual_stock_price'])) }}</p>
        </div>
        <div class="bg-white p-4 rounded shadow">
            <p class="text-xs text-gray-500">Total Selling Price</p>
            <p class="text-xl font-semibold">{{ $maskStockMoney(($summary['total_selling_value'] ?? 0), !empty($controls['hide_stock_values']) || !empty($controls['hide_actual_stock_price'])) }}</p>
        </div>
        <div class="bg-white p-4 rounded shadow">
            <p class="text-xs text-gray-500">After Sale Profit (Selling - Cost)</p>
            <p class="text-xl font-semibold {{ ($summary['expected_profit'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}">{{ $maskProfitMoney(($summary['expected_profit'] ?? 0), !empty($controls['hide_stock_values']) || !empty($controls['hide_profit_loss'])) }}</p>
        </div>
    </div>
    @endif

    @if(empty($controls['hide_tables']))
    <div class="bg-white rounded shadow overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50">
                <tr class="text-left">
                    <th class="px-3 py-2">Product</th>
                    <th class="px-3 py-2">Category</th>
                    <th class="px-3 py-2">Brand</th>
                    <th class="px-3 py-2">Cost Price</th>
                    <th class="px-3 py-2">Selling Price</th>
                    <th class="px-3 py-2">Purchased Qty</th>
                    <th class="px-3 py-2">Sold Qty</th>
                    <th class="px-3 py-2">Current Stock</th>
                    <th class="px-3 py-2">Total Cost</th>
                    <th class="px-3 py-2">Total Selling</th>
                    <th class="px-3 py-2">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $row)
                    <tr class="border-t {{ $row['low_stock'] ? 'bg-red-50' : '' }}">
                        <td class="px-3 py-2 font-medium">{{ !empty($controls['hide_product_wise_data']) ? 'Hidden Product' : $row['product']->name }}</td>
                        <td class="px-3 py-2">{{ $row['product']->categories->pluck('name')->join(', ') ?: ($row['product']->category->name ?? '-') }}</td>
                        <td class="px-3 py-2">{{ $row['product']->brands->pluck('name')->join(', ') ?: ($row['product']->brand->name ?? '-') }}</td>
                        <td class="px-3 py-2">{{ $maskMoney(($row['product']->cost_price ?? 0), !empty($controls['hide_actual_purchase_price']) || !empty($controls['hide_actual_stock_price'])) }}</td>
                        <td class="px-3 py-2">{{ $maskMoney(($row['product']->selling_price ?? 0), !empty($controls['hide_actual_stock_price'])) }}</td>
                        <td class="px-3 py-2">{{ $maskInventoryQty($row['purchased']) }}</td>
                        <td class="px-3 py-2">{{ $maskInventoryQty($row['sold']) }}</td>
                        <td class="px-3 py-2">{{ $maskStockQty($row['current_stock']) }}</td>
                        @php
                            $lineCost    = (float) ($row['product']->cost_price ?? 0) * max(0, (int) $row['current_stock']);
                            $lineSelling = (float) ($row['product']->selling_price ?? 0) * max(0, (int) $row['current_stock']);
                        @endphp
                        <td class="px-3 py-2">{{ $maskStockMoney($lineCost, !empty($controls['hide_stock_values']) || !empty($controls['hide_actual_stock_price'])) }}</td>
                        <td class="px-3 py-2">{{ $maskStockMoney($lineSelling, !empty($controls['hide_stock_values']) || !empty($controls['hide_actual_stock_price'])) }}</td>
                        <td class="px-3 py-2">
                            <span class="px-2 py-1 rounded text-xs {{ $row['low_stock'] ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' }}">{{ $row['low_stock'] ? 'Low' : 'OK' }}</span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="px-3 py-6 text-center text-gray-500">No product data.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="bg-white px-4 py-3 rounded shadow text-sm flex flex-col sm:flex-row sm:items-center sm:justify-end gap-2 sm:gap-6">
        <div>
            <span class="text-gray-500">Page Total Cost:</span>
            <span class="font-semibold">{{ $maskStockMoney(($pageTotalCost ?? 0), !empty($controls['hide_stock_values']) || !empty($controls['hide_actual_stock_price'])) }}</span>
        </div>
        <div>
            <span class="text-gray-500">Page Total Selling:</span>
            <span class="font-semibold">{{ $maskStockMoney(($pageTotalSelling ?? 0), !empty($controls['hide_stock_values']) || !empty($controls['hide_actual_stock_price'])) }}</span>
        </div>
    </div>

    @if($paginator->hasPages())
    <div class="flex flex-col sm:flex-row items-center justify-between gap-3 bg-white px-4 py-3 rounded shadow text-sm">
        <div class="text-gray-500">
            Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }} products
        </div>
        <div>
            {{ $paginator->links() }}
        </div>
    </div>
    @endif
    @endif
</div>
@endsection

@push('scripts')
<script>
(() => {
    const form = document.getElementById('stockReportFilters');
    const searchInput = document.getElementById('stockReportSearch');
    if (!form || !searchInput) return;

    let timer = null;
    const debounceMs = 400;

    searchInput.addEventListener('input', () => {
        window.clearTimeout(timer);
        timer = window.setTimeout(() => form.submit(), debounceMs);
    });
})();
</script>
@endpush
