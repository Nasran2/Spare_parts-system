@extends('layouts.app')

@section('title', 'Receive Report')
@section('page-title', 'Receive Report')

@section('content')
@php
    $controls = \App\Services\DashboardVisibilityService::configForUser(auth()->user());
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
    
    <form method="get" class="bg-white p-4 rounded shadow flex flex-wrap gap-4 items-end mb-6">
        <div>
            <label class="text-sm font-medium text-gray-600">From</label>
            <input type="date" name="from" value="{{ request('from') }}" class="mt-1 border rounded px-3 py-2 text-sm w-48" />
        </div>
        <div>
            <label class="text-sm font-medium text-gray-600">To</label>
            <input type="date" name="to" value="{{ request('to') }}" class="mt-1 border rounded px-3 py-2 text-sm w-48" />
        </div>
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
        <div class="flex items-center gap-2">
            <button class="bg-blue-600 text-white px-4 py-2 rounded text-sm">Filter</button>
            <a href="{{ route('reports.receive') }}" class="text-sm text-gray-600 hover:text-gray-800">Reset</a>
        </div>
    </form>
<div class="bg-white rounded-xl shadow-md p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-bold text-gray-800"><i class="fas fa-arrow-down text-green-600 mr-2"></i>Payments Received</h3>
            <div class="flex items-center gap-2">
                <a href="{{ route('reports.receive.csv', request()->all()) }}" target="_blank" rel="noopener" class="px-3 py-2 bg-emerald-600 text-white rounded-lg"><i class="fas fa-file-excel mr-1"></i>Excel</a>
                <a href="{{ route('reports.receive.pdf', request()->all()) }}" target="_blank" rel="noopener" class="px-3 py-2 bg-blue-600 text-white rounded-lg"><i class="fas fa-file-pdf mr-1"></i>PDF</a>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="p-4 bg-blue-50 rounded-lg">
                <div class="text-sm text-gray-600">Total Received</div>
                <div class="text-lg font-bold text-gray-800">{{ $maskMoney($summary['total_received'], !empty($controls['hide_supplier_payments']) || !empty($controls['hide_invoice_details'])) }}</div>
            </div>
            <div class="p-4 bg-blue-50 rounded-lg">
                <div class="text-sm text-gray-600">Transactions</div>
                <div class="text-lg font-bold text-gray-800">{{ $summary['count'] }}</div>
            </div>
            <div class="p-4 bg-blue-50 rounded-lg">
                <div class="text-sm font-semibold text-gray-700">By Method</div>
                <ul class="text-sm text-gray-700 mt-1">
                    @foreach($summary['by_method'] as $m => $amt)
                        <li>{{ $m ?? 'Unknown' }} — {{ $maskMoney($amt, !empty($controls['hide_supplier_payments']) || !empty($controls['hide_invoice_details'])) }}</li>
                    @endforeach
                </ul>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full border rounded-lg">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-sm text-gray-600">Date</th>
                        <th class="px-4 py-2 text-left text-sm text-gray-600">Customer</th>
                        <th class="px-4 py-2 text-left text-sm text-gray-600">Sale</th>
                        <th class="px-4 py-2 text-left text-sm text-gray-600">Method</th>
                        <th class="px-4 py-2 text-left text-sm text-gray-600">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payments as $p)
                        <tr class="border-t">
                            <td class="px-4 py-2">{{ optional($p->payment_date)->toDateString() }}</td>
                            <td class="px-4 py-2">{{ $p->customer?->name ?? '-' }}</td>
                            <td class="px-4 py-2">{{ $p->sale?->invoice_no ?? '-' }}</td>
                            <td class="px-4 py-2">{{ $p->payment_method ?? '-' }}</td>
                            <td class="px-4 py-2">{{ $maskMoney($p->amount, !empty($controls['hide_supplier_payments']) || !empty($controls['hide_invoice_details'])) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-4 text-center text-gray-500">No payments found</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
