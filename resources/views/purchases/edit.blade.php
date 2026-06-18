@extends('layouts.app')

@section('title', 'Edit Purchase')
@section('page-title', 'Edit Purchase')

@section('content')
@php
    $selectedSupplier = old('supplier_id', $purchase->supplier_id);
    $selectedStatus = old('status', $purchase->status ?? 'pending');
    $selectedPaymentMethod = old('payment_method', $purchase->payment_method ?? 'cash');
    $purchaseDate = old('purchase_date', optional($purchase->purchase_date)->format('Y-m-d'));
@endphp

<div class="max-w-6xl mx-auto space-y-6">
    <div>
        <a href="{{ route('purchases.index') }}" class="inline-flex items-center text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left mr-2"></i>Back to Purchases
        </a>
    </div>

    @if ($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-4">
            <div class="font-semibold mb-2">Please fix the following:</div>
            <ul class="list-disc list-inside text-sm space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white rounded-xl shadow-md p-6">
        <form action="{{ route('purchases.update', $purchase->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Supplier <span class="text-red-500">*</span></label>
                    <select name="supplier_id" id="supplier_id" class="w-full border border-gray-300 rounded px-3 py-2 focus:ring-2 focus:ring-blue-500" required onchange="loadSupplierAddress()">
                        <option value="">Please Select</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" data-address="{{ trim(($supplier->address ?? '') . ', ' . ($supplier->city ?? ''), ', ') }}" @selected((string) $selectedSupplier === (string) $supplier->id)>
                                {{ $supplier->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Reference No</label>
                    <input type="text" name="reference_no" value="{{ old('reference_no', $purchase->reference_no) }}" class="w-full border border-gray-300 rounded px-3 py-2 focus:ring-2 focus:ring-blue-500" placeholder="Optional">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Purchase Date <span class="text-red-500">*</span></label>
                    <input type="date" name="purchase_date" value="{{ $purchaseDate }}" class="w-full border border-gray-300 rounded px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Purchase Status <span class="text-red-500">*</span></label>
                    <select name="status" class="w-full border border-gray-300 rounded px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
                        <option value="received" @selected($selectedStatus === 'received')>Received</option>
                        <option value="pending" @selected($selectedStatus === 'pending')>Pending</option>
                        <option value="ordered" @selected($selectedStatus === 'ordered')>Ordered</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Address</label>
                    <input type="text" id="supplier_address" readonly class="w-full border border-gray-300 rounded px-3 py-2 bg-gray-50" placeholder="Auto-filled">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Replace Attached Document</label>
                    <input type="file" name="document" accept=".pdf,.csv,.zip,.doc,.docx,.jpeg,.jpg,.png" class="w-full border border-gray-300 rounded px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    @if($purchase->document_path)
                        <a href="{{ asset('storage/' . $purchase->document_path) }}" target="_blank" class="inline-flex items-center mt-2 text-sm text-blue-600 hover:text-blue-800">
                            <i class="fas fa-paperclip mr-2"></i>View current document
                        </a>
                    @endif
                    <p class="text-xs text-gray-500 mt-1">Max file size: 5MB</p>
                </div>
            </div>

            <div class="overflow-x-auto mb-6">
                <table class="w-full border-collapse">
                    <thead class="bg-green-600 text-white">
                        <tr>
                            <th class="border border-gray-300 px-3 py-2 text-left text-sm">#</th>
                            <th class="border border-gray-300 px-3 py-2 text-left text-sm">Product Name</th>
                            <th class="border border-gray-300 px-3 py-2 text-right text-sm">Quantity</th>
                            <th class="border border-gray-300 px-3 py-2 text-right text-sm">Unit Cost</th>
                            <th class="border border-gray-300 px-3 py-2 text-right text-sm">Line Total</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white">
                        @forelse($purchase->items as $index => $item)
                            <tr class="border-b">
                                <td class="border px-3 py-2 text-center">{{ $index + 1 }}</td>
                                <td class="border px-3 py-2">{{ $item->product->name ?? 'Product deleted' }}</td>
                                <td class="border px-3 py-2 text-right">{{ $item->quantity }}</td>
                                <td class="border px-3 py-2 text-right">{{ number_format((float) $item->unit_cost, 2) }}</td>
                                <td class="border px-3 py-2 text-right font-semibold">{{ number_format((float) $item->total, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="border px-3 py-8 text-center text-gray-500">No products recorded for this purchase.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div class="bg-blue-50 p-4 rounded-lg">
                    <h3 class="font-semibold text-gray-800 mb-4">Payment</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Payment Method</label>
                            <select name="payment_method" class="w-full border border-gray-300 rounded px-3 py-2 focus:ring-2 focus:ring-blue-500">
                                <option value="cash" @selected($selectedPaymentMethod === 'cash')>Cash</option>
                                <option value="card" @selected($selectedPaymentMethod === 'card')>Card</option>
                                <option value="bank_transfer" @selected($selectedPaymentMethod === 'bank_transfer')>Bank Transfer</option>
                                <option value="cheque" @selected($selectedPaymentMethod === 'cheque')>Cheque</option>
                                <option value="credit" @selected($selectedPaymentMethod === 'credit')>Credit</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Paid Amount</label>
                            <input type="number" name="paid_amount" value="{{ old('paid_amount', $purchase->paid_amount) }}" min="0" max="{{ $purchase->total_amount }}" step="0.01" class="w-full border border-gray-300 rounded px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 p-4 rounded-lg border">
                    <h3 class="font-semibold text-gray-800 mb-4">Totals</h3>
                    <div class="space-y-2">
                        <div class="flex justify-between"><span>Total Amount</span><span class="font-semibold">{{ number_format((float) $purchase->total_amount, 2) }}</span></div>
                        <div class="flex justify-between"><span>Paid Amount</span><span class="font-semibold">{{ number_format((float) $purchase->paid_amount, 2) }}</span></div>
                        <div class="flex justify-between"><span>Due Amount</span><span class="font-semibold text-red-600">{{ number_format((float) $purchase->due_amount, 2) }}</span></div>
                        <div class="flex justify-between"><span>Payment Status</span><span class="font-semibold">{{ ucfirst($purchase->payment_status ?? 'unpaid') }}</span></div>
                    </div>
                </div>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Additional Notes</label>
                <textarea name="notes" rows="3" class="w-full border border-gray-300 rounded px-3 py-2 focus:ring-2 focus:ring-blue-500">{{ old('notes', $purchase->notes) }}</textarea>
            </div>

            <div class="flex items-center gap-2">
                <button type="submit" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-semibold">
                    <i class="fas fa-save mr-2"></i>Update Purchase
                </button>
                <a href="{{ route('purchases.show', $purchase->id) }}" class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
function loadSupplierAddress() {
    const sel = document.getElementById('supplier_id');
    const opt = sel ? sel.selectedOptions[0] : null;
    const address = document.getElementById('supplier_address');
    if (address) {
        address.value = opt ? (opt.dataset.address || '') : '';
    }
}

document.addEventListener('DOMContentLoaded', loadSupplierAddress);
</script>
@endsection
