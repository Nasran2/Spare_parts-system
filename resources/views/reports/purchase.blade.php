@extends('layouts.app')

@section('title', 'Purchase Report')
@section('page-title', 'Purchase Report')

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
        @include('partials.quick-date-filter', [
            'fromName' => 'from',
            'toName' => 'to',
            'labelClass' => 'text-sm font-medium text-gray-600',
            'selectClass' => 'mt-1 border rounded px-3 py-2 text-sm w-48',
        ])
        <div>
            <label class="text-sm font-medium text-gray-600">To</label>
            <input type="date" name="to" value="{{ request('to', $to) }}" class="mt-1 border rounded px-3 py-2 text-sm w-48" />
        </div>
        <div>
            <label class="text-sm font-medium text-gray-600">Category</label>
            <select name="category_id" id="purchaseMainCategory" class="mt-1 border rounded px-3 py-2 text-sm w-56">
                <option value="">All Categories</option>
                @foreach($categories as $c)
                    <option value="{{ $c->id }}" @selected($categoryId == $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-sm font-medium text-gray-600">Sub Category</label>
            <select name="subcategory_id" id="purchaseSubCategory" class="mt-1 border rounded px-3 py-2 text-sm w-56" data-selected="{{ $subcategoryId ?? '' }}">
                <option value="">All Sub Categories</option>
            </select>
        </div>
        <div class="flex items-center gap-2">
            <button class="bg-blue-600 text-white px-4 py-2 rounded text-sm">Filter</button>
            <a href="{{ route('reports.purchase') }}" class="text-sm text-gray-600 hover:text-gray-800">Reset</a>
            <a href="{{ route('reports.purchase.csv', request()->all()) }}" target="_blank" rel="noopener" class="px-3 py-2 bg-emerald-600 text-white rounded text-sm"><i class="fas fa-file-excel mr-1"></i>Excel</a>
            <a href="{{ route('reports.purchase.pdf', request()->all()) }}" target="_blank" rel="noopener" class="px-3 py-2 bg-blue-600 text-white rounded text-sm"><i class="fas fa-file-pdf mr-1"></i>PDF</a>
        </div>
    </form>

    <script>
        async function purchaseFetchSubcategories(parentId) {
            const resp = await fetch(`{{ url('categories') }}/${parentId}/children`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await resp.json();
            return Array.isArray(data.children) ? data.children : [];
        }

        async function purchaseRefreshSubcategories() {
            const main = document.getElementById('purchaseMainCategory');
            const sub = document.getElementById('purchaseSubCategory');
            if (!main || !sub) return;

            const parentId = (main.value || '').trim();
            const selected = (sub.dataset.selected || '').trim();
            sub.innerHTML = '<option value="">All Sub Categories</option>';

            if (!parentId) {
                return;
            }

            let children = [];
            try {
                children = await purchaseFetchSubcategories(parentId);
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

        document.getElementById('purchaseMainCategory')?.addEventListener('change', () => {
            const sub = document.getElementById('purchaseSubCategory');
            if (sub) sub.dataset.selected = '';
            purchaseRefreshSubcategories();
        });

        purchaseRefreshSubcategories();
    </script>

    @if(empty($controls['hide_widgets']))
    <div class="grid md:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded shadow">
            <p class="text-xs text-gray-500">Total Purchases</p>
            <p class="text-xl font-semibold">{{ $maskMoney($summary['total_purchases'], !empty($controls['hide_total_purchase'])) }}</p>
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
            <p class="text-xs text-gray-500">Orders</p>
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
                        <td class="px-3 py-2 font-medium">{{ !empty($controls['hide_invoice_details']) ? 'HIDDEN' : $purchase->purchase_no }}</td>
                        <td class="px-3 py-2">{{ !empty($controls['hide_supplier_names']) ? 'Hidden' : ($purchase->supplier?->name ?? 'N/A') }}</td>
                        <td class="px-3 py-2">{{ $maskMoney($purchase->total_amount, !empty($controls['hide_total_purchase']) || !empty($controls['hide_invoice_details'])) }}</td>
                        <td class="px-3 py-2 text-green-600">{{ $maskMoney($purchase->paid_amount, !empty($controls['hide_supplier_payments']) || !empty($controls['hide_invoice_details'])) }}</td>
                        <td class="px-3 py-2 text-red-600">{{ $maskMoney($purchase->due_amount, !empty($controls['hide_supplier_payments']) || !empty($controls['hide_invoice_details'])) }}</td>
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
    @endif
</div>
@endsection
