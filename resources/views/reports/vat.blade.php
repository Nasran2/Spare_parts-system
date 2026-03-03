@extends('layouts.app')

@section('title', 'VAT Report')
@section('page-title', 'VAT Report')

@section('content')
<div class="space-y-6">
    <div class="bg-white rounded-xl shadow-md p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-bold text-gray-800"><i class="fas fa-receipt text-green-600 mr-2"></i>VAT Report</h3>
            <form method="GET" action="{{ route('reports.vat') }}" class="flex items-center gap-2">
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
                <div class="text-lg font-bold text-gray-800">{{ number_format($summary['total_final'], 2) }}</div>
            </div>
            <div class="p-4 bg-purple-50 rounded-lg">
                <div class="text-sm text-gray-600">VAT (Exclusive)</div>
                <div class="text-lg font-bold text-gray-800">{{ number_format($summary['vat_exclusive'], 2) }}</div>
                <div class="text-xs text-gray-500">If selling prices exclude VAT</div>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="p-4 bg-purple-50 rounded-lg">
                <div class="text-sm text-gray-600">VAT (Inclusive)</div>
                <div class="text-lg font-bold text-gray-800">{{ number_format($summary['vat_inclusive'], 2) }}</div>
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
                            <td class="px-4 py-2">{{ number_format($d['final_total'], 2) }}</td>
                            <td class="px-4 py-2">{{ number_format($d['vat_exclusive'], 2) }}</td>
                            <td class="px-4 py-2">{{ number_format($d['vat_inclusive'], 2) }}</td>
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
async function openVatDay(date) {
    try {
        const res = await fetch(`{{ route('reports.vat.day') }}?date=${date}`);
        const data = await res.json();
        document.getElementById('vatDayTitle').textContent = `VAT Details — ${data.date}`;
        document.getElementById('vatDayPdf').href = `{{ route('reports.vat.day.pdf') }}?date=${date}`;
        const s = data.totals || { line_total:0, vat_exclusive:0, vat_inclusive:0 };
        document.getElementById('vatDaySummary').innerHTML = `
            <div class='p-3 bg-blue-50 rounded'><div class='text-sm text-gray-600'>Sales Total</div><div class='text-lg font-bold'>${Number(s.line_total).toFixed(2)}</div></div>
            <div class='p-3 bg-purple-50 rounded'><div class='text-sm text-gray-600'>VAT (Exclusive)</div><div class='text-lg font-bold'>${Number(s.vat_exclusive).toFixed(2)}</div></div>
            <div class='p-3 bg-purple-50 rounded'><div class='text-sm text-gray-600'>VAT (Inclusive)</div><div class='text-lg font-bold'>${Number(s.vat_inclusive).toFixed(2)}</div></div>`;
        const body = document.getElementById('vatDayBody');
        body.innerHTML = '';
        (data.items || []).forEach(i => {
            body.insertAdjacentHTML('beforeend', `<tr class='border-t'>
                <td class='px-3 py-2'>${i.invoice ?? '-'}</td>
                <td class='px-3 py-2'>${i.product}</td>
                <td class='px-3 py-2'>${i.quantity}</td>
                <td class='px-3 py-2'>${Number(i.unit_price).toFixed(2)}</td>
                <td class='px-3 py-2'>${Number(i.line_total).toFixed(2)}</td>
                <td class='px-3 py-2'>${Number(i.vat_exclusive).toFixed(2)}</td>
                <td class='px-3 py-2'>${Number(i.vat_inclusive).toFixed(2)}</td>
            </tr>`);
        });
        document.getElementById('vatDayModal').classList.remove('hidden');
    } catch (e) { console.error(e); }
}
function closeVatDay() { document.getElementById('vatDayModal').classList.add('hidden'); }
</script>
@endsection
