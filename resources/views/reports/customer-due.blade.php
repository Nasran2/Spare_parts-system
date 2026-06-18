@extends('layouts.app')

@section('title', 'Customer Due Report')
@section('page-title', 'Customer Due Report')

@section('content')
@php
    $controls = \App\Services\DashboardVisibilityService::configForUser(auth()->user());
    $priceVisiblePct = (float) ($controls['customer_visible_percentage'] ?? 100);
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
    <div class="bg-white rounded-xl shadow-md p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-bold text-gray-800"><i class="fas fa-user-clock text-red-600 mr-2"></i>Customers with Outstanding Dues</h3>
            <form method="GET" action="{{ route('reports.customer-due') }}" class="flex items-center gap-2">
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

                <input type="date" name="from" value="{{ $from }}" class="px-3 py-2 border rounded-lg">
                <input type="date" name="to" value="{{ $to }}" class="px-3 py-2 border rounded-lg">
                @include('partials.quick-date-filter', [
                    'fromName' => 'from',
                    'toName' => 'to',
                    'inline' => true,
                    'selectClass' => 'px-3 py-2 border rounded-lg',
                ])
                <button class="px-3 py-2 bg-gray-100 rounded-lg">Filter</button>
                <a href="{{ route('reports.customer-due') }}" class="px-3 py-2 text-sm text-gray-600">Reset</a>
                <a href="{{ route('reports.customer-due.csv', request()->all()) }}" target="_blank" rel="noopener" class="px-3 py-2 bg-emerald-600 text-white rounded-lg"><i class="fas fa-file-excel mr-1"></i>Excel</a>
                <a href="{{ route('reports.customer-due.pdf', request()->all()) }}" target="_blank" rel="noopener" class="px-3 py-2 bg-blue-600 text-white rounded-lg"><i class="fas fa-file-pdf mr-1"></i>PDF</a>
            </form>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="p-4 bg-red-50 rounded-lg">
                <div class="text-sm text-gray-600">Total Due</div>
                <div class="text-lg font-bold text-gray-800">{{ $maskMoney($summary['total_due'], !empty($controls['hide_supplier_payments']) || !empty($controls['hide_invoice_details'])) }}</div>
            </div>
            <div class="p-4 bg-red-50 rounded-lg">
                <div class="text-sm text-gray-600">Customers</div>
                <div class="text-lg font-bold text-gray-800">{{ $summary['customers'] }}</div>
            </div>
            <div class="p-4 bg-red-50 rounded-lg">
                <div class="text-sm text-gray-600">Date Range</div>
                <div class="text-sm text-gray-800">{{ $from ?? '—' }} to {{ $to ?? '—' }}</div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full border rounded-lg">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-sm text-gray-600">Customer</th>
                        <th class="px-4 py-2 text-left text-sm text-gray-600">Phone</th>
                        <th class="px-4 py-2 text-left text-sm text-gray-600">Invoices</th>
                        <th class="px-4 py-2 text-left text-sm text-gray-600">Due Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $row)
                        <tr class="border-t">
                            <td class="px-4 py-2">{{ $row['customer']->name }}</td>
                            <td class="px-4 py-2">{{ $row['customer']->phone ?? '-' }}</td>
                            <td class="px-4 py-2">{{ $row['invoices'] }}</td>
                            <td class="px-4 py-2 font-semibold text-red-700">{{ $maskMoney($row['due'], !empty($controls['hide_supplier_payments']) || !empty($controls['hide_invoice_details'])) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-4 text-center text-gray-500">No customer dues found</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
