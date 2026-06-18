@extends('layouts.app')

@section('title', 'VAT Report')
@section('page-title', 'VAT Report')

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
    <div class="bg-white rounded-xl shadow-md p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-bold text-gray-800"><i class="fas fa-receipt text-green-600 mr-2"></i>VAT Report</h3>
            <form method="GET" action="{{ route('reports.vat') }}" class="flex items-center gap-2">
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

                <input type="date" name="from" value="{{ request('from') }}" class="px-3 py-2 border rounded-lg">
                <input type="date" name="to" value="{{ request('to') }}" class="px-3 py-2 border rounded-lg">
                @include('partials.quick-date-filter', [
                    'fromName' => 'from',
                    'toName' => 'to',
                    'inline' => true,
                    'selectClass' => 'px-3 py-2 border rounded-lg',
                ])
                <button class="px-3 py-2 bg-gray-100 rounded-lg">Filter</button>
                <a href="{{ route('reports.vat') }}" class="px-3 py-2 text-sm text-gray-600">Reset</a>
                <a href="{{ route('reports.vat.csv', request()->all()) }}" target="_blank" rel="noopener" class="px-3 py-2 bg-emerald-600 text-white rounded-lg"><i class="fas fa-file-excel mr-1"></i>Excel</a>
                <a href="{{ route('reports.vat.pdf', request()->all()) }}" target="_blank" rel="noopener" class="px-3 py-2 bg-blue-600 text-white rounded-lg"><i class="fas fa-file-pdf mr-1"></i>PDF</a>
            </form>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="p-4 bg-green-50 rounded-lg">
                <div class="text-sm text-gray-600">VAT Enabled</div>
                <div class="text-lg font-bold text-gray-800">{{ $summary['enabled'] ? 'Yes' : 'No' }}</div>
            </div>
            <div class="p-4 bg-green-50 rounded-lg">
                <div class="text-sm text-gray-600">VAT Rate</div>
                <div class="text-lg font-bold text-gray-800">{{ number_format($summary['rate'], 2) }}%</div>
            </div>
            <div class="p-4 bg-blue-50 rounded-lg">
                <div class="text-sm text-gray-600">Total Sales</div>
                <div class="text-lg font-bold text-gray-800">{{ $maskMoney($summary['total_final'], !empty($controls['hide_total_sales'])) }}</div>
            </div>
            <div class="p-4 bg-purple-50 rounded-lg">
                <div class="text-sm text-gray-600">VAT (Exclusive)</div>
                <div class="text-lg font-bold text-gray-800">{{ $maskMoney($summary['vat_exclusive']) }}</div>
                <div class="text-xs text-gray-500">If selling prices exclude VAT</div>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="p-4 bg-purple-50 rounded-lg">
                <div class="text-sm text-gray-600">VAT (Inclusive)</div>
                <div class="text-lg font-bold text-gray-800">{{ $maskMoney($summary['vat_inclusive']) }}</div>
                <div class="text-xs text-gray-500">If selling prices include VAT</div>
            </div>
        </div>

        <h4 class="font-bold text-gray-700 mb-2">Daily Breakdown</h4>
        <div class="overflow-x-auto">
            <table class="min-w-full border rounded-lg">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-sm text-gray-600">Date</th>
                        <th class="px-4 py-2 text-left text-sm text-gray-600">Sales Total</th>
                        <th class="px-4 py-2 text-left text-sm text-gray-600">VAT (Exclusive)</th>
                        <th class="px-4 py-2 text-left text-sm text-gray-600">VAT (Inclusive)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($daily as $d)
                        <tr class="border-t hover:bg-gray-50 cursor-pointer" onclick="openVatDay('{{ $d['date'] }}')">
                            <td class="px-4 py-2">{{ $d['date'] }}</td>
                            <td class="px-4 py-2">{{ $maskMoney($d['final_total'], !empty($controls['hide_total_sales'])) }}</td>
                            <td class="px-4 py-2">{{ $maskMoney($d['vat_exclusive']) }}</td>
                            <td class="px-4 py-2">{{ $maskMoney($d['vat_inclusive']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-4 text-center text-gray-500">No data in selected range</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- VAT Day Modal -->
<div id="vatDayModal" class="hidden fixed inset-0 bg-black bg-opacity-40 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-3xl w-full">
        <div class="flex items-center justify-between px-4 py-3 border-b">
            <h4 id="vatDayTitle" class="text-lg font-bold">VAT Details</h4>
            <div class="flex gap-2">
                <a id="vatDayPdf" href="#" target="_blank" rel="noopener" class="px-3 py-2 bg-blue-600 text-white rounded">PDF</a>
                <button onclick="closeVatDay()" class="px-3 py-2 bg-gray-100 rounded">Close</button>
            </div>
        </div>
        <div class="p-4">
            <div id="vatDaySummary" class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-3"></div>
            <div class="overflow-x-auto">
                <table class="min-w-full border rounded">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-sm">Invoice</th>
                            <th class="px-3 py-2 text-left text-sm">Product</th>
                            <th class="px-3 py-2 text-left text-sm">Qty</th>
                            <th class="px-3 py-2 text-left text-sm">Unit Price</th>
                            <th class="px-3 py-2 text-left text-sm">Line Total</th>
                            <th class="px-3 py-2 text-left text-sm">VAT (Ex.)</th>
                            <th class="px-3 py-2 text-left text-sm">VAT (Inc.)</th>
                        </tr>
                    </thead>
                    <tbody id="vatDayBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function formatMoneyValue(value, fallbackNumeric) {
    if (typeof value === 'string' && value.trim() !== '') {
        return value;
    }

    const numeric = Number(fallbackNumeric ?? value);
    return Number.isFinite(numeric) ? numeric.toFixed(2) : '—';
}

async function openVatDay(date) {
    try {
        const res = await fetch(`{{ route('reports.vat.day') }}?date=${date}`);
        const data = await res.json();
        document.getElementById('vatDayTitle').textContent = `VAT Details — ${data.date}`;
        document.getElementById('vatDayPdf').href = `{{ route('reports.vat.day.pdf') }}?date=${date}`;
        const s = data.totals || { line_total:0, vat_exclusive:0, vat_inclusive:0 };
        document.getElementById('vatDaySummary').innerHTML = `
            <div class='p-3 bg-blue-50 rounded'><div class='text-sm text-gray-600'>Sales Total</div><div class='text-lg font-bold'>${formatMoneyValue(s.line_total_display, s.line_total)}</div></div>
            <div class='p-3 bg-purple-50 rounded'><div class='text-sm text-gray-600'>VAT (Exclusive)</div><div class='text-lg font-bold'>${formatMoneyValue(s.vat_exclusive_display, s.vat_exclusive)}</div></div>
            <div class='p-3 bg-purple-50 rounded'><div class='text-sm text-gray-600'>VAT (Inclusive)</div><div class='text-lg font-bold'>${formatMoneyValue(s.vat_inclusive_display, s.vat_inclusive)}</div></div>`;
        const body = document.getElementById('vatDayBody');
        body.innerHTML = '';
        (data.items || []).forEach(i => {
            body.insertAdjacentHTML('beforeend', `<tr class='border-t'>
                <td class='px-3 py-2'>${i.invoice ?? '-'}</td>
                <td class='px-3 py-2'>${i.product}</td>
                <td class='px-3 py-2'>${i.quantity_display ?? i.quantity ?? '—'}</td>
                <td class='px-3 py-2'>${formatMoneyValue(i.unit_price_display, i.unit_price)}</td>
                <td class='px-3 py-2'>${formatMoneyValue(i.line_total_display, i.line_total)}</td>
                <td class='px-3 py-2'>${formatMoneyValue(i.vat_exclusive_display, i.vat_exclusive)}</td>
                <td class='px-3 py-2'>${formatMoneyValue(i.vat_inclusive_display, i.vat_inclusive)}</td>
            </tr>`);
        });
        document.getElementById('vatDayModal').classList.remove('hidden');
    } catch (e) { console.error(e); }
}
function closeVatDay() { document.getElementById('vatDayModal').classList.add('hidden'); }
</script>
@endsection
