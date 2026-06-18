@extends('layouts.app')

@section('title', 'Purchase Details')
@section('page-title', 'Purchase Details')

@section('content')
@php
    $controls = is_array($controls ?? null) ? $controls : [];
    $priceVisiblePct = (float) ($controls['price_visible_percentage'] ?? 100);
    $applyPct = function ($value, $pct) {
        $pct = max(0, min(100, (float) $pct));
        return (float) $value * ($pct / 100);
    };
    $maskMoney = function ($value, $forceHide = false) use ($controls, $priceVisiblePct, $applyPct) {
        if ($forceHide || !empty($controls['hide_price_wise_data'])) {
            return '—';
        }

        $masked = $applyPct((float) $value, $priceVisiblePct);

        $roundToWhole = $priceVisiblePct < 100;


        return number_format($roundToWhole ? round($masked) : $masked, $roundToWhole ? 0 : 2);
    };
@endphp
<div class="max-w-6xl mx-auto space-y-6">
    <div>
        <a href="{{ route('purchases.index') }}" class="inline-flex items-center text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left mr-2"></i>Back to Purchases
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="bg-gradient-to-r from-blue-600 to-blue-700 p-6 text-white flex justify-between items-start">
            <div>
                <h2 class="text-2xl font-bold flex items-center"><i class="fas fa-file-invoice-dollar mr-3"></i>{{ $purchase->purchase_no }}</h2>
                <p class="text-blue-100">{{ $purchase->supplier->name ?? 'Supplier' }} — {{ optional($purchase->purchase_date)->format('M d, Y') }}</p>
            </div>
            <div class="text-right">
                <p class="text-sm">Status: <span class="px-2 py-1 bg-green-100 text-green-700 rounded">{{ ucfirst($purchase->payment_status ?? 'pending') }}</span></p>
            </div>
        </div>

        <div class="p-6 space-y-6">
            <div class="overflow-x-auto">
                <table class="min-w-full table-auto border-collapse">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="px-4 py-2 text-left">#</th>
                            <th class="px-4 py-2 text-left">Product</th>
                            <th class="px-4 py-2 text-right">Qty</th>
                            <th class="px-4 py-2 text-right">Unit Cost</th>
                            <th class="px-4 py-2 text-right">Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($purchase->items as $i => $item)
                        <tr class="border-b">
                            <td class="px-4 py-2">{{ $i + 1 }}</td>
                            <td class="px-4 py-2">{{ $item->product->name ?? 'Product' }}</td>
                            <td class="px-4 py-2 text-right">{{ $item->quantity }}</td>
                            <td class="px-4 py-2 text-right">${{ $maskMoney($item->unit_cost, !empty($controls['hide_actual_purchase_price']) || !empty($controls['hide_actual_stock_price'])) }}</td>
                            <td class="px-4 py-2 text-right">${{ $maskMoney(($item->quantity * $item->unit_cost), !empty($controls['hide_total_purchase']) || !empty($controls['hide_invoice_details'])) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex justify-end">
                <div class="w-full max-w-md bg-gray-50 p-4 rounded-lg border">
                    <div class="flex justify-between py-1"><span class="text-sm">Net Total</span><span class="font-semibold">${{ $maskMoney(($purchase->total_amount - ($purchase->shipping_cost ?? 0) - ($purchase->tax_amount ?? 0) + ($purchase->discount_amount ?? 0)), !empty($controls['hide_total_purchase']) || !empty($controls['hide_invoice_details'])) }}</span></div>
                    <div class="flex justify-between py-1"><span class="text-sm">Discount</span><span class="font-semibold">${{ $maskMoney(($purchase->discount_amount ?? 0), !empty($controls['hide_total_purchase']) || !empty($controls['hide_invoice_details'])) }}</span></div>
                    <div class="flex justify-between py-1"><span class="text-sm">Tax</span><span class="font-semibold">${{ $maskMoney(($purchase->tax_amount ?? 0), !empty($controls['hide_total_purchase']) || !empty($controls['hide_invoice_details'])) }}</span></div>
                    <div class="flex justify-between py-1"><span class="text-sm">Shipping</span><span class="font-semibold">${{ $maskMoney(($purchase->shipping_cost ?? 0), !empty($controls['hide_total_purchase']) || !empty($controls['hide_invoice_details'])) }}</span></div>
                    <hr class="my-2" />
                    <div class="flex justify-between py-1 text-lg"><span class="font-semibold">Grand Total</span><span class="font-bold text-blue-600">${{ $maskMoney(($purchase->total_amount ?? 0), !empty($controls['hide_total_purchase']) || !empty($controls['hide_invoice_details'])) }}</span></div>
                    <div class="flex justify-between py-1"><span class="text-sm">Paid</span><span class="font-semibold">${{ $maskMoney(($purchase->paid_amount ?? 0), !empty($controls['hide_supplier_payments']) || !empty($controls['hide_invoice_details'])) }}</span></div>
                    <div class="flex justify-between py-1"><span class="text-sm">Due</span><span class="font-semibold text-red-600">${{ $maskMoney(($purchase->due_amount ?? 0), !empty($controls['hide_supplier_payments']) || !empty($controls['hide_invoice_details'])) }}</span></div>
                    <div class="mt-3 text-right">
                        @if(($purchase->due_amount ?? 0) > 0)
                            <a href="{{ route('payments.create', ['purchase_id' => $purchase->id]) }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Add Payment</a>
                        @else
                            <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full">Paid</span>
                        @endif
                    </div>
                </div>
            </div>

            <div>
                <h4 class="text-lg font-semibold mb-2">Payments</h4>
                <div class="bg-white border rounded">
                    <table class="min-w-full">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="px-4 py-2 text-left">Date</th>
                                <th class="px-4 py-2 text-left">Method</th>
                                <th class="px-4 py-2 text-right">Amount</th>
                                <th class="px-4 py-2 text-left">Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($purchase->payments as $payment)
                            <tr class="border-b">
                                <td class="px-4 py-2">{{ optional($payment->payment_date)->format('M d, Y') }}</td>
                                <td class="px-4 py-2">{{ $payment->payment_method }}</td>
                                <td class="px-4 py-2 text-right">${{ $maskMoney($payment->amount, !empty($controls['hide_supplier_payments']) || !empty($controls['hide_invoice_details'])) }}</td>
                                <td class="px-4 py-2">{{ $payment->notes }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td class="px-4 py-6 text-center text-gray-500" colspan="4">No payments recorded.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex gap-3 pt-4 border-t">
                <a href="{{ route('purchases.edit', $purchase->id) }}" class="px-6 py-3 bg-blue-600 text-white rounded hover:bg-blue-700 font-semibold">Edit</a>
                <form action="{{ route('purchases.destroy', $purchase->id) }}" method="POST" onsubmit="return confirm('Delete this purchase? This action cannot be undone.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="px-6 py-3 bg-red-600 text-white rounded hover:bg-red-700 font-semibold">Delete</button>
                </form>
                <a href="{{ route('purchases.index') }}" class="px-6 py-3 bg-gray-200 text-gray-800 rounded hover:bg-gray-300 font-semibold">Back</a>
            </div>
        </div>
    </div>
</div>
@endsection
