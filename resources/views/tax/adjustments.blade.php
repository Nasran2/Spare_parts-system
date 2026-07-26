@extends('layouts.app')

@section('title', 'VAT Adjustments')
@section('page-title', 'VAT Adjustments')

@section('content')
<div class="space-y-6">
    @if(session('success'))<div class="rounded-lg bg-green-50 border border-green-200 p-3 text-green-700">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="rounded-lg bg-red-50 border border-red-200 p-3 text-red-700">{{ $errors->first() }}</div>@endif
    @if(auth()->user()?->hasPermission('tax.adjustment.manage'))
    <form method="POST" action="{{ route('tax.adjustments.store') }}" class="rounded-xl bg-white border p-5 shadow-sm">
        @csrf
        <h3 class="font-bold mb-4">Create VAT Adjustment</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div><label class="block text-xs font-semibold mb-1">Tax Period</label><input type="month" name="tax_period" value="{{ now()->format('Y-m') }}" class="w-full rounded-lg border-gray-300" required></div>
            <div><label class="block text-xs font-semibold mb-1">Adjustment Date</label><input type="date" name="adjustment_date" value="{{ now()->toDateString() }}" class="w-full rounded-lg border-gray-300" required></div>
            <div><label class="block text-xs font-semibold mb-1">Type</label><select name="adjustment_type" class="w-full rounded-lg border-gray-300"><option value="debit">Debit (increase payable)</option><option value="credit">Credit (reduce payable)</option></select></div>
            <div><label class="block text-xs font-semibold mb-1">Amount</label><input type="number" step="0.0001" min="0.0001" name="amount" class="w-full rounded-lg border-gray-300" required></div>
            <div><label class="block text-xs font-semibold mb-1">Reference</label><input name="reference" class="w-full rounded-lg border-gray-300" required></div>
            <div><label class="block text-xs font-semibold mb-1">Reason</label><input name="reason" class="w-full rounded-lg border-gray-300" required></div>
        </div>
        <div class="mt-4 text-right"><button name="approve" value="1" class="rounded-lg bg-blue-600 px-4 py-2 text-white">Save & Approve</button></div>
    </form>
    @endif
    <div class="rounded-xl bg-white border overflow-x-auto">
        <table class="w-full text-sm"><thead class="bg-gray-50 text-left"><tr><th class="p-3">Period</th><th class="p-3">Date</th><th class="p-3">Reference</th><th class="p-3">Type</th><th class="p-3 text-right">Amount</th><th class="p-3">Reason</th><th class="p-3">Status</th></tr></thead>
        <tbody class="divide-y">@forelse($adjustments as $adjustment)<tr><td class="p-3">{{ $adjustment->tax_period }}</td><td class="p-3">{{ $adjustment->adjustment_date?->format('Y-m-d') }}</td><td class="p-3 font-semibold">{{ $adjustment->reference }}</td><td class="p-3">{{ ucfirst($adjustment->adjustment_type) }}</td><td class="p-3 text-right">Rs {{ number_format((float) $adjustment->amount, 2) }}</td><td class="p-3">{{ $adjustment->reason }}</td><td class="p-3">{{ ucfirst($adjustment->status) }} @if($adjustment->status==='draft' && auth()->user()?->hasPermission('tax.adjustment.manage'))<form method="POST" action="{{ route('tax.adjustments.approve', $adjustment) }}" class="inline ml-2">@csrf<button class="text-blue-600">Approve</button></form>@endif</td></tr>@empty<tr><td colspan="7" class="p-8 text-center text-gray-500">No adjustments.</td></tr>@endforelse</tbody></table>
        <div class="p-4">{{ $adjustments->links() }}</div>
    </div>
</div>
@endsection
