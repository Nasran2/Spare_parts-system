@extends('layouts.app')

@section('title', 'Store Stock')
@section('page-title', 'Store Stock & Shipments')

@section('content')
<div class="space-y-6">
    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">{{ $errors->first() }}</div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 bg-white rounded-lg shadow overflow-hidden">
            <div class="p-5 border-b">
                <h3 class="font-semibold text-gray-800">Stores & POS Stock</h3>
                <p class="text-sm text-gray-500">Create stores, allocate shipment stock and transfer stock between stores.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Store</th>
                            <th class="px-4 py-3 text-left">Code</th>
                            <th class="px-4 py-3 text-left">Products</th>
                            <th class="px-4 py-3 text-right">Total Qty</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach($stores as $store)
                            <tr>
                                <td class="px-4 py-3 font-semibold">{{ $store->name }} @if($store->is_default)<span class="ml-2 text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded">Default</span>@endif</td>
                                <td class="px-4 py-3">{{ $store->code }}</td>
                                <td class="px-4 py-3">{{ $store->stocks->count() }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($store->stocks->sum('quantity'), 3) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow p-5">
            <h3 class="font-semibold text-gray-800 mb-4">Create Store</h3>
            <form method="POST" action="{{ route('stores.store') }}" class="space-y-3">
                @csrf
                <input name="name" placeholder="Store name" class="w-full border rounded-lg px-3 py-2" required>
                <input name="code" placeholder="Code" class="w-full border rounded-lg px-3 py-2" required>
                <input name="phone" placeholder="Phone" class="w-full border rounded-lg px-3 py-2">
                <input name="address" placeholder="Address" class="w-full border rounded-lg px-3 py-2">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_default" value="1"> Default POS store</label>
                <button class="w-full bg-blue-600 text-white rounded-lg px-4 py-2">Save Store</button>
            </form>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <div class="bg-white rounded-lg shadow p-5">
            <h3 class="font-semibold text-gray-800 mb-4">Good Receive Note / Shipment Cost</h3>
            <form method="POST" action="{{ route('stores.shipments.store') }}" class="space-y-3">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <input name="shipment_no" placeholder="Shipment no" class="border rounded-lg px-3 py-2" required>
                    <input name="grn_no" placeholder="GRN no" class="border rounded-lg px-3 py-2" required>
                    <select name="supplier_id" class="border rounded-lg px-3 py-2">
                        <option value="">Supplier</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                    <input type="date" name="shipment_date" value="{{ date('Y-m-d') }}" class="border rounded-lg px-3 py-2" required>
                    <input type="number" step="0.01" name="freight_cost" placeholder="Freight" class="border rounded-lg px-3 py-2">
                    <input type="number" step="0.01" name="duty_cost" placeholder="Duty" class="border rounded-lg px-3 py-2">
                    <input type="number" step="0.01" name="other_cost" placeholder="Other cost" class="border rounded-lg px-3 py-2">
                    <input name="notes" placeholder="Notes" class="border rounded-lg px-3 py-2">
                </div>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-3 bg-gray-50 p-3 rounded-lg">
                    <select name="items[0][product_id]" class="border rounded-lg px-3 py-2" required>
                        <option value="">Product</option>
                        @foreach($products as $product)
                            <option value="{{ $product->id }}">{{ $product->name }}</option>
                        @endforeach
                    </select>
                    <input type="number" step="0.001" name="items[0][quantity]" placeholder="Qty" class="border rounded-lg px-3 py-2" required>
                    <input type="number" step="0.01" name="items[0][unit_cost]" placeholder="Unit cost" class="border rounded-lg px-3 py-2" required>
                    <input type="number" step="0.01" name="items[0][selling_price]" placeholder="Selling" class="border rounded-lg px-3 py-2">
                </div>
                <button class="w-full bg-green-600 text-white rounded-lg px-4 py-2">Record GRN</button>
            </form>
        </div>

        <div class="bg-white rounded-lg shadow p-5">
            <h3 class="font-semibold text-gray-800 mb-4">Allocate Shipment Stock</h3>
            <form method="POST" action="{{ route('stores.shipments.allocate') }}" class="space-y-3 mb-6">
                @csrf
                <select name="stock_shipment_item_id" class="w-full border rounded-lg px-3 py-2" required>
                    <option value="">Shipment item</option>
                    @foreach($shipments as $shipment)
                        @foreach($shipment->items as $item)
                            <option value="{{ $item->id }}">{{ $shipment->grn_no }} - {{ $item->product->name }} ({{ number_format($item->quantity - $item->allocations->sum('quantity'), 3) }} left)</option>
                        @endforeach
                    @endforeach
                </select>
                <select name="store_id" class="w-full border rounded-lg px-3 py-2" required>
                    <option value="">Store</option>
                    @foreach($stores as $store)
                        <option value="{{ $store->id }}">{{ $store->name }}</option>
                    @endforeach
                </select>
                <input type="number" step="0.001" name="quantity" placeholder="Qty to allocate" class="w-full border rounded-lg px-3 py-2" required>
                <button class="w-full bg-blue-600 text-white rounded-lg px-4 py-2">Allocate</button>
            </form>

            <h3 class="font-semibold text-gray-800 mb-4">Transfer Between Stores</h3>
            <form method="POST" action="{{ route('stores.transfers.store') }}" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @csrf
                <select name="from_store_id" class="border rounded-lg px-3 py-2" required>
                    <option value="">From store</option>
                    @foreach($stores as $store)
                        <option value="{{ $store->id }}">{{ $store->name }}</option>
                    @endforeach
                </select>
                <select name="to_store_id" class="border rounded-lg px-3 py-2" required>
                    <option value="">To store</option>
                    @foreach($stores as $store)
                        <option value="{{ $store->id }}">{{ $store->name }}</option>
                    @endforeach
                </select>
                <select name="product_id" class="border rounded-lg px-3 py-2" required>
                    <option value="">Product</option>
                    @foreach($products as $product)
                        <option value="{{ $product->id }}">{{ $product->name }}</option>
                    @endforeach
                </select>
                <input type="number" step="0.001" name="quantity" placeholder="Qty" class="border rounded-lg px-3 py-2" required>
                <input type="date" name="transfer_date" value="{{ date('Y-m-d') }}" class="border rounded-lg px-3 py-2" required>
                <input name="reference_no" placeholder="Reference" class="border rounded-lg px-3 py-2">
                <button class="md:col-span-2 bg-gray-800 text-white rounded-lg px-4 py-2">Transfer Stock</button>
            </form>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="p-5 border-b">
            <h3 class="font-semibold text-gray-800">Shipment Stock Report</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3 text-left">GRN</th>
                        <th class="px-4 py-3 text-left">Supplier</th>
                        <th class="px-4 py-3 text-left">Product</th>
                        <th class="px-4 py-3 text-right">Qty</th>
                        <th class="px-4 py-3 text-right">Landed Cost</th>
                        <th class="px-4 py-3 text-right">Allocated</th>
                        <th class="px-4 py-3 text-left">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach($shipments as $shipment)
                        @foreach($shipment->items as $item)
                            <tr>
                                <td class="px-4 py-3">{{ $shipment->grn_no }}</td>
                                <td class="px-4 py-3">{{ $shipment->supplier->name ?? 'Manual' }}</td>
                                <td class="px-4 py-3">{{ $item->product->name }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($item->quantity, 3) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($item->landed_unit_cost, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($item->allocations->sum('quantity'), 3) }}</td>
                                <td class="px-4 py-3">{{ ucfirst($shipment->status) }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
