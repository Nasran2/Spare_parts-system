@extends('layouts.app')

@section('title', 'Sale Details')
@section('page-title', 'Sale #'.$sale->sale_no)

@section('content')
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
                    @foreach($sale->items as $it)
                        <tr class="border-b">
                            <td class="py-2 pr-4">{{ $it->product->name ?? ('#'.$it->product_id) }}</td>
                            <td class="py-2 pr-4 text-right">{{ $it->quantity }}</td>
                                <td class="py-2 pr-4 text-right">{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $it->unit_price, $currency) }}</td>
                                <td class="py-2 pr-4 text-right">{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $it->total, $currency) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="grid md:grid-cols-2 gap-6 mt-6">
            <div class="space-y-2">
                <div class="text-sm text-gray-500">Cashier</div>
                <div class="text-lg">{{ $sale->user?->name ?? '-' }}</div>
                @if(!empty($sale->notes))
                    <div class="text-sm text-gray-500">Notes</div>
                    <div class="text-gray-800">{{ $sale->notes }}</div>
                @endif
                <div class="text-sm text-gray-500">Payment Method</div>
                @php $method = $sale->payment_method; @endphp
                <div>
                    <span class="px-2 py-1 text-xs font-semibold rounded
                        @switch($method)
                            @case('cash') bg-gray-200 text-gray-800 @break
                            @case('card') bg-blue-100 text-blue-700 @break
                            @case('bank_transfer') bg-purple-100 text-purple-700 @break
                            @case('mobile_payment') bg-teal-100 text-teal-700 @break
                            @default bg-gray-100 text-gray-600
                        @endswitch
                    ">
                        {{ str_replace('_',' ', ucfirst($method)) }}
                    </span>
                </div>
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
                    <div class="flex justify-between"><span>Subtotal</span><span>{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $sale->subtotal, $currency) }}</span></div>
                    <div class="flex justify-between"><span>Discount</span><span>{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $sale->discount, $currency) }}</span></div>
                    <div class="flex justify-between"><span>Tax</span><span>{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $sale->tax, $currency) }}</span></div>
                <div class="flex justify-between font-semibold border-t pt-2"><span>Total</span><span>{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $sale->total_amount, $currency) }}</span></div>
                <div class="flex justify-between text-green-700"><span>Paid</span><span>{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $sale->paid_amount, $currency) }}</span></div>
                <div class="flex justify-between text-amber-700"><span>Due</span><span>{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $sale->due_amount, $currency) }}</span></div>
                <div class="flex justify-between"><span>Status</span>
                    <span class="px-2 py-1 rounded text-xs font-semibold {{ $sale->payment_status === 'paid' ? 'bg-green-100 text-green-700' : ($sale->payment_status === 'partial' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700') }}">
                        {{ ucfirst($sale->payment_status) }}
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
                    <div class="border rounded-lg p-3 flex items-center justify-between">
                        <div>
                            <div class="font-semibold text-sm">{{ $it->product->name ?? ('#'.$it->product_id) }}</div>
                            <div class="text-xs text-gray-500">Sold: {{ $it->quantity }} @ {{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $it->unit_price, $currency) }}</div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <input type="hidden" name="items[{{ $it->id }}][sale_item_id]" value="{{ $it->id }}">
                            <input type="number" name="items[{{ $it->id }}][quantity]" value="0" min="0" max="{{ $it->quantity }}" class="w-20 px-2 py-1 border rounded">
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
