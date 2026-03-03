@extends('layouts.app')

@section('title', 'Profit & Loss Report')
@section('page-title', 'Profit & Loss Report')

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
        <div class="flex items-center gap-2">
            <button class="bg-blue-600 text-white px-4 py-2 rounded text-sm">Filter</button>
            <a href="{{ route('reports.profit-loss') }}" class="text-sm text-gray-600 hover:text-gray-800">Reset</a>
            <a href="{{ route('reports.profit-loss.csv', request()->all()) }}" target="_blank" rel="noopener" class="px-3 py-2 bg-emerald-600 text-white rounded text-sm"><i class="fas fa-file-excel mr-1"></i>Excel</a>
            <a href="{{ route('reports.profit-loss.pdf', request()->all()) }}" target="_blank" rel="noopener" class="px-3 py-2 bg-blue-600 text-white rounded text-sm"><i class="fas fa-file-pdf mr-1"></i>PDF</a>
        </div>
    </form>

    <div class="grid md:grid-cols-5 gap-4">
        <div class="bg-white p-4 rounded shadow">
            <p class="text-xs text-gray-500">Sales Revenue</p>
            <p class="text-lg font-semibold">{{ number_format($summary['sales_revenue'],2) }}</p>
        </div>
        <div class="bg-white p-4 rounded shadow">
            <p class="text-xs text-gray-500">COGS</p>
            <p class="text-lg font-semibold text-red-600">{{ number_format($summary['cogs'],2) }}</p>
        </div>
        <div class="bg-white p-4 rounded shadow">
            <p class="text-xs text-gray-500">Gross Profit</p>
            <p class="text-lg font-semibold">{{ number_format($summary['gross_profit'],2) }}</p>
        </div>
        <div class="bg-white p-4 rounded shadow">
            <p class="text-xs text-gray-500">Expenses</p>
            <p class="text-lg font-semibold text-red-600">{{ number_format($summary['expenses'],2) }}</p>
        </div>
        <div class="bg-white p-4 rounded shadow">
            <p class="text-xs text-gray-500">Net Profit</p>
            <p class="text-lg font-semibold {{ $summary['net_profit'] >= 0 ? 'text-green-600' : 'text-red-600' }}">{{ number_format($summary['net_profit'],2) }}</p>
        </div>
    </div>

    <div class="bg-white p-6 rounded shadow">
        <h4 class="font-semibold mb-4">Breakdown</h4>
        <div class="grid md:grid-cols-2 gap-6 text-sm">
            <div>
                <h5 class="font-medium mb-2">Revenue</h5>
                <p class="flex justify-between"><span>Total Sales</span><span>{{ number_format($summary['sales_revenue'],2) }}</span></p>
            </div>
            <div>
                <h5 class="font-medium mb-2">Costs</h5>
                <p class="flex justify-between"><span>COGS</span><span>{{ number_format($summary['cogs'],2) }}</span></p>
                <p class="flex justify-between"><span>Expenses</span><span>{{ number_format($summary['expenses'],2) }}</span></p>
            </div>
            <div class="md:col-span-2 border-t pt-3">
                <p class="flex justify-between font-semibold"><span>Net Profit</span><span>{{ number_format($summary['net_profit'],2) }}</span></p>
            </div>
        </div>
    </div>
</div>
@endsection
