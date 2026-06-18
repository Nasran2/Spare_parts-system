@extends('layouts.app')

@section('title', 'Sales Report')
@section('page-title', 'Sales Report')

@section('content')
@php
    $controls = is_array($controls ?? null) ? $controls : [];
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
            <label class="text-sm font-medium text-gray-600">Category</label>
            <select name="category_id" id="salesMainCategory" class="mt-1 border rounded px-3 py-2 text-sm w-56">
                <option value="">All Categories</option>
                @foreach($categories as $c)
                    <option value="{{ $c->id }}" @selected($categoryId == $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-sm font-medium text-gray-600">Sub Category</label>
            <select name="subcategory_id" id="salesSubCategory" class="mt-1 border rounded px-3 py-2 text-sm w-56" data-selected="{{ $subcategoryId ?? '' }}">
                <option value="">All Sub Categories</option>
            </select>
        </div>
        <div class="flex items-center gap-2">
            <button class="bg-blue-600 text-white px-4 py-2 rounded text-sm">Filter</button>
            <a href="{{ route('reports.sales') }}" class="text-sm text-gray-600 hover:text-gray-800">Reset</a>
            <a href="{{ route('reports.sales.csv', request()->all()) }}" target="_blank" rel="noopener" class="px-3 py-2 bg-emerald-600 text-white rounded text-sm"><i class="fas fa-file-excel mr-1"></i>Excel</a>
            <a href="{{ route('reports.sales.pdf', request()->all()) }}" target="_blank" rel="noopener" class="px-3 py-2 bg-blue-600 text-white rounded text-sm"><i class="fas fa-file-pdf mr-1"></i>PDF</a>
        </div>
    </form>

    <script>
        async function salesFetchSubcategories(parentId) {
            const resp = await fetch(`{{ url('categories') }}/${parentId}/children`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await resp.json();
            return Array.isArray(data.children) ? data.children : [];
        }

        async function salesRefreshSubcategories() {
            const main = document.getElementById('salesMainCategory');
            const sub = document.getElementById('salesSubCategory');
            if (!main || !sub) return;

            const parentId = (main.value || '').trim();
            const selected = (sub.dataset.selected || '').trim();
            sub.innerHTML = '<option value="">All Sub Categories</option>';

            if (!parentId) {
                return;
            }

            let children = [];
            try {
                children = await salesFetchSubcategories(parentId);
            } catch (e) {
                children = [];
            }

            children.forEach(child => {
                sub.add(new Option(child.name, child.id, false, false));
            });

            if (selected) {
                sub.value = String(selected);
            }
        }

        document.getElementById('salesMainCategory')?.addEventListener('change', () => {
            const sub = document.getElementById('salesSubCategory');
            if (sub) sub.dataset.selected = '';
            salesRefreshSubcategories();
        });

        salesRefreshSubcategories();
    </script>

    @if(empty($controls['hide_widgets']))
    <div class="grid md:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded shadow">
            <p class="text-xs text-gray-500">Total Sales</p>
            <p class="text-xl font-semibold">{{ $maskMoney($summary['total_sales'], !empty($controls['hide_total_sales'])) }}</p>
        </div>
        <div class="bg-white p-4 rounded shadow">
            <p class="text-xs text-gray-500">Paid</p>
            <p class="text-xl font-semibold text-green-600">{{ $maskMoney($summary['total_paid'], !empty($controls['hide_supplier_payments']) || !empty($controls['hide_invoice_details'])) }}</p>
        </div>
        <div class="bg-white p-4 rounded shadow">
            <p class="text-xs text-gray-500">Due</p>
            <p class="text-xl font-semibold text-red-600">{{ $maskMoney($summary['total_due'], !empty($controls['hide_supplier_payments']) || !empty($controls['hide_invoice_details'])) }}</p>
        </div>
        <div class="bg-white p-4 rounded shadow">
            <p class="text-xs text-gray-500">Invoices</p>
            <p class="text-xl font-semibold">{{ !empty($controls['hide_invoice_details']) ? '—' : $summary['count'] }}</p>
        </div>
    </div>
    @endif

    @if(empty($controls['hide_tables']))
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
                        <td class="px-3 py-2 font-medium">{{ !empty($controls['hide_invoice_details']) ? 'HIDDEN' : \App\Services\PrivacyModeService::displayInvoiceNumber($sale) }}</td>
                        <td class="px-3 py-2">{{ !empty($controls['hide_supplier_names']) ? 'Hidden' : ($sale->customer?->name ?? 'Walk-in') }}</td>
                        <td class="px-3 py-2">{{ $maskMoney($sale->total_amount, !empty($controls['hide_invoice_details']) || !empty($controls['hide_total_sales'])) }}</td>
                        <td class="px-3 py-2 text-green-600">{{ $maskMoney($sale->paid_amount, !empty($controls['hide_supplier_payments']) || !empty($controls['hide_invoice_details'])) }}</td>
                        <td class="px-3 py-2 text-red-600">{{ $maskMoney($sale->due_amount, !empty($controls['hide_supplier_payments']) || !empty($controls['hide_invoice_details'])) }}</td>
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
                        <td class="px-2 py-1">{{ !empty($controls['hide_invoice_details']) ? '—' : $d['count'] }}</td>
                        <td class="px-2 py-1">{{ $maskMoney($d['total'], !empty($controls['hide_total_sales'])) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
@endsection
