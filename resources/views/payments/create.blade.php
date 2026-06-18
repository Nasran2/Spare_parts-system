@extends('layouts.app')

@section('title', 'Add Payment')
@section('page-title', 'Add Payment')

@section('content')
@php
    $controls = is_array($controls ?? null) ? $controls : [];
@endphp
<div class="max-w-xl mx-auto bg-white rounded-xl shadow-lg p-6 mt-8">
    <h2 class="text-xl font-bold mb-4">Add Payment for Purchase</h2>
    <div class="mb-4 p-4 bg-blue-50 rounded">
        <div><strong>Supplier:</strong> {{ !empty($controls['hide_supplier_names']) ? 'Hidden Supplier' : $supplier->name }}</div>
        <div><strong>Purchase No:</strong> {{ !empty($controls['hide_invoice_details']) ? 'HIDDEN' : $purchase->purchase_no }}</div>
        <div><strong>Purchase Date:</strong> {{ optional($purchase->purchase_date)->format('M d, Y') }}</div>
        <div><strong>Total Amount:</strong> {{ !empty($controls['hide_total_purchase']) || !empty($controls['hide_price_wise_data']) ? '—' : ('$' . number_format($purchase->total_amount, 2)) }}</div>
        <div><strong>Paid:</strong> {{ !empty($controls['hide_supplier_payments']) || !empty($controls['hide_price_wise_data']) ? '—' : ('$' . number_format($purchase->paid_amount, 2)) }}</div>
        <div><strong>Due:</strong> {{ !empty($controls['hide_supplier_payments']) || !empty($controls['hide_price_wise_data']) ? '—' : ('$' . number_format($purchase->due_amount, 2)) }}</div>
    </div>
    <form method="POST" action="{{ route('payments.store') }}">
        @csrf
        <input type="hidden" name="purchase_id" value="{{ $purchase->id }}">
        <div class="mb-4">
            <label class="block text-sm font-semibold mb-1">Payment Amount</label>
            <input type="number" name="amount" step="0.01" min="0.01" max="{{ $purchase->due_amount }}" value="{{ $purchase->due_amount }}" class="w-full border rounded px-3 py-2" required>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-semibold mb-1">Payment Method</label>
            <select name="payment_method" class="w-full border rounded px-3 py-2" required>
                <option value="Cash">Cash</option>
                <option value="Card">Card</option>
                <option value="Bank Transfer">Bank Transfer</option>
                <option value="Cheque">Cheque</option>
                <option value="Credit">Credit</option>
            </select>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-semibold mb-1">Payment Date</label>
            <input type="date" name="payment_date" value="{{ date('Y-m-d') }}" class="w-full border rounded px-3 py-2" required>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-semibold mb-1">Notes</label>
            <textarea name="notes" rows="2" class="w-full border rounded px-3 py-2"></textarea>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 font-semibold">Save Payment</button>
            <a href="{{ route('suppliers.show', $supplier->id) }}" class="px-6 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300 font-semibold">Cancel</a>
        </div>
    </form>
</div>
@endsection
