@extends('layouts.app')

@section('title', 'Tax Reports')
@section('page-title', 'Tax Reports')

@section('content')
<div class="space-y-5">
    <div class="rounded-xl bg-white border p-4 shadow-sm">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
            <div><label class="block text-xs font-semibold mb-1">Report</label><select name="type" class="w-full rounded-lg border-gray-300">@foreach($reports as $key => $label)<option value="{{ $key }}" @selected($type === $key)>{{ $label }}</option>@endforeach</select></div>
            <div><label class="block text-xs font-semibold mb-1">Quick Range</label><select name="range" class="w-full rounded-lg border-gray-300">@foreach(['custom'=>'Custom Date','today'=>'Today','yesterday'=>'Yesterday','this_week'=>'This Week','last_week'=>'Last Week','this_month'=>'This Month','last_month'=>'Last Month','this_quarter'=>'This Quarter','last_quarter'=>'Last Quarter','this_year'=>'This Year'] as $value=>$label)<option value="{{ $value }}" @selected(request('range','custom')===$value)>{{ $label }}</option>@endforeach</select></div>
            <div><label class="block text-xs font-semibold mb-1">From</label><input type="date" name="from" value="{{ $from }}" class="w-full rounded-lg border-gray-300"></div>
            <div><label class="block text-xs font-semibold mb-1">To</label><input type="date" name="to" value="{{ $to }}" class="w-full rounded-lg border-gray-300"></div>
            <button class="rounded-lg bg-blue-600 px-4 py-2 text-white">Run Report</button>
            <div><label class="block text-xs font-semibold mb-1">Customer</label><select name="customer_id" class="w-full rounded-lg border-gray-300"><option value="">All</option>@foreach($customers as $item)<option value="{{ $item->id }}" @selected((string)request('customer_id')===(string)$item->id)>{{ $item->name }}</option>@endforeach</select></div>
            <div><label class="block text-xs font-semibold mb-1">Supplier</label><select name="supplier_id" class="w-full rounded-lg border-gray-300"><option value="">All</option>@foreach($suppliers as $item)<option value="{{ $item->id }}" @selected((string)request('supplier_id')===(string)$item->id)>{{ $item->name }}</option>@endforeach</select></div>
            <div><label class="block text-xs font-semibold mb-1">Product</label><select name="product_id" class="w-full rounded-lg border-gray-300"><option value="">All</option>@foreach($products as $item)<option value="{{ $item->id }}" @selected((string)request('product_id')===(string)$item->id)>{{ $item->name }}</option>@endforeach</select></div>
            <div><label class="block text-xs font-semibold mb-1">Category</label><select name="category_id" class="w-full rounded-lg border-gray-300"><option value="">All</option>@foreach($categories as $item)<option value="{{ $item->id }}" @selected((string)request('category_id')===(string)$item->id)>{{ $item->name }}</option>@endforeach</select></div>
            <div><label class="block text-xs font-semibold mb-1">Store</label><select name="store_id" class="w-full rounded-lg border-gray-300"><option value="">All</option>@foreach($stores as $item)<option value="{{ $item->id }}" @selected((string)request('store_id')===(string)$item->id)>{{ $item->name }}</option>@endforeach</select></div>
            <div><label class="block text-xs font-semibold mb-1">User</label><select name="user_id" class="w-full rounded-lg border-gray-300"><option value="">All</option>@foreach($users as $item)<option value="{{ $item->id }}" @selected((string)request('user_id')===(string)$item->id)>{{ $item->name }}</option>@endforeach</select></div>
            <div><label class="block text-xs font-semibold mb-1">Tax Status</label><select name="tax_status" class="w-full rounded-lg border-gray-300"><option value="">All</option>@foreach(['standard'=>'Standard Rated','zero_rated'=>'Zero Rated','exempt'=>'Exempt','out_of_scope'=>'Out of Scope'] as $value=>$label)<option value="{{ $value }}" @selected(request('tax_status')===$value)>{{ $label }}</option>@endforeach</select></div>
            <div><label class="block text-xs font-semibold mb-1">VAT Rate</label><input type="number" step="0.0001" min="0" name="vat_rate" value="{{ request('vat_rate') }}" class="w-full rounded-lg border-gray-300"></div>
            <div><label class="block text-xs font-semibold mb-1">Payment Status</label><select name="payment_status" class="w-full rounded-lg border-gray-300"><option value="">All</option>@foreach(['paid','partial','unpaid'] as $value)<option value="{{ $value }}" @selected(request('payment_status')===$value)>{{ ucfirst($value) }}</option>@endforeach</select></div>
            <div><label class="block text-xs font-semibold mb-1">Invoice Number</label><input name="invoice" value="{{ request('invoice') }}" class="w-full rounded-lg border-gray-300"></div>
            <div><label class="block text-xs font-semibold mb-1">TIN</label><input name="tin" value="{{ request('tin') }}" class="w-full rounded-lg border-gray-300"></div>
        </form>
        @if(auth()->user()?->hasPermission('tax.reports.export'))
        <div class="mt-3 flex justify-end gap-2">
            <a href="{{ route('tax.reports.export', array_merge(request()->query(), ['format' => 'xlsx', 'type' => $type, 'from' => $from, 'to' => $to])) }}" class="rounded-lg bg-emerald-600 px-3 py-2 text-sm text-white"><i class="fas fa-file-excel mr-1"></i>Excel</a>
            <a href="{{ route('tax.reports.export', array_merge(request()->query(), ['format' => 'pdf', 'type' => $type, 'from' => $from, 'to' => $to])) }}" class="rounded-lg bg-red-600 px-3 py-2 text-sm text-white"><i class="fas fa-file-pdf mr-1"></i>PDF</a>
        </div>
        @endif
    </div>
    <div class="rounded-xl bg-white border overflow-x-auto">
        <div class="border-b p-4 font-bold">{{ $title }}</div>
        <table class="w-full text-sm"><thead class="bg-gray-50 text-left"><tr><th class="p-3">Invoice</th><th class="p-3">Date</th><th class="p-3">Direction</th><th class="p-3 text-right">Taxable</th><th class="p-3 text-right">VAT</th><th class="p-3">Status</th></tr></thead>
        <tbody class="divide-y">@forelse($rows as $row)<tr><td class="p-3 font-semibold">{{ $row->invoice_number }}</td><td class="p-3">{{ $row->entry_date?->format('Y-m-d') }}</td><td class="p-3">{{ ucfirst($row->direction) }}</td><td class="p-3 text-right">Rs {{ number_format((float) $row->taxable_amount, 2) }}</td><td class="p-3 text-right">Rs {{ number_format((float) $row->tax_amount, 2) }}</td><td class="p-3">{{ ucfirst($row->status) }}</td></tr>@empty<tr><td colspan="6" class="p-8 text-center text-gray-500">No report data.</td></tr>@endforelse</tbody>
        <tfoot class="border-t-2 bg-gray-50 font-bold"><tr><td colspan="3" class="p-3">TOTAL</td><td class="p-3 text-right">Rs {{ number_format((float) $totals['taxable'], 2) }}</td><td class="p-3 text-right">Rs {{ number_format((float) $totals['vat'], 2) }}</td><td></td></tr></tfoot></table>
    </div>
</div>
@endsection
