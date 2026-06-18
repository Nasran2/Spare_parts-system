@extends('layouts.app')

@section('title', $title)
@section('page-title', $title)

@section('content')
@php
    $controls = is_array($controls ?? null) ? $controls : [];
    $priceVisiblePct = (float) ($controls['price_visible_percentage'] ?? 100);
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
@endphp

<div class="space-y-6">
    @if($type === 'never')
    <div class="bg-white p-4 rounded shadow">
        <form method="GET" action="{{ route('reports.never-sold') }}" class="flex items-end gap-3">
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

            <div class="w-64">
                <label class="block text-sm font-medium text-gray-700 mb-1">Supplier Filter</label>
                <select name="supplier_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    <option value="">All Suppliers</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" {{ request('supplier_id') == $supplier->id ? 'selected' : '' }}>{{ $supplier->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm">Filter</button>
                <a href="{{ route('reports.never-sold') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm">Reset</a>
            </div>
        </form>
    </div>
    @endif

    @if($type === 'recently')
    <div class="bg-white p-4 rounded shadow">
        <form method="GET" action="{{ route('reports.unsold-recently') }}" class="grid grid-cols-1 md:grid-cols-6 gap-3 items-end">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Time Period</label>
                <select name="period" id="periodSelect" class="w-full px-3 py-2 border border-gray-300 rounded-lg" onchange="toggleCustomDates()">
                    <option value="1_week" {{ $period === '1_week' ? 'selected' : '' }}>Last 1 Week</option>
                    <option value="1_month" {{ $period === '1_month' ? 'selected' : '' }}>Last 1 Month</option>
                    <option value="2_months" {{ $period === '2_months' ? 'selected' : '' }}>Last 2 Months</option>
                    <option value="3_months" {{ $period === '3_months' ? 'selected' : '' }}>Last 3 Months</option>
                    <option value="6_months" {{ $period === '6_months' ? 'selected' : '' }}>Last 6 Months</option>
                    <option value="9_months" {{ $period === '9_months' ? 'selected' : '' }}>Last 9 Months</option>
                    <option value="1_year" {{ $period === '1_year' ? 'selected' : '' }}>Last 1 Year</option>
                    <option value="custom" {{ $period === 'custom' ? 'selected' : '' }}>Custom Date Range</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Supplier Filter</label>
                <select name="supplier_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    <option value="">All Suppliers</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" {{ request('supplier_id') == $supplier->id ? 'selected' : '' }}>{{ $supplier->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="custom-date-group {{ $period === 'custom' ? '' : 'hidden' }}">
                <label class="block text-sm font-medium text-gray-700 mb-1">From</label>
                <input type="date" name="custom_from" value="{{ request('custom_from') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
            </div>
            <div class="custom-date-group {{ $period === 'custom' ? '' : 'hidden' }}">
                <label class="block text-sm font-medium text-gray-700 mb-1">To</label>
                <input type="date" name="custom_to" value="{{ request('custom_to') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
            </div>
            <div class="flex gap-2 md:col-span-2">
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm">Filter</button>
                <a href="{{ route('reports.unsold-recently') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm">Reset</a>
            </div>
        </form>
    </div>
    
    <script>
        function toggleCustomDates() {
            const period = document.getElementById('periodSelect').value;
            const customGroups = document.querySelectorAll('.custom-date-group');
            customGroups.forEach(group => {
                if (period === 'custom') {
                    group.classList.remove('hidden');
                } else {
                    group.classList.add('hidden');
                }
            });
        }
    </script>
    @endif

    @if(empty($controls['hide_tables']))
    <div class="bg-white rounded shadow overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50">
                <tr class="text-left">
                    <th class="px-3 py-2">Product</th>
                    <th class="px-3 py-2">SKU</th>
                    <th class="px-3 py-2">Category</th>
                    <th class="px-3 py-2">Brand</th>
                    <th class="px-3 py-2">Current Stock</th>
                    <th class="px-3 py-2">Cost Price</th>
                    <th class="px-3 py-2">Selling Price</th>
                </tr>
            </thead>
            <tbody>
                @forelse($products as $product)
                    <tr class="border-t">
                        <td class="px-3 py-2 font-medium">{{ !empty($controls['hide_product_wise_data']) ? 'Hidden Product' : $product->name }}</td>
                        <td class="px-3 py-2">{{ $product->sku }}</td>
                        <td class="px-3 py-2">{{ $product->categories->pluck('name')->join(', ') ?: ($product->category->name ?? '-') }}</td>
                        <td class="px-3 py-2">{{ $product->brands->pluck('name')->join(', ') ?: ($product->brand->name ?? '-') }}</td>
                        <td class="px-3 py-2">{{ $product->stock_quantity ?? 0 }}</td>
                        <td class="px-3 py-2">{{ $maskMoney($product->cost_price, !empty($controls['hide_actual_purchase_price'])) }}</td>
                        <td class="px-3 py-2">{{ $maskMoney($product->selling_price, !empty($controls['hide_actual_stock_price'])) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-3 py-6 text-center text-gray-500">No unsold products found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($products->hasPages())
    <div class="flex flex-col sm:flex-row items-center justify-between gap-3 bg-white px-4 py-3 rounded shadow text-sm mt-4">
        <div class="text-gray-500">
            Showing {{ $products->firstItem() }}–{{ $products->lastItem() }} of {{ $products->total() }} products
        </div>
        <div>
            {{ $products->links() }}
        </div>
    </div>
    @endif
    @endif
</div>
@endsection
