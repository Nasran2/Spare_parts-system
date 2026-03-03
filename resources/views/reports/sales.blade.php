@extends('layouts.app')

@section('title', 'Sales Report')
@section('page-title', 'Sales Report')

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
        @include('partials.quick-date-filter', [
            'fromName' => 'from',
            'toName' => 'to',
            'labelClass' => 'text-sm font-medium text-gray-600',
            'selectClass' => 'mt-1 border rounded px-3 py-2 text-sm w-48',
        ])
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
            <a href="{{ route('reports.sales') }}" class="text-sm text-gray-600 hover:text-gray-800">Reset</a>
            <a href="{{ route('reports.sales.csv', request()->all()) }}" target="_blank" rel="noopener" class="px-3 py-2 bg-emerald-600 text-white rounded text-sm"><i class="fas fa-file-excel mr-1"></i>Excel</a>
            <a href="{{ route('reports.sales.pdf', request()->all()) }}" target="_blank" rel="noopener" class="px-3 py-2 bg-blue-600 text-white rounded text-sm"><i class="fas fa-file-pdf mr-1"></i>PDF</a>
        </div>
    </form>

    <div class="grid md:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded shadow">
            <p class="text-xs text-gray-500">Total Sales</p>
            <p class="text-xl font-semibold">{{ number_format($summary['total_sales'],2) }}</p>
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
            <p class="text-xs text-gray-500">Invoices</p>
            <p class="text-xl font-semibold">{{ $summary['count'] }}</p>
        </div>
    </div>

    <div class="bg-white rounded shadow overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50">
                <tr class="text-left">
                    <th class="px-3 py-2">Date</th>
                    <th class="px-3 py-2">Invoice</th>
                    <th class="px-3 py-2">Customer</th>
                    <th class="px-3 py-2">Total</th>
                    <th class="px-3 py-2">Paid</th>
                    <th class="px-3 py-2">Due</th>
                    <th class="px-3 py-2">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($sales as $sale)
                    <tr class="border-t">
                        <td class="px-3 py-2">{{ $sale->sale_date?->toDateString() }}</td>
                        <td class="px-3 py-2 font-medium">{{ $sale->sale_no }}</td>
                        <td class="px-3 py-2">{{ $sale->customer?->name ?? 'Walk-in' }}</td>
                        <td class="px-3 py-2">{{ \App\Support\SecretPos::maskForSale($sale->total_amount, $sale->total_amount) }}</td>
                        <td class="px-3 py-2 text-green-600">{{ \App\Support\SecretPos::maskForSale($sale->total_amount, $sale->paid_amount) }}</td>
                        <td class="px-3 py-2 text-red-600">{{ \App\Support\SecretPos::maskForSale($sale->total_amount, $sale->due_amount) }}</td>
                        <td class="px-3 py-2">
                            <span class="px-2 py-1 rounded text-xs {{ $sale->payment_status === 'paid' ? 'bg-green-100 text-green-700' : ($sale->payment_status === 'partial' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700') }}">{{ ucfirst($sale->payment_status) }}</span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-3 py-6 text-center text-gray-500">No sales found for selected range.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="bg-white rounded shadow p-4">
        <h4 class="font-semibold mb-2 text-sm">Daily Summary</h4>
        <div class="overflow-x-auto">
            <table class="min-w-full text-xs">
                <thead class="bg-gray-50">
                    <tr class="text-left">
                        <th class="px-2 py-1">Date</th>
                        <th class="px-2 py-1">Invoices</th>
                        <th class="px-2 py-1">Total</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($daily as $d)
                    <tr class="border-t">
                        <td class="px-2 py-1">{{ $d['date'] }}</td>
                        <td class="px-2 py-1">{{ $d['count'] }}</td>
                        <td class="px-2 py-1">{{ number_format($d['total'],2) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
