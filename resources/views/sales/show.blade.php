@extends('layouts.app')

@section('title', 'Sale Details')
@section('page-title', 'Sale #'.$sale->sale_no)

@section('content')
@php
    $controls = \App\Services\DashboardVisibilityService::configForUser(auth()->user());
    $priceVisiblePct = (float) ($controls['price_visible_percentage'] ?? 100);
    $applyPct = function ($value, $pct) {
        $pct = max(0, min(100, (float) $pct));
        return (float) $value * ($pct / 100);
    };
    $maskMoneyValue = function ($value, $forceHide = false) use ($controls, $priceVisiblePct, $applyPct) {
        if ($forceHide || !empty($controls['hide_price_wise_data'])) {
            return null;
        }

        $raw = (float) $value;
        $masked = $applyPct(abs($raw), $priceVisiblePct);
        if ($priceVisiblePct < 100) {
            $masked = round($masked);
        }
        if ($priceVisiblePct < 100 && abs($raw) > 0 && $masked <= 0) {
            $masked = 1;
        }
        if ($raw < 0) {
            $masked *= -1;
        }

        return $masked;
    };
    $formatMoney = function ($value) use ($priceVisiblePct) {
        if ($value === null) {
            return '—';
        }

        return number_format((float) $value, $priceVisiblePct < 100 ? 0 : 2);
    };
    $maskQty = function ($value, $forceHide = false) use ($controls) {
        if ($forceHide || !empty($controls['hide_qty_wise_data'])) {
            return '—';
        }

        $qty = (float) $value;
        if ($qty > 0 && $qty < 1) {
            $qty = 1;
        }
        if ($qty < 0 && $qty > -1) {
            $qty = -1;
        }

        return number_format(round($qty), 0);
    };
    $heldChequeAmount = (float) ($sale->held_cheque_amount ?? 0);
    $hasChequeHold = $heldChequeAmount > 0;
    $displayPaymentStatus = $hasChequeHold ? 'Hold' : ucfirst((string) $sale->payment_status);
    $displayPaymentStatusClass = $hasChequeHold
        ? 'bg-indigo-100 text-indigo-700'
        : ($sale->payment_status === 'paid' ? 'bg-green-100 text-green-700' : ($sale->payment_status === 'partial' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700'));
@endphp
<div class="space-y-6">
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4 mb-4">
            <div>
                <div class="text-sm text-gray-500">Sale No</div>
                <div class="text-lg font-semibold">{{ $sale->sale_no }}</div>
            </div>
            <div>
                <div class="text-sm text-gray-500">Date</div>
                <div class="text-lg">{{ $sale->sale_date?->format('Y-m-d H:i') ?? $sale->created_at->format('Y-m-d H:i') }}</div>
            </div>
            <div>
                <div class="text-sm text-gray-500">Customer</div>
                <div class="text-lg">{{ $sale->customer?->name ?? 'Walk-in Customer' }}</div>
            </div>
            <div>
                <a href="{{ route('sales.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">Back</a>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-600 border-b">
                        <th class="py-2 pr-4">Item</th>
                        <th class="py-2 pr-4 text-right">Qty</th>
                        <th class="py-2 pr-4 text-right">Unit Price</th>
                        <th class="py-2 pr-4 text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(($netItems ?? $sale->items) as $it)
                        @php
                            $lineQtyRaw = (float) ($it->net_quantity ?? $it->quantity);
                            $lineUnitRaw = (float) ($it->display_unit_price ?? $it->unit_price);
                            $lineUnitMasked = $maskMoneyValue($lineUnitRaw, !empty($controls['hide_actual_stock_price']) || !empty($controls['hide_invoice_details']));
                            $lineTotalMasked = $lineUnitMasked === null ? null : ($lineUnitMasked * $lineQtyRaw);
                        @endphp
                        <tr class="border-b">
                            <td class="py-2 pr-4">
                                <div>{{ $it->product->name ?? ('#'.$it->product_id) }}</div>
                                @if(((float) ($it->line_discount_amount ?? 0)) > 0)
                                    @php
                                        $lineDiscountMasked = $maskMoneyValue((float) ($it->line_discount_amount ?? 0), !empty($controls['hide_invoice_details']));
                                    @endphp
                                    <div class="text-xs text-red-600 font-semibold">Discount: -{{ trim($currency) }} {{ $formatMoney($lineDiscountMasked) }}</div>
                                @endif
                            </td>
                            <td class="py-2 pr-4 text-right">{{ $maskQty($it->net_quantity ?? $it->quantity) }}</td>
                            <td class="py-2 pr-4 text-right">{{ trim($currency) }} {{ $formatMoney($lineUnitMasked) }}</td>
                            <td class="py-2 pr-4 text-right">{{ trim($currency) }} {{ $formatMoney($lineTotalMasked) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @php
            $exchangeCredit = (float) ($exchangeReturnAmount ?? 0);
            $netAfterExchange = round(((float) $sale->total_amount) - $exchangeCredit, 2);
        @endphp

        @if((!empty($exchangeReturnItems) && $exchangeReturnItems->count() > 0) || (!empty($returnItems) && $returnItems->count() > 0))
            <div class="mt-6 space-y-3">
                @if(!empty($exchangeReturnItems) && $exchangeReturnItems->count() > 0)
                    <div class="text-sm font-semibold text-gray-800">Returned Items (Exchange Credit)</div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-gray-600 border-b">
                                    <th class="py-2 pr-4">Item</th>
                                    <th class="py-2 pr-4 text-right">Qty</th>
                                    <th class="py-2 pr-4 text-right">Unit Price</th>
                                    <th class="py-2 pr-4 text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($exchangeReturnItems as $rit)
                                    <tr class="border-b bg-red-50/30">
                                        <td class="py-2 pr-4">{{ $rit->product?->name ?? ('#'.$rit->product_id) }}</td>
                                        <td class="py-2 pr-4 text-right">{{ $maskQty(-1 * (int) $rit->quantity) }}</td>
                                        <td class="py-2 pr-4 text-right">{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, (float) $rit->unit_price, $currency) }}</td>
                                        <td class="py-2 pr-4 text-right">{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, -1 * (float) $rit->total, $currency) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if(!empty($returnItems) && $returnItems->count() > 0)
                    <div class="text-sm font-semibold text-gray-800">Returned From This Invoice</div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-gray-600 border-b">
                                    <th class="py-2 pr-4">Item</th>
                                    <th class="py-2 pr-4 text-right">Qty</th>
                                    <th class="py-2 pr-4 text-right">Unit Price</th>
                                    <th class="py-2 pr-4 text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($returnItems as $rit)
                                    <tr class="border-b bg-red-50/30">
                                        <td class="py-2 pr-4">{{ $rit->product?->name ?? ('#'.$rit->product_id) }}</td>
                                        <td class="py-2 pr-4 text-right">{{ $maskQty(-1 * (int) $rit->quantity) }}</td>
                                        <td class="py-2 pr-4 text-right">{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, (float) $rit->unit_price, $currency) }}</td>
                                        <td class="py-2 pr-4 text-right">{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, -1 * (float) $rit->total, $currency) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endif

        <div class="grid md:grid-cols-2 gap-6 mt-6">
            <div class="space-y-2">
                <div class="text-sm text-gray-500">Cashier</div>
                <div class="text-lg">{{ $sale->user?->name ?? '-' }}</div>
                @if(!empty($sale->notes))
                    <div class="text-sm text-gray-500">Notes</div>
                    <div class="text-gray-800">{{ $sale->notes }}</div>
                @endif
                <div class="text-sm text-gray-500">Payment Method</div>
                <div>
                    <span class="px-2 py-1 text-xs font-semibold rounded
                        @switch($sale->payment_method)
                            @case('cash') bg-gray-200 text-gray-800 @break
                            @case('card') bg-blue-100 text-blue-700 @break
                            @case('bank_transfer') bg-purple-100 text-purple-700 @break
                            @case('mobile_payment') bg-teal-100 text-teal-700 @break
                            @case('cheque') bg-indigo-100 text-indigo-700 @break
                            @default bg-gray-100 text-gray-600
                        @endswitch
                    ">
                        {{ ((string) ($sale->payment_method ?? '')) === 'cheque' ? 'Cheque Payment' : str_replace('_',' ', ucfirst((string) ($sale->payment_method ?? ''))) }}
                    </span>
                </div>
                @if($sale->payments?->count())
                    <div class="mt-4 rounded-lg border border-emerald-100 bg-emerald-50/60 p-4">
                        <div class="mb-3 font-semibold text-gray-800">
                            <i class="fas fa-credit-card text-emerald-600 mr-2"></i>Payment Details
                        </div>
                        <div class="space-y-2">
                            @foreach($sale->payments as $payment)
                                <div class="flex items-center justify-between gap-3 rounded-lg bg-white p-3 text-sm">
                                    <div>
                                        <div class="font-semibold text-gray-800">{{ str_replace('_', ' ', ucfirst((string) $payment->payment_method)) }}</div>
                                        <div class="text-gray-500">{{ $payment->payment_date?->format('Y-m-d') ?? '-' }}</div>
                                    </div>
                                    <div class="font-semibold text-emerald-700">{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $payment->amount, $currency) }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
                @if($sale->chequePayments?->count())
                    <div class="mt-4 rounded-lg border border-indigo-100 bg-indigo-50/60 p-4">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <div class="font-semibold text-gray-800">
                                <i class="fas fa-money-check-alt text-indigo-600 mr-2"></i>Cheque Details
                            </div>
                            @if($hasChequeHold)
                                <span class="rounded-full bg-indigo-100 px-3 py-1 text-xs font-semibold text-indigo-700">Held</span>
                            @endif
                        </div>
                        <div class="space-y-3">
                            @foreach($sale->chequePayments as $cheque)
                                <div class="grid gap-2 rounded-lg bg-white p-3 text-sm md:grid-cols-2">
                                    <div><span class="text-gray-500">Pass Date:</span> <span class="font-semibold">{{ $cheque->cheque_date?->format('Y-m-d') ?? '-' }}</span></div>
                                    <div><span class="text-gray-500">Cheque No:</span> <span class="font-semibold">{{ $cheque->cheque_number }}</span></div>
                                    <div><span class="text-gray-500">Bank:</span> <span class="font-semibold">{{ $cheque->bank_name ?: '-' }}</span></div>
                                    <div><span class="text-gray-500">Name:</span> <span class="font-semibold">{{ $cheque->account_name ?: '-' }}</span></div>
                                    <div><span class="text-gray-500">Amount:</span> <span class="font-semibold">{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $cheque->amount, $currency) }}</span></div>
                                    <div><span class="text-gray-500">Status:</span> <span class="font-semibold">{{ $cheque->status === 'pending' ? 'Hold until passed' : ucfirst($cheque->status) }}</span></div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
                <div class="pt-3">
                    <a href="{{ route('sales.print', $sale->id) }}" target="_blank" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                        <i class="fas fa-receipt mr-2"></i> Re-print (Thermal)
                    </a>
                </div>
                <div class="pt-3">
                    <button onclick="document.getElementById('return-modal').classList.remove('hidden');" class="inline-flex items-center px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                        <i class="fas fa-undo mr-2"></i> Return Items
                    </button>
                </div>
            </div>
            <div class="space-y-2 bg-gray-50 rounded-lg p-4">
                    <div class="flex justify-between"><span>Subtotal</span><span>{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, (float) ($displaySubtotal ?? $sale->subtotal), $currency) }}</span></div>
                    @if(((float) ($cartDiscountAmount ?? 0)) > 0)
                        <div class="flex justify-between"><span>Discount</span><span>{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, (float) $cartDiscountAmount, $currency) }}</span></div>
                    @endif
                    <div class="flex justify-between"><span>Tax</span><span>{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $sale->tax, $currency) }}</span></div>
                <div class="flex justify-between font-semibold border-t pt-2"><span>Total</span><span>{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $sale->total_amount, $currency) }}</span></div>

                @if($exchangeCredit > 0)
                    @php
                        $customerPay = max(0.0, $netAfterExchange);
                        $refundDue = max(0.0, -1 * $netAfterExchange);
                    @endphp
                    <div class="flex justify-between text-red-700"><span>Return Credit</span><span>{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, -1 * $exchangeCredit, $currency) }}</span></div>
                    @if($refundDue > 0)
                        <div class="flex justify-between font-semibold"><span>Refund Due</span><span>{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $refundDue, $currency) }}</span></div>
                    @elseif($customerPay > 0)
                        <div class="flex justify-between font-semibold"><span>Customer Pay</span><span>{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $customerPay, $currency) }}</span></div>
                    @endif
                @endif
                <div class="flex justify-between text-green-700"><span>Paid</span><span>{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $sale->paid_amount, $currency) }}</span></div>
                @if($heldChequeAmount > 0)
                    <div class="flex justify-between text-indigo-700"><span>Cheque Held</span><span>{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $heldChequeAmount, $currency) }}</span></div>
                @endif
                <div class="flex justify-between text-amber-700"><span>Due</span><span>{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $sale->due_amount, $currency) }}</span></div>
                <div class="flex justify-between"><span>Status</span>
                    <span class="px-2 py-1 rounded text-xs font-semibold {{ $displayPaymentStatusClass }}">
                        {{ $displayPaymentStatus }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Return Modal -->
<div id="return-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50">
    <div class="bg-white w-full max-w-xl rounded-xl shadow-lg p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold">Process Return</h3>
            <button class="text-gray-500 hover:text-gray-700" onclick="document.getElementById('return-modal').classList.add('hidden');">&times;</button>
        </div>
        <form method="POST" action="{{ route('sales.return', $sale->id) }}" class="space-y-4">
            @csrf
            <div class="space-y-2 max-h-64 overflow-y-auto">
                @foreach($sale->items as $it)
                    @php
                        $alreadyReturned = (int) (($returnedQtyByItem[$it->id] ?? 0));
                        $remainingQty = max(0, (int)$it->quantity - $alreadyReturned);
                    @endphp
                    <div class="border rounded-lg p-3 flex items-center justify-between">
                        <div>
                            <div class="font-semibold text-sm">{{ $it->product->name ?? ('#'.$it->product_id) }}</div>
                            <div class="text-xs text-gray-500">Sold: {{ $maskQty($it->quantity) }}, Returned: {{ $maskQty($alreadyReturned) }}, Remaining: {{ $maskQty($remainingQty) }} @ {{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $it->unit_price, $currency) }}</div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <input type="hidden" name="items[{{ $it->id }}][sale_item_id]" value="{{ $it->id }}">
                            <input type="number" name="items[{{ $it->id }}][quantity]" value="0" min="0" max="{{ $remainingQty }}" class="w-20 px-2 py-1 border rounded" {{ $remainingQty <= 0 ? 'disabled' : '' }}>
                        </div>
                    </div>
                @endforeach
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Notes</label>
                <textarea name="notes" rows="3" class="w-full px-3 py-2 border rounded-lg"></textarea>
            </div>
            <div class="flex justify-end space-x-2 pt-2">
                <button type="button" onclick="document.getElementById('return-modal').classList.add('hidden');" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg">Submit Return</button>
            </div>
        </form>
    </div>
</div>
@endsection
