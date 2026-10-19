@if(request()->attributes->get('business_export'))
<table data-export-table data-export-title="Products"><thead><tr><th>Product</th><th>SKU</th><th>Category</th><th>Brand</th><th>Unit</th><th>Selling Price</th><th>Cost Price</th><th>Stock</th><th>Status</th></tr></thead><tbody>
@foreach($products as $product)
@php
    $activeStoreId = request('store_id') ?: ($stores->firstWhere('is_default', true)->id ?? null);
    $stock = $activeStoreId ? $product->storeStocks->where('store_id', $activeStoreId)->sum('quantity') : $product->stock_quantity;
@endphp
<tr><td>{{ $product->name }}</td><td>{{ $product->sku }}</td><td>{{ $product->categories->pluck('name')->join(', ') ?: 'Uncategorized' }}</td><td>{{ $product->brands->pluck('name')->join(', ') }}</td><td>{{ $product->unit?->short_name }}</td><td>{{ $maskMoney($product->selling_price, !empty($controls['hide_actual_stock_price'])) }}</td><td>{{ $maskMoney($product->cost_price, !empty($controls['hide_actual_purchase_price']) || !empty($controls['hide_actual_stock_price'])) }}</td><td>{{ $maskStockQty($stock) }}</td><td>{{ $product->is_active ? 'Active' : 'Inactive' }}</td></tr>
@endforeach
</tbody></table>
@endif
