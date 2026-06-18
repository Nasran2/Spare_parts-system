@extends('layouts.app')

@section('title', 'Products')
@section('page-title', 'Products')

@section('content')
@php
    $controls = \App\Services\DashboardVisibilityService::configForUser(auth()->user());
    $priceVisiblePct = (float) ($controls['price_visible_percentage'] ?? 100);
    $stockVisiblePct = (float) ($controls['stock_visible_percentage'] ?? 100);
    $applyPct = function ($value, $pct) {
        $pct = max(0, min(100, (float) $pct));
        return (float) $value * ($pct / 100);
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
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Product Management</h3>
            <p class="text-sm text-gray-600">Manage your vehicle parts inventory</p>
        </div>
        <a 
            href="{{ route('products.create') }}" 
            class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg hover:from-blue-700 hover:to-blue-800 transition shadow-lg"
        >
            <i class="fas fa-plus mr-2"></i>Add New Product
        </a>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-md p-6">
        <form method="GET" action="{{ route('products.index') }}" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4">
            
            <!-- Search -->
            <div class="lg:col-span-2">
                <input 
                    type="text" 
                    name="search" 
                    value="{{ request('search') }}"
                    placeholder="Search by name, SKU, or barcode..."
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                >
            </div>

            <!-- Category Filter -->
            <div>
                <select 
                    name="category_id" 
                    id="mainCategoryFilter"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                >
                    <option value="">All Categories</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" {{ (int) request('category_id') === (int) $category->id ? 'selected' : '' }}>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Sub Category Filter -->
            <div>
                <select
                    name="subcategory_id"
                    id="subCategoryFilter"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    data-selected="{{ request('subcategory_id') }}"
                >
                    <option value="">All Sub Categories</option>
                    @foreach(($subcategories ?? collect()) as $subcategory)
                        <option value="{{ $subcategory->id }}" {{ (int) request('subcategory_id') === (int) $subcategory->id ? 'selected' : '' }}>
                            {{ $subcategory->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Store Filter -->
            <div>
                <select 
                    name="store_id" 
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                >
                    @if(isset($stores))
                        @foreach($stores as $store)
                            <option value="{{ $store->id }}" {{ (int) request('store_id') === (int) $store->id || (!request('store_id') && $store->is_default) ? 'selected' : '' }}>
                                {{ $store->name }}
                            </option>
                        @endforeach
                    @endif
                </select>
            </div>

            <!-- Filter Button -->
            <div class="flex gap-2">
                <button 
                    type="submit" 
                    class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
                >
                    <i class="fas fa-filter mr-2"></i>Filter
                </button>
                <a 
                    href="{{ route('products.index') }}" 
                    class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition"
                >
                    <i class="fas fa-redo"></i>
                </a>
            </div>
        </form>
    </div>

    <script>
        async function fetchSubcategoriesForFilter(parentId) {
            const resp = await fetch(`{{ url('categories') }}/${parentId}/children`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await resp.json();
            return Array.isArray(data.children) ? data.children : [];
        }

        async function refreshSubcategoryFilter() {
            const main = document.getElementById('mainCategoryFilter');
            const sub = document.getElementById('subCategoryFilter');
            if (!main || !sub) return;

            // Store initial (all subcategories) options so we can restore when main category is cleared
            if (!sub.dataset.allOptionsHtml) {
                sub.dataset.allOptionsHtml = sub.innerHTML;
            }

            const parentId = (main.value || '').trim();
            const selected = (sub.dataset.selected || '').trim();

            if (!parentId) {
                sub.innerHTML = sub.dataset.allOptionsHtml || '<option value="">All Sub Categories</option>';
                if (selected) {
                    sub.value = String(selected);
                }
                return;
            }

            sub.innerHTML = '<option value="">All Sub Categories</option>';

            let children = [];
            try {
                children = await fetchSubcategoriesForFilter(parentId);
            } catch (e) {
                children = [];
            }

            // If fetch fails or the category has no children, keep showing all subcategories
            if (!Array.isArray(children) || children.length === 0) {
                sub.innerHTML = sub.dataset.allOptionsHtml || '<option value="">All Sub Categories</option>';
                if (selected) {
                    sub.value = String(selected);
                }
                return;
            }

            children.forEach(child => {
                const opt = new Option(child.name, child.id, false, false);
                sub.add(opt);
            });

            if (selected) {
                sub.value = String(selected);
            }
        }

        document.getElementById('mainCategoryFilter')?.addEventListener('change', () => {
            const sub = document.getElementById('subCategoryFilter');
            if (sub) sub.dataset.selected = '';
            refreshSubcategoryFilter();
        });

        refreshSubcategoryFilter();
    </script>

    <!-- Products Table -->
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Product</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">SKU</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Category</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Prices</th>
                        <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Stock</th>
                        <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($products as $product)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-6 py-4">
                            <div class="flex items-center">
                                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                    @if($product->image)
                                        <img src="{{ asset('storage/' . $product->image) }}" alt="{{ $product->name }}" class="w-full h-full object-cover rounded-lg">
                                    @else
                                        <i class="fas fa-cog text-blue-600 text-xl"></i>
                                    @endif
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-800">{{ $product->name }}</p>
                                    <p class="text-xs text-gray-500">{{ $product->brands->pluck('name')->join(', ') ?: 'No Brand' }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="font-mono text-sm text-gray-700">{{ $product->sku }}</span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-sm text-gray-700">{{ $product->categories->pluck('name')->join(', ') ?: 'Uncategorized' }}</span>
                        </td>
                        <td class="px-6 py-4 align-top">
                            <div class="text-sm space-y-1">
                                <p class="font-semibold text-gray-800">Base: {{ $currency }}{{ $maskMoney($product->selling_price, !empty($controls['hide_actual_stock_price'])) }} <span class="text-xs text-gray-500">(per {{ $product->unit->short_name ?? 'base' }})</span></p>
                                <p class="text-xs text-gray-500">Base Cost: {{ $currency }}{{ $maskMoney($product->cost_price, !empty($controls['hide_actual_purchase_price']) || !empty($controls['hide_actual_stock_price'])) }}</p>
                            </div>
                        </td>
                        @php
                            $displayStock = clone $product;
                            $activeStoreId = request('store_id') ?: ($stores->firstWhere('is_default', true)->id ?? null);
                            if ($activeStoreId && $product->relationLoaded('storeStocks') && $product->storeStocks->where('store_id', $activeStoreId)->isNotEmpty()) {
                                $displayStock->stock_quantity = $product->storeStocks->where('store_id', $activeStoreId)->first()->quantity;
                            } elseif ($activeStoreId) {
                                $displayStock->stock_quantity = 0;
                            }
                        @endphp
                        <td class="px-6 py-4 text-center">
                            @if($displayStock->stock_quantity <= $product->alert_quantity)
                                <span class="inline-flex items-center px-3 py-1 bg-red-100 text-red-700 rounded-full text-xs font-semibold">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>{{ $maskStockQty($displayStock->stock_quantity) }}
                                </span>
                            @else
                                <span class="inline-flex items-center px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-semibold">
                                    {{ $maskStockQty($displayStock->stock_quantity) }}
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center">
                            @if($product->is_active)
                                <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-semibold">Active</span>
                            @else
                                <span class="px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-xs font-semibold">Inactive</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center">
                            <div class="flex items-center justify-center space-x-2">
                                <a 
                                    href="{{ route('products.show', $product) }}" 
                                    class="p-2 text-green-600 hover:bg-green-50 rounded-lg transition"
                                    title="View details & history"
                                >
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a 
                                    href="{{ route('products.edit', $product) }}" 
                                    class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition"
                                    title="Edit"
                                >
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form action="{{ route('products.destroy', $product) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this product?');">
                                    @csrf
                                    @method('DELETE')
                                    <button 
                                        type="submit" 
                                        class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition"
                                        title="Delete"
                                    >
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center">
                            <div class="flex flex-col items-center">
                                <i class="fas fa-box-open text-6xl text-gray-300 mb-4"></i>
                                <p class="text-gray-500 text-lg mb-2">No products found</p>
                                <p class="text-gray-400 text-sm mb-4">Start by adding your first product</p>
                                <a 
                                    href="{{ route('products.create') }}" 
                                    class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
                                >
                                    <i class="fas fa-plus mr-2"></i>Add Product
                                </a>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($products->hasPages())
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $products->links() }}
        </div>
        @endif
    </div>

</div>
@endsection

{{-- Unit price toggle removed; prices shown inline --}}
