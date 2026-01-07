@extends('layouts.app')

@section('title', 'Purchase Report')
@section('page-title', 'Purchase Report')

@section('content')
<div class="space-y-6">
    <form method="get" class="bg-white p-4 rounded shadow flex flex-wrap gap-4 items-end">
        <div>
            <label class="text-sm font-medium text-gray-600">From</label>
            <input type="date" name="from" value="{{ request('from', $from) }}" class="mt-1 border rounded px-3 py-2 text-sm w-48" />
        </div>
        <div>
            <label class="text-sm font-medium text-gray-600">To</label>
            <input type="date" name="to" value="{{ request('to', $to) }}" class="mt-1 border rounded px-3 py-2 text-sm w-48" />
        </div>
        <div>
            <label class="text-sm font-medium text-gray-600">Category</label>
            <select name="category_id" class="mt-1 border rounded px-3 py-2 text-sm w-56">
                <option value="">All Categories</option>
                @foreach($categories as $c)
                    <option value="{{ $c->id }}" @selected($categoryId == $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-center gap-2">
            <button class="bg-blue-600 text-white px-4 py-2 rounded text-sm">Filter</button>
            <a href="{{ route('reports.purchase') }}" class="text-sm text-gray-600 hover:text-gray-800">Reset</a>
            <a href="{{ route('reports.purchase.csv', request()->all()) }}" target="_blank" rel="noopener" class="px-3 py-2 bg-emerald-600 text-white rounded text-sm"><i class="fas fa-file-excel mr-1"></i>Excel</a>
            <a href="{{ route('reports.purchase.pdf', request()->all()) }}" target="_blank" rel="noopener" class="px-3 py-2 bg-blue-600 text-white rounded text-sm"><i class="fas fa-file-pdf mr-1"></i>PDF</a>
        </div>
    </form>

    <div class="grid md:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded shadow">
            <p class="text-xs text-gray-500">Total Purchases</p>
            <p class="text-xl font-semibold">{{ number_format($summary['total_purchases'],2) }}</p>
        </div>
        <div class="bg-white p-4 rounded shadow">
            <p class="text-xs text-gray-500">Paid</p>
            <p class="text-xl font-semibold text-green-600">{{ number_format($summary['total_paid'],2) }}</p>
        </div>
        <div class="bg-white p-4 rounded shadow">
            <p class="text-xs text-gray-500">Due</p>
            <p class="text-xl font-semibold text-red-600">{{ number_format($summary['total_due'],2) }}</p>
        </div>
        <div class="bg-white p-4 rounded shadow">
            <p class="text-xs text-gray-500">Orders</p>
            <p class="text-xl font-semibold">{{ $summary['count'] }}</p>
        </div>
    </div>

    <div class="bg-white rounded shadow overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50">
                <tr class="text-left">
                    <th class="px-3 py-2">Date</th>
                    <th class="px-3 py-2">PO #</th>
                    <th class="px-3 py-2">Supplier</th>
                    <th class="px-3 py-2">Total</th>
                    <th class="px-3 py-2">Paid</th>
                    <th class="px-3 py-2">Due</th>
                    <th class="px-3 py-2">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($purchases as $purchase)
                    <tr class="border-t">
                        <td class="px-3 py-2">{{ $purchase->purchase_date?->toDateString() }}</td>
                        <td class="px-3 py-2 font-medium">{{ $purchase->purchase_no }}</td>
                        <td class="px-3 py-2">{{ $purchase->supplier?->name ?? 'N/A' }}</td>
                        <td class="px-3 py-2">{{ number_format($purchase->total_amount,2) }}</td>
                        <td class="px-3 py-2 text-green-600">{{ number_format($purchase->paid_amount,2) }}</td>
                        <td class="px-3 py-2 text-red-600">{{ number_format($purchase->due_amount,2) }}</td>
                        <td class="px-3 py-2">
                            <span class="px-2 py-1 rounded text-xs {{ $purchase->payment_status === 'paid' ? 'bg-green-100 text-green-700' : ($purchase->payment_status === 'partial' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700') }}">{{ ucfirst($purchase->payment_status) }}</span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-3 py-6 text-center text-gray-500">No purchases found for selected range.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
