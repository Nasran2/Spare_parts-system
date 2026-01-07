@extends('layouts.app')

@section('title', 'Stock Report')
@section('page-title', 'Stock Report')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div></div>
        <div class="flex items-center gap-2">
            <a href="{{ route('reports.stock.csv') }}" target="_blank" rel="noopener" class="px-3 py-2 bg-emerald-600 text-white rounded text-sm"><i class="fas fa-file-excel mr-1"></i>Excel</a>
            <a href="{{ route('reports.stock.pdf') }}" target="_blank" rel="noopener" class="px-3 py-2 bg-blue-600 text-white rounded text-sm"><i class="fas fa-file-pdf mr-1"></i>PDF</a>
        </div>
    </div>
    <div class="grid md:grid-cols-3 gap-4">
        <div class="bg-white p-4 rounded shadow">
            <p class="text-xs text-gray-500">Total Products</p>
            <p class="text-xl font-semibold">{{ $summary['total_products'] }}</p>
        </div>
        <div class="bg-white p-4 rounded shadow">
            <p class="text-xs text-gray-500">Low Stock</p>
            <p class="text-xl font-semibold text-red-600">{{ $summary['low_stock'] }}</p>
        </div>
        <div class="bg-white p-4 rounded shadow">
            <p class="text-xs text-gray-500">Total Units In Stock</p>
            <p class="text-xl font-semibold">{{ $summary['total_stock'] }}</p>
        </div>
    </div>

    <div class="bg-white rounded shadow overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50">
                <tr class="text-left">
                    <th class="px-3 py-2">Product</th>
                    <th class="px-3 py-2">Category</th>
                    <th class="px-3 py-2">Purchased Qty</th>
                    <th class="px-3 py-2">Sold Qty</th>
                    <th class="px-3 py-2">Current Stock</th>
                    <th class="px-3 py-2">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $row)
                    <tr class="border-t {{ $row['low_stock'] ? 'bg-red-50' : '' }}">
                        <td class="px-3 py-2 font-medium">{{ $row['product']->name }}</td>
                        <td class="px-3 py-2">{{ $row['product']->categories->pluck('name')->join(', ') }}</td>
                        <td class="px-3 py-2">{{ $row['purchased'] }}</td>
                        <td class="px-3 py-2">{{ $row['sold'] }}</td>
                        <td class="px-3 py-2">{{ $row['current_stock'] }}</td>
                        <td class="px-3 py-2">
                            <span class="px-2 py-1 rounded text-xs {{ $row['low_stock'] ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' }}">{{ $row['low_stock'] ? 'Low' : 'OK' }}</span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-3 py-6 text-center text-gray-500">No product data.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
