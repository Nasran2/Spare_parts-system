@extends('layouts.app')

@section('title', 'Trending Products')
@section('page-title', 'Trending Products')

@section('content')
@php
    $controls = is_array($controls ?? null) ? $controls : [];
    $priceVisiblePct = (float) ($controls['price_visible_percentage'] ?? 100);
    $qtyVisiblePct = (float) ($controls['qty_visible_percentage'] ?? 100);
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
        <div>
            <button class="bg-blue-600 text-white px-4 py-2 rounded text-sm">Filter</button>
            <a href="{{ route('reports.trending') }}" class="ml-2 text-sm text-gray-600 hover:text-gray-800">Reset</a>
        </div>
    </form>

    <div class="bg-white rounded shadow overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50">
                <tr class="text-left">
                    <th class="px-3 py-2">Product</th>
                    <th class="px-3 py-2">Quantity Sold</th>
                    <th class="px-3 py-2">Revenue</th>
                </tr>
            </thead>
            <tbody>
                @forelse($top as $row)
                    <tr class="border-t">
                        <td class="px-3 py-2 font-medium">{{ !empty($controls['hide_product_wise_data']) ? 'Hidden Product' : ($row['product']?->name ?? '-') }}</td>
                        <td class="px-3 py-2">{{ $maskQty($row['quantity']) }}</td>
                        <td class="px-3 py-2">{{ $maskMoney($row['revenue']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-3 py-6 text-center text-gray-500">No data available.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
