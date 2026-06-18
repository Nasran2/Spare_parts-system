@extends('layouts.app')

@section('title', 'Supplier Details')
@section('page-title', 'Supplier Details')

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
<div class="max-w-4xl mx-auto space-y-6">
    <div>
        <a href="{{ route('suppliers.index') }}" class="inline-flex items-center text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left mr-2"></i>Back to Suppliers
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="bg-gradient-to-r from-blue-600 to-blue-700 p-6 text-white flex justify-between items-start">
            <div>
                <h2 class="text-2xl font-bold flex items-center"><i class="fas fa-truck mr-3"></i>{{ !empty($controls['hide_supplier_names']) ? 'Hidden Supplier' : $supplier->name }}</h2>
                <p class="text-blue-100">{{ !empty($controls['hide_supplier_names']) ? 'Hidden' : ($supplier->company_name ?? 'Individual Supplier') }}</p>
            </div>
            <div>
                @if($supplier->is_active)
                    <span class="px-3 py-1 bg-green-500 rounded-full text-xs font-semibold"><i class="fas fa-check-circle mr-1"></i>Active</span>
                @else
                    <span class="px-3 py-1 bg-gray-500 rounded-full text-xs font-semibold"><i class="fas fa-ban mr-1"></i>Inactive</span>
                @endif
            </div>
        </div>
        <div class="p-6 space-y-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-4">
                    <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Contact Information</h3>
                    <div class="space-y-2 text-sm">
                        <div class="flex items-center text-gray-700"><i class="fas fa-phone w-5 text-blue-600"></i><span>{{ !empty($controls['hide_supplier_names']) ? 'Hidden' : $supplier->phone }}</span></div>
                        <div class="flex items-center text-gray-700"><i class="fas fa-envelope w-5 text-blue-600"></i><span>{{ !empty($controls['hide_supplier_names']) ? 'Hidden' : ($supplier->email ?? 'N/A') }}</span></div>
                        <div class="flex items-start text-gray-700"><i class="fas fa-map-marker-alt w-5 text-blue-600 mt-1"></i><span>{{ !empty($controls['hide_supplier_names']) ? 'Hidden' : ($supplier->address ?? 'No address provided') }}</span></div>
                        <div class="flex items-center text-gray-700"><i class="fas fa-city w-5 text-blue-600"></i><span>{{ !empty($controls['hide_supplier_names']) ? 'Hidden' : ($supplier->city ?? '—') }}</span></div>
                        <div class="flex items-center text-gray-700"><i class="fas fa-globe w-5 text-blue-600"></i><span>{{ !empty($controls['hide_supplier_names']) ? 'Hidden' : ($supplier->country ?? '—') }}</span></div>
                    </div>
                </div>
                <div class="space-y-4">
                    <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Account Summary</h3>
                    <div class="grid grid-cols-3 gap-4">
                        <div class="bg-blue-50 p-4 rounded-lg border border-blue-100">
                            <p class="text-xs uppercase text-blue-600 font-semibold">Opening Balance</p>
                            <p class="text-xl font-bold text-gray-800 mt-1">{{ $maskMoney($supplier->opening_balance, !empty($controls['hide_supplier_payments'])) }}</p>
                        </div>
                        <div class="bg-green-50 p-4 rounded-lg border border-green-100">
                            <p class="text-xs uppercase text-green-600 font-semibold">Total Purchases</p>
                            <p class="text-xl font-bold text-gray-800 mt-1">{{ $maskMoney(($purchaseTotals['total_purchases'] ?? 0), !empty($controls['hide_total_purchase']) || !empty($controls['hide_supplier_payments'])) }}</p>
                        </div>
                        <div class="bg-red-50 p-4 rounded-lg border border-red-100">
                            <p class="text-xs uppercase text-red-600 font-semibold">Total Due</p>
                            <p class="text-xl font-bold text-gray-800 mt-1">{{ $maskMoney(($purchaseTotals['total_due'] ?? 0), !empty($controls['hide_supplier_payments'])) }}</p>
                        </div>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                        <p class="text-xs uppercase text-gray-500 font-semibold mb-2">Notes</p>
                        <p class="text-sm text-gray-600">(Future enhancement: Add notes/support documents field.)</p>
                    </div>
                </div>
            </div>
            <div class="mt-8">
                <h3 class="text-lg font-semibold text-gray-700 mb-4">Purchase & Payment History</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                        <thead>
                            <tr>
                                <th class="px-4 py-2 border-b text-left">Purchase Date</th>
                                <th class="px-4 py-2 border-b text-left">Purchase No</th>
                                <th class="px-4 py-2 border-b text-right">Amount</th>
                                <th class="px-4 py-2 border-b text-right">Paid</th>
                                <th class="px-4 py-2 border-b text-right">Due</th>
                                <th class="px-4 py-2 border-b text-left">Payment(s)</th>
                                <th class="px-4 py-2 border-b text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($purchases as $purchase)
                            <tr>
                                <td class="px-4 py-2 border-b">{{ optional($purchase->purchase_date)->format('M d, Y') }}</td>
                                <td class="px-4 py-2 border-b">{{ !empty($controls['hide_invoice_details']) ? 'HIDDEN' : $purchase->purchase_no }}</td>
                                <td class="px-4 py-2 border-b text-right">${{ $maskMoney($purchase->total_amount, !empty($controls['hide_total_purchase']) || !empty($controls['hide_invoice_details'])) }}</td>
                                <td class="px-4 py-2 border-b text-right">${{ $maskMoney($purchase->paid_amount, !empty($controls['hide_supplier_payments']) || !empty($controls['hide_invoice_details'])) }}</td>
                                <td class="px-4 py-2 border-b text-right">${{ $maskMoney($purchase->due_amount, !empty($controls['hide_supplier_payments']) || !empty($controls['hide_invoice_details'])) }}</td>
                                <td class="px-4 py-2 border-b text-left">
                                    @if(!empty($controls['hide_supplier_payments']))
                                        <span class="text-xs text-gray-500">Hidden</span>
                                    @else
                                    @foreach($purchase->payments as $payment)
                                        <div>
                                            <span class="text-xs text-gray-700">{{ optional($payment->payment_date)->format('M d, Y') }}:</span>
                                            <span class="font-semibold text-green-700">${{ $maskMoney($payment->amount) }}</span>
                                            <span class="text-xs text-gray-500">({{ $payment->payment_method }})</span>
                                        </div>
                                    @endforeach
                                    @endif
                                </td>
                                <td class="px-4 py-2 border-b text-center">
                                    @if($purchase->due_amount > 0 && empty($controls['hide_supplier_payments']))
                                        <a href="{{ route('payments.create', ['purchase_id' => $purchase->id]) }}" class="inline-flex whitespace-nowrap items-center px-3 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm font-medium">Add Payment</a>
                                    @else
                                        <span class="inline-flex items-center px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-semibold">Paid</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="flex flex-col md:flex-row gap-3 pt-4 border-t">
                <a href="{{ route('suppliers.edit', $supplier->id) }}" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold text-center"><i class="fas fa-edit mr-2"></i>Edit</a>
                <form action="{{ route('suppliers.destroy', $supplier->id) }}" method="POST" onsubmit="return confirm('Delete this supplier? This action cannot be undone.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="px-6 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition font-semibold w-full md:w-auto"><i class="fas fa-trash mr-2"></i>Delete</button>
                </form>
                <a href="{{ route('suppliers.index') }}" class="px-6 py-3 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition font-semibold text-center"><i class="fas fa-arrow-left mr-2"></i>Back</a>
            </div>
        </div>
    </div>
</div>
@endsection
