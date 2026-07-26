@extends('layouts.app')

@section('title', ucfirst($direction).' VAT')
@section('page-title', $direction === 'output' ? 'Sales VAT / Output VAT' : 'Purchase VAT / Input VAT')

@section('content')
<div class="space-y-5">
    @include('tax.partials.date-filter')
    <div class="rounded-xl bg-white shadow-sm border overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600 text-left"><tr><th class="p-3">Invoice</th><th class="p-3">Date</th><th class="p-3">Tax Period</th><th class="p-3 text-right">Taxable Value</th><th class="p-3 text-right">VAT Amount</th><th class="p-3">Store</th><th class="p-3">Status</th></tr></thead>
                <tbody class="divide-y">
                    @forelse($entries as $entry)
                    <tr>
                        <td class="p-3 font-semibold">{{ $entry->invoice_number }}</td>
                        <td class="p-3">{{ $entry->entry_date?->format('Y-m-d') }}</td>
                        <td class="p-3">{{ $entry->tax_period }}</td>
                        <td class="p-3 text-right">Rs {{ number_format((float) $entry->taxable_amount, 2) }}</td>
                        <td class="p-3 text-right font-semibold">Rs {{ number_format((float) $entry->tax_amount, 2) }}</td>
                        <td class="p-3">{{ $entry->store_id ?: '—' }}</td>
                        <td class="p-3">{{ ucfirst($entry->status) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="p-8 text-center text-gray-500">No entries found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $entries->links() }}</div>
    </div>
</div>
@endsection
