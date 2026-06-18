@extends('layouts.app')

@section('title', 'Profit & Loss Report')
@section('page-title', 'Profit & Loss Report')

@section('content')
@php
    $controls = \App\Services\DashboardVisibilityService::configForUser(auth()->user());
    $priceVisiblePct = (float) ($controls['price_visible_percentage'] ?? 100);
    $profitVisiblePct = (float) ($controls['profit_visible_percentage'] ?? 100);
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
    $maskProfitMoney = function ($value, $forceHide = false) use ($controls, $profitVisiblePct, $applyPct) {
        if (\App\Services\PrivacyModeService::isActiveForUser(auth()->user()) && \App\Services\PrivacyModeService::shouldMaskForCurrentPage()) {
            return \App\Services\PrivacyModeService::maskAmount((float) $value);
        }
        if ($forceHide || !empty($controls['hide_profit_loss'])) {
            return '—';
        }

        $masked = $applyPct((float) $value, $profitVisiblePct);
        $roundToWhole = $profitVisiblePct < 100;

        return number_format($roundToWhole ? round($masked) : $masked, $roundToWhole ? 0 : 2);
    };
@endphp
<div class="space-y-6">
    <form method="get" class="bg-white p-4 rounded shadow flex flex-wrap gap-4 items-end">
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
            <p class="text-lg font-semibold">{{ $maskMoney($summary['sales_revenue'], !empty($controls['hide_total_sales'])) }}</p>
        </div>
        <div class="bg-white p-4 rounded shadow">
            <p class="text-xs text-gray-500">COGS</p>
            <p class="text-lg font-semibold text-red-600">{{ $maskMoney($summary['cogs'], !empty($controls['hide_actual_purchase_price']) || !empty($controls['hide_actual_stock_price'])) }}</p>
        </div>
        <div class="bg-white p-4 rounded shadow">
            <p class="text-xs text-gray-500">Gross Profit</p>
            <p class="text-lg font-semibold">{{ $maskProfitMoney($summary['gross_profit'], !empty($controls['hide_profit_loss'])) }}</p>
        </div>
        <div class="bg-white p-4 rounded shadow">
            <p class="text-xs text-gray-500">Expenses</p>
            <p class="text-lg font-semibold text-red-600">{{ $maskMoney($summary['expenses']) }}</p>
        </div>
        <div class="bg-white p-4 rounded shadow">
            <p class="text-xs text-gray-500">Net Profit</p>
            <p class="text-lg font-semibold {{ $summary['net_profit'] >= 0 ? 'text-green-600' : 'text-red-600' }}">{{ $maskProfitMoney($summary['net_profit'], !empty($controls['hide_profit_loss'])) }}</p>
        </div>
    </div>

    <div class="bg-white p-6 rounded shadow">
        <h4 class="font-semibold mb-4">Breakdown</h4>
        <div class="grid md:grid-cols-2 gap-6 text-sm">
            <div>
                <h5 class="font-medium mb-2">Revenue</h5>
                <p class="flex justify-between"><span>Total Sales</span><span>{{ $maskMoney($summary['sales_revenue'], !empty($controls['hide_total_sales'])) }}</span></p>
            </div>
            <div>
                <h5 class="font-medium mb-2">Costs</h5>
                <p class="flex justify-between"><span>COGS</span><span>{{ $maskMoney($summary['cogs'], !empty($controls['hide_actual_purchase_price']) || !empty($controls['hide_actual_stock_price'])) }}</span></p>
                <p class="flex justify-between"><span>Expenses</span><span>{{ $maskMoney($summary['expenses']) }}</span></p>
            </div>
            <div class="md:col-span-2 border-t pt-3">
                <p class="flex justify-between font-semibold"><span>Net Profit</span><span>{{ $maskProfitMoney($summary['net_profit'], !empty($controls['hide_profit_loss'])) }}</span></p>
            </div>
        </div>
    </div>
</div>
@endsection
