@extends('layouts.app')

@section('title', 'Tax Dashboard')
@section('page-title', 'Tax Management Dashboard')

@section('content')
<div class="space-y-6">
    @include('tax.partials.date-filter')

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        @foreach([
            ['Today Taxable Sales', $stats['today_taxable_sales'], 'fa-receipt', 'blue', 'taxable_sales', true],
            ['Today Output VAT', $stats['today_output_vat'], 'fa-arrow-trend-up', 'emerald', 'output_vat', true],
            ['Today Taxable Purchases', $stats['today_taxable_purchases'], 'fa-cart-shopping', 'amber', 'taxable_purchases', true],
            ['Today Input VAT', $stats['today_input_vat'], 'fa-arrow-trend-down', 'purple', 'input_vat', true],
            ['Period Output VAT', $stats['output_vat'], 'fa-up-long', 'blue', 'output_vat', false],
            ['Period Input VAT', $stats['input_vat'], 'fa-down-long', 'emerald', 'input_vat', false],
            ['VAT Payments', $stats['payments'], 'fa-money-check-dollar', 'indigo', 'payments', false],
            ['Final Outstanding VAT', $stats['payable'], 'fa-scale-balanced', 'rose', 'outstanding', false],
        ] as [$label, $value, $icon, $color, $metric, $todayOnly])
        <button type="button" data-tax-detail="{{ $metric }}" data-today="{{ $todayOnly ? 1 : 0 }}" data-title="{{ $label }}" class="rounded-xl border border-gray-100 bg-white p-5 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-500">{{ $label }}</span>
                <i class="fas {{ $icon }} text-{{ $color }}-500"></i>
            </div>
            <div class="mt-3 text-2xl font-bold text-gray-900">Rs {{ number_format((float) $value, 2) }}</div>
            <div class="mt-2 text-xs font-semibold text-blue-600">View transaction details</div>
        </button>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
        @foreach([
            ['Previous Balance', $stats['previous_balance']],
            ['Debit Adjustments', $stats['debit_adjustments']],
            ['Credit Adjustments', $stats['credit_adjustments']],
            ['Carry-Forward Balance', $stats['payable']],
        ] as [$label, $value])
            <div class="rounded-xl bg-slate-800 p-4 text-white">
                <div class="text-xs uppercase tracking-wide text-slate-300">{{ $label }}</div>
                <div class="mt-1 text-xl font-semibold">Rs {{ number_format((float) $value, 2) }}</div>
            </div>
        @endforeach
    </div>

    <div class="rounded-xl bg-white shadow-sm border border-gray-100 overflow-hidden">
        <div class="flex items-center justify-between border-b p-4">
            <h3 class="font-bold text-gray-800">Recent VAT Ledger Activity</h3>
            <a href="{{ route('tax.reports') }}" class="text-sm font-semibold text-blue-600">Open Tax Reports</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-gray-600">
                    <tr><th class="p-3">Date</th><th class="p-3">Invoice</th><th class="p-3">Direction</th><th class="p-3 text-right">Taxable</th><th class="p-3 text-right">VAT</th><th class="p-3">Status</th></tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($recent as $entry)
                        <tr>
                            <td class="p-3">{{ $entry->entry_date?->format('Y-m-d') }}</td>
                            <td class="p-3 font-medium">{{ $entry->invoice_number }}</td>
                            <td class="p-3"><span class="rounded-full px-2 py-1 text-xs {{ $entry->direction === 'output' ? 'bg-blue-50 text-blue-700' : 'bg-green-50 text-green-700' }}">{{ ucfirst($entry->direction) }}</span></td>
                            <td class="p-3 text-right">Rs {{ number_format((float) $entry->taxable_amount, 2) }}</td>
                            <td class="p-3 text-right font-semibold">Rs {{ number_format((float) $entry->tax_amount, 2) }}</td>
                            <td class="p-3">{{ ucfirst($entry->status) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-8 text-center text-gray-500">No VAT activity in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="tax-detail-modal" class="hidden fixed inset-0 z-50 bg-black/50 p-4">
    <div class="mx-auto mt-10 max-h-[85vh] max-w-7xl overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div class="flex items-center justify-between border-b p-4"><h3 id="tax-detail-title" class="font-bold"></h3><button type="button" id="tax-detail-close" class="text-gray-500"><i class="fas fa-times"></i></button></div>
        <div class="max-h-[75vh] overflow-auto">
            <table class="w-full min-w-[1100px] text-sm"><thead class="sticky top-0 bg-gray-50"><tr><th class="p-3 text-left">Invoice</th><th class="p-3 text-left">Date</th><th class="p-3 text-left">Customer / Supplier</th><th class="p-3 text-left">TIN</th><th class="p-3 text-right">Taxable</th><th class="p-3 text-left">Rate</th><th class="p-3 text-right">VAT</th><th class="p-3 text-right">Total</th><th class="p-3 text-left">Store</th><th class="p-3 text-left">User</th><th class="p-3 text-left">Status</th></tr></thead><tbody id="tax-detail-body"></tbody></table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const modal = document.getElementById('tax-detail-modal');
    const body = document.getElementById('tax-detail-body');
    const title = document.getElementById('tax-detail-title');
    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[character]));
    document.querySelectorAll('[data-tax-detail]').forEach(button => button.addEventListener('click', async () => {
        title.textContent = button.dataset.title;
        body.innerHTML = '<tr><td colspan="11" class="p-8 text-center">Loading...</td></tr>';
        modal.classList.remove('hidden');
        const query = new URLSearchParams(window.location.search);
        query.set('today', button.dataset.today);
        const base = {{ \Illuminate\Support\Js::from(url('tax/dashboard/details')) }};
        try {
            const response = await fetch(`${base}/${encodeURIComponent(button.dataset.taxDetail)}?${query.toString()}`, {headers:{Accept:'application/json'}});
            if (!response.ok) throw new Error('Unable to load tax details.');
            const rows = await response.json();
            body.innerHTML = rows.length ? rows.map(row => `<tr class="border-t">
                <td class="p-3 font-semibold">${row.url ? `<a class="text-blue-600" href="${escapeHtml(row.url)}">${escapeHtml(row.invoice_number)}</a>` : escapeHtml(row.invoice_number)}</td>
                <td class="p-3">${escapeHtml(row.date)}</td><td class="p-3">${escapeHtml(row.party)}</td><td class="p-3">${escapeHtml(row.tin)}</td>
                <td class="p-3 text-right">${Number(row.taxable_value || 0).toFixed(2)}</td><td class="p-3">${escapeHtml(row.vat_rate)}</td>
                <td class="p-3 text-right">${Number(row.vat_amount || 0).toFixed(2)}</td><td class="p-3 text-right">${Number(row.total || 0).toFixed(2)}</td>
                <td class="p-3">${escapeHtml(row.store)}</td><td class="p-3">${escapeHtml(row.user)}</td><td class="p-3">${escapeHtml(row.status)}</td>
            </tr>`).join('') : '<tr><td colspan="11" class="p-8 text-center text-gray-500">No matching transactions.</td></tr>';
        } catch (error) {
            body.innerHTML = `<tr><td colspan="11" class="p-8 text-center text-red-600">${escapeHtml(error.message)}</td></tr>`;
        }
    }));
    document.getElementById('tax-detail-close')?.addEventListener('click', () => modal.classList.add('hidden'));
    modal?.addEventListener('click', event => { if (event.target === modal) modal.classList.add('hidden'); });
})();
</script>
@endpush
