@extends('layouts.app')
@section('title', $preOrder->pre_order_number)
@section('page-title', 'Pre-Order Details')

@section('content')
@php
    $statusColors=['pending'=>'bg-amber-100 text-amber-800 border-amber-200','completed'=>'bg-green-100 text-green-800 border-green-200','cancelled'=>'bg-red-100 text-red-800 border-red-200'];
    $paymentColors=['unpaid'=>'bg-red-100 text-red-800','partial'=>'bg-amber-100 text-amber-800','paid'=>'bg-green-100 text-green-800'];
@endphp
<div class="space-y-6">
    <div class="bg-white rounded-xl shadow-lg p-5 flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
        <div class="flex flex-wrap items-center gap-3"><a href="{{ route('preorders.index') }}" class="p-2 text-gray-600 hover:bg-gray-100 rounded-lg"><i class="fas fa-arrow-left"></i></a><div><h2 class="text-2xl font-bold font-mono text-gray-800">{{ $preOrder->pre_order_number }}</h2><p class="text-sm text-gray-500">{{ ucfirst($preOrder->document_type) }} · {{ $preOrder->pre_order_date->format('Y-m-d') }}@if($preOrder->sale) · Sale <a class="text-blue-600" href="{{ route('sales.show',$preOrder->sale) }}">{{ $preOrder->sale->sale_no }}</a>@endif</p></div><span class="px-3 py-1.5 rounded-full border text-sm font-semibold {{ $statusColors[$preOrder->status] }}">{{ ucfirst($preOrder->status) }}</span><span class="px-3 py-1.5 rounded-full text-sm font-semibold {{ $paymentColors[$preOrder->payment_status] }}">{{ $preOrder->payment_status==='partial'?'Partially Paid':ucfirst($preOrder->payment_status) }}</span></div>
        <div class="flex flex-wrap gap-2">
            @if($preOrder->status==='pending' && auth()->user()->hasPermission('preorder_edit'))<a href="{{ route('preorders.edit',$preOrder) }}" class="px-4 py-2 bg-indigo-600 text-white rounded-lg"><i class="fas fa-pen mr-1"></i>Edit</a>@endif
            @if(auth()->user()->hasPermission('preorder_print_quotation'))<a target="_blank" href="{{ route('preorders.quotation-pdf',$preOrder) }}" class="px-4 py-2 bg-purple-600 text-white rounded-lg"><i class="fas fa-file-pdf mr-1"></i>Quotation</a>@endif
            @if(auth()->user()->hasPermission('preorder_print_invoice'))<a target="_blank" href="{{ route('preorders.invoice-pdf',$preOrder) }}" class="px-4 py-2 bg-slate-700 text-white rounded-lg"><i class="fas fa-print mr-1"></i>Invoice</a>@endif
            @if(auth()->user()->hasPermission('preorder_payment_view'))<a href="#payment-history" class="px-4 py-2 bg-blue-50 text-blue-700 rounded-lg"><i class="fas fa-wallet mr-1"></i>Payment History</a>@endif
            <a href="#activity-history" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg"><i class="fas fa-history mr-1"></i>Activity History</a>
            @if($preOrder->status==='pending' && auth()->user()->hasPermission('preorder_complete'))<button onclick="openModal('complete-modal')" class="px-4 py-2 bg-green-600 text-white rounded-lg"><i class="fas fa-check mr-1"></i>Complete</button>@endif
            @if($preOrder->status==='pending' && auth()->user()->hasPermission('preorder_cancel'))<button onclick="openModal('cancel-modal')" class="px-4 py-2 bg-red-600 text-white rounded-lg"><i class="fas fa-ban mr-1"></i>Cancel</button>@endif
            @if(in_array($preOrder->status,['cancelled','completed']) && auth()->user()->hasPermission('preorder_reopen'))<button onclick="openModal('reopen-modal')" class="px-4 py-2 bg-amber-500 text-white rounded-lg"><i class="fas fa-rotate-left mr-1"></i>Reopen</button>@endif
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-white rounded-xl shadow p-5"><h3 class="font-semibold text-gray-800 border-b pb-3 mb-4"><i class="fas fa-clipboard-list text-blue-600 mr-2"></i>Pre-Order Information</h3><dl class="grid grid-cols-2 gap-y-3 text-sm"><dt class="text-gray-500">Document</dt><dd class="font-medium text-right">{{ ucfirst($preOrder->document_type) }}</dd><dt class="text-gray-500">Store</dt><dd class="font-medium text-right">{{ $preOrder->store?->name ?? 'General stock' }}</dd><dt class="text-gray-500">Expected Delivery</dt><dd class="font-medium text-right">{{ $preOrder->expected_delivery_date?->format('Y-m-d') ?? 'Not specified' }}</dd><dt class="text-gray-500">Created By</dt><dd class="font-medium text-right">{{ $preOrder->creator?->name }}</dd></dl>@if($preOrder->notes)<div class="mt-4 p-3 bg-gray-50 rounded-lg text-sm whitespace-pre-wrap"><strong>Notes:</strong><br>{{ $preOrder->notes }}</div>@endif</div>
                <div class="bg-white rounded-xl shadow p-5"><h3 class="font-semibold text-gray-800 border-b pb-3 mb-4"><i class="fas fa-user text-blue-600 mr-2"></i>Customer Information</h3><div class="font-bold text-lg">{{ $preOrder->customer->name }}</div><div class="space-y-2 mt-3 text-sm text-gray-600">@if($preOrder->customer->phone)<div><i class="fas fa-phone w-5"></i>{{ $preOrder->customer->phone }}</div>@endif @if($preOrder->customer->email)<div><i class="fas fa-envelope w-5"></i>{{ $preOrder->customer->email }}</div>@endif @if($preOrder->customer->address)<div><i class="fas fa-location-dot w-5"></i>{{ $preOrder->customer->address }}</div>@endif</div><a href="{{ route('preorders.index',['customer_id'=>$preOrder->customer_id]) }}" class="inline-block mt-4 text-sm text-blue-600 hover:underline">View all this customer's Pre-Orders</a></div>
            </div>

            <div class="bg-white rounded-xl shadow p-5"><h3 class="font-semibold text-gray-800 border-b pb-3 mb-4"><i class="fas fa-car-side text-blue-600 mr-2"></i>Vehicle Information</h3><div class="grid grid-cols-1 md:grid-cols-3 gap-5">@if($preOrder->vehicle_image_url)<div><img src="{{ $preOrder->vehicle_image_url }}" alt="{{ $preOrder->vehicle_name }}" class="w-full max-h-56 object-contain bg-gray-50 border rounded-xl"></div>@endif<div class="{{ $preOrder->vehicle_image_url ? 'md:col-span-2':'md:col-span-3' }}"><div class="text-xl font-bold">{{ $preOrder->vehicle_name }}</div>@if($preOrder->vehicle_description)<p class="mt-4 text-sm text-gray-600 whitespace-pre-wrap">{{ $preOrder->vehicle_description }}</p>@endif @if($preOrder->instructions)<div class="mt-4 p-3 bg-blue-50 text-blue-900 rounded-lg text-sm whitespace-pre-wrap"><strong>Instructions</strong><br>{{ $preOrder->instructions }}</div>@endif</div></div></div>

            <div class="bg-white rounded-xl shadow overflow-hidden"><div class="px-5 py-4 border-b flex justify-between"><h3 class="font-semibold text-gray-800"><i class="fas fa-gears text-blue-600 mr-2"></i>Products / Parts & Sync Status</h3><span class="text-sm text-gray-500">{{ $preOrder->items->count() }} item(s)</span></div><div class="overflow-x-auto"><table class="w-full min-w-[1150px] text-sm"><thead class="bg-gray-50 text-xs uppercase text-gray-600"><tr><th class="p-3 text-left">Part</th><th class="p-3 text-center">Stock / Sync</th><th class="p-3 text-right">Qty</th><th class="p-3 text-right">Quoted</th><th class="p-3 text-right">Current</th><th class="p-3 text-right">Final</th><th class="p-3 text-right">Discount</th><th class="p-3 text-right">Total</th><th class="p-3 text-center">Action</th></tr></thead><tbody class="divide-y">@foreach($preOrder->items as $item) @php $isSeparate = $preOrder->pdf_tax_display === 'separate'; $isExclHidden = $preOrder->pdf_tax_display === 'exclusive_hidden'; $tm = $isExclHidden ? (1 + ((float)$preOrder->custom_tax_rate / 100)) : 1; $dispQuoted = (float)$item->quoted_price * $tm; $dispCurrent = $item->current_selling_price !== null ? (float)$item->current_selling_price * $tm : null; $dispFinal = (float)$item->final_price * $tm; $dispDiscount = (float)$item->discount_amount * $tm; $dispLineTotal = $isSeparate ? ((float)$item->gross_amount - (float)$item->discount_amount) : (float)$item->line_total; @endphp <tr><td class="p-3"><div class="font-semibold">{{ $item->original_product_name }}</div>@if($item->description)<div class="text-xs text-gray-500 mt-1">{{ $item->description }}</div>@endif @if($item->product)<div class="text-xs text-green-600 mt-1">Linked: {{ $item->product->name }}{{ $item->product->sku ? ' · '.$item->product->sku : '' }}</div>@endif</td><td class="p-3 text-center"><div class="flex items-center justify-center gap-2">@if(!$item->product_id)<span class="px-2 py-1 bg-amber-100 text-amber-800 rounded-full text-xs whitespace-nowrap">⚠ Not Synced</span>@elseif($item->current_stock<=0)<span class="px-2 py-1 bg-amber-100 text-amber-800 rounded-full text-xs whitespace-nowrap">⚠ 0 Stock</span>@else<span class="px-2 py-1 bg-green-100 text-green-700 rounded-full text-xs whitespace-nowrap">✓ Synced · {{ $item->current_stock }}</span>@endif @if($preOrder->status==='pending' && auth()->user()->hasPermission('preorder_sync_product'))<button onclick="openSync({{ json_encode(['id'=>$item->id,'name'=>$item->original_product_name,'quoted'=>(float)$item->quoted_price]) }})" class="p-1 text-indigo-600 hover:bg-indigo-50 rounded inline-flex items-center" title="Sync Product"><i class="fas fa-link"></i></button>
@if(auth()->user()->hasPermission('products.create'))
<button type="button" onclick="openCreateProductForSync({{ json_encode(['id'=>$item->id,'name'=>$item->original_product_name]) }})" class="p-1 text-green-600 hover:bg-green-50 rounded inline-flex items-center ml-1" title="Create New Product & Sync"><i class="fas fa-plus"></i></button>
@endif @endif</div></td><td class="p-3 text-right">{{ $item->quantity }}</td><td class="p-3 text-right">{{ $currency }}{{ number_format($dispQuoted,2) }}</td><td class="p-3 text-right">{{ $dispCurrent !== null ? $currency.number_format($dispCurrent,2) : '—' }}</td><td class="p-3 text-right font-semibold {{ $dispQuoted !== $dispFinal ? 'text-blue-700':'' }}">{{ $currency }}{{ number_format($dispFinal,2) }}</td><td class="p-3 text-right">{{ $currency }}{{ number_format($dispDiscount,2) }}</td><td class="p-3 text-right font-semibold">{{ $currency }}{{ number_format($dispLineTotal,2) }}</td><td class="p-3"><div class="flex justify-center gap-1">@if($preOrder->status==='pending' && $item->product && auth()->user()->hasPermission('preorder_change_price'))<button onclick="openPrice({{ json_encode(['id'=>$item->id,'name'=>$item->original_product_name,'quoted'=>(float)$item->quoted_price,'current'=>$item->current_selling_price,'final'=>(float)$item->final_price]) }})" class="p-2 text-blue-600 hover:bg-blue-50 rounded" title="Change Price"><i class="fas fa-tags"></i></button>@endif</div></td></tr>@endforeach</tbody></table></div></div>
        </div>

        <div class="space-y-6">
            <div class="bg-white rounded-xl shadow p-5"><h3 class="font-semibold border-b pb-3 mb-4"><i class="fas fa-calculator text-blue-600 mr-2"></i>Totals</h3><div class="space-y-3 text-sm"><div class="flex justify-between"><span class="text-gray-500">Subtotal</span><strong>{{ $currency }}{{ number_format((float)$preOrder->subtotal * $tm,2) }}</strong></div><div class="flex justify-between"><span class="text-gray-500">Discount</span><strong class="text-red-600">{{ $currency }}{{ number_format((float)$preOrder->discount_amount * $tm,2) }}</strong></div>@if(!in_array($preOrder->pdf_tax_display, ['inclusive', 'exclusive_hidden']))<div class="flex justify-between"><span class="text-gray-500">Tax</span><strong>{{ $currency }}{{ number_format((float)$preOrder->tax_amount,2) }}</strong></div>@endif<div class="flex justify-between border-t pt-3 text-lg"><span class="font-bold">Grand Total</span><strong class="text-blue-700">{{ $currency }}{{ number_format((float)$preOrder->grand_total,2) }}</strong></div></div></div>
            <div class="bg-white rounded-xl shadow p-5"><div class="flex justify-between items-center border-b pb-3 mb-4"><h3 class="font-semibold"><i class="fas fa-wallet text-blue-600 mr-2"></i>Payment Summary</h3>@if($preOrder->status!=='cancelled' && (float)$preOrder->due_amount>0 && auth()->user()->hasPermission('preorder_payment_create'))<button onclick="openModal('payment-modal')" class="text-sm px-3 py-1.5 bg-blue-600 text-white rounded-lg">Collect</button>@endif</div><div class="space-y-3 text-sm"><div class="flex justify-between"><span>Total</span><strong>{{ $currency }}{{ number_format((float)$preOrder->grand_total,2) }}</strong></div><div class="flex justify-between text-green-700"><span>Paid</span><strong>{{ $currency }}{{ number_format((float)$preOrder->paid_amount,2) }}</strong></div><div class="flex justify-between text-amber-700"><span>Pending Cheques</span><strong>{{ $currency }}{{ number_format((float)$preOrder->held_cheque_amount,2) }}</strong></div><div class="flex justify-between text-red-700 border-t pt-3"><span>Remaining Due</span><strong>{{ $currency }}{{ number_format((float)$preOrder->due_amount,2) }}</strong></div></div></div>
            @if(auth()->user()->hasPermission('preorder_payment_view'))<div class="bg-white rounded-xl shadow overflow-hidden"><div class="p-4 border-b font-semibold"><i class="fas fa-clock-rotate-left text-blue-600 mr-2"></i>Payment Collection History</div><div class="divide-y max-h-96 overflow-y-auto">@php $allPayments = collect($preOrder->payments)->merge($preOrder->sale?->payments ?? [])->unique('id')->sortByDesc('created_at'); $allCheques = collect($preOrder->chequePayments)->merge($preOrder->sale?->chequePayments ?? [])->unique('id')->sortByDesc('created_at'); @endphp @forelse($allPayments as $payment)<div class="p-4 text-sm"><div class="flex justify-between"><strong>{{ $currency }}{{ number_format((float)$payment->amount,2) }}</strong><span class="capitalize">{{ str_replace('_',' ',$payment->payment_method) }}</span></div><div class="text-xs text-gray-500 mt-1">{{ $payment->payment_date?->format('Y-m-d') }} · {{ $payment->user?->name ?? 'System' }} @if($payment->reference_no) · {{ $payment->reference_no }}@endif</div>@if(auth()->user()->hasPermission('preorder_payment_edit') && !$allCheques->contains('payment_id',$payment->id))<form method="POST" action="{{ route('preorders.payments.destroy',[$preOrder,$payment]) }}" class="mt-2" onsubmit="return confirm('Remove this payment and reverse its accounting entry?')">@csrf @method('DELETE')<button class="text-xs text-red-600">Remove / Reverse</button></form>@endif</div>@empty<div class="p-5 text-sm text-center text-gray-500">No cleared payments yet.</div>@endforelse @foreach($allCheques as $cheque)<div class="p-4 text-sm"><div class="flex justify-between"><strong>{{ $currency }}{{ number_format((float)$cheque->amount,2) }}</strong><span class="px-2 py-0.5 rounded-full text-xs {{ $cheque->status==='passed'?'bg-green-100 text-green-700':($cheque->status==='returned'?'bg-red-100 text-red-700':'bg-amber-100 text-amber-700') }}">Cheque {{ ucfirst($cheque->status) }}</span></div><div class="text-xs text-gray-500 mt-1">#{{ $cheque->cheque_number }} · {{ $cheque->bank_name }} · {{ $cheque->cheque_date?->format('Y-m-d') }}</div></div>@endforeach</div></div>@endif
        </div>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden"><div class="p-4 border-b font-semibold"><i class="fas fa-list-check text-blue-600 mr-2"></i>Activity History</div><div class="divide-y">@forelse($preOrder->activities as $activity)<div class="p-4 flex gap-3"><div class="w-9 h-9 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center flex-none"><i class="fas fa-history"></i></div><div><div class="font-medium text-sm">{{ $activity->description }}</div><div class="text-xs text-gray-500 mt-1">{{ $activity->user?->name ?? 'System' }} · {{ $activity->created_at->format('Y-m-d H:i') }} · {{ str_replace('_',' ',ucfirst($activity->action)) }}</div></div></div>@empty<div class="p-6 text-center text-gray-500">No activity recorded.</div>@endforelse</div></div>
</div>

{{-- Cancel confirmation --}}
<div id="cancel-modal" class="modal fixed inset-0 hidden items-center justify-center bg-black/50 z-[80] p-4"><div class="bg-white rounded-xl shadow-2xl max-w-lg w-full"><form method="POST" action="{{ route('preorders.cancel',$preOrder) }}">@csrf<div class="p-6"><div class="text-red-600 text-4xl mb-3"><i class="fas fa-circle-exclamation"></i></div><h3 class="text-xl font-bold">Are you sure you want to cancel this Pre-Order?</h3><p class="text-gray-500 mt-2">This does not affect stock or sales. Authorized users can reopen it later.</p><label class="block text-sm font-medium mt-5 mb-2">Cancellation reason (optional)</label><textarea name="reason" class="w-full p-3 border rounded-lg" rows="3"></textarea></div><div class="p-4 bg-gray-50 flex justify-end gap-2"><button type="button" onclick="closeModal('cancel-modal')" class="px-4 py-2 bg-gray-200 rounded-lg">Keep Pre-Order</button><button class="px-4 py-2 bg-red-600 text-white rounded-lg">Confirm Cancel</button></div></form></div></div>

{{-- Reopen confirmation --}}
<div id="reopen-modal" class="modal fixed inset-0 hidden items-center justify-center bg-black/50 z-[80] p-4"><div class="bg-white rounded-xl shadow-2xl max-w-lg w-full"><form method="POST" action="{{ route('preorders.reopen',$preOrder) }}">@csrf<div class="p-6"><h3 class="text-xl font-bold">Reopen {{ ucfirst($preOrder->status) }} Pre-Order?</h3>@if($preOrder->status==='completed')<div class="mt-3 p-3 bg-amber-50 text-amber-900 rounded-lg text-sm"><strong>Safe reversal:</strong> the linked sale, stock deductions, payments, cheques, tax and accounting effects will be reversed atomically.</div>@endif<label class="block text-sm font-medium mt-5 mb-2">Reason (optional)</label><textarea name="reason" class="w-full p-3 border rounded-lg" rows="3"></textarea></div><div class="p-4 bg-gray-50 flex justify-end gap-2"><button type="button" onclick="closeModal('reopen-modal')" class="px-4 py-2 bg-gray-200 rounded-lg">Keep Current Status</button><button class="px-4 py-2 bg-amber-500 text-white rounded-lg">Confirm Reopen</button></div></form></div></div>

{{-- Sync modal --}}
<div id="sync-modal" class="modal fixed inset-0 hidden items-center justify-center bg-black/50 z-[80] p-4"><div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full"><form method="POST" id="sync-form">@csrf<div class="p-6"><h3 class="text-xl font-bold">Sync Product</h3><p class="text-sm text-gray-500 mt-1">Original description remains unchanged in history.</p><div class="mt-4 relative"><input id="sync-search" type="search" placeholder="Search product name or SKU..." class="w-full p-3 border rounded-lg"><div id="sync-results" class="absolute w-full bg-white border rounded-lg shadow-xl mt-1 max-h-60 overflow-y-auto hidden z-10"></div></div><input type="hidden" name="product_id" id="sync-product-id"><input type="hidden" name="product_price_id" id="sync-price-id"><div id="sync-selected" class="hidden mt-4 p-3 bg-green-50 text-green-900 rounded-lg"></div><div class="mt-4"><label class="block text-sm font-medium mb-2">Price decision</label><select name="price_action" id="sync-price-action" class="w-full p-2.5 border rounded-lg"><option value="keep">Keep Quoted Price</option><option value="current">Use Current Product Price</option><option value="custom">Enter New Price</option></select><input type="number" name="custom_price" id="sync-custom-price" step="0.01" min="0" placeholder="New price" class="hidden mt-2 w-full p-2.5 border rounded-lg"></div></div><div class="p-4 bg-gray-50 flex justify-end gap-2"><button type="button" onclick="closeModal('sync-modal')" class="px-4 py-2 bg-gray-200 rounded-lg">Cancel</button><button class="px-5 py-2 bg-indigo-600 text-white rounded-lg">Link Product</button></div></form></div></div>

{{-- Price modal --}}
<div id="price-modal" class="modal fixed inset-0 hidden items-center justify-center bg-black/50 z-[80] p-4"><div class="bg-white rounded-xl shadow-2xl max-w-lg w-full"><form method="POST" id="price-form">@csrf<div class="p-6"><h3 class="text-xl font-bold">Change Item Price</h3><div id="price-summary" class="mt-3 p-3 bg-gray-50 rounded-lg text-sm"></div><select name="price_action" id="price-action" class="mt-4 w-full p-3 border rounded-lg"><option value="keep">Keep Quoted Price</option><option value="current">Use Current Product Price</option><option value="custom">Enter New Price</option></select><input name="custom_price" id="custom-price" type="number" min="0" step="0.01" class="hidden mt-3 w-full p-3 border rounded-lg" placeholder="New price"></div><div class="p-4 bg-gray-50 flex justify-end gap-2"><button type="button" onclick="closeModal('price-modal')" class="px-4 py-2 bg-gray-200 rounded-lg">Cancel</button><button class="px-5 py-2 bg-blue-600 text-white rounded-lg">Apply & Log</button></div></form></div></div>

{{-- Complete modal --}}
<div id="complete-modal" class="modal fixed inset-0 hidden items-center justify-center bg-black/50 z-[80] p-4"><div class="bg-white rounded-xl shadow-2xl max-w-4xl w-full max-h-[92vh] overflow-y-auto"><form method="POST" action="{{ route('preorders.complete',$preOrder) }}">@csrf<div class="p-6"><h3 class="text-xl font-bold">Complete Pre-Order</h3><p class="text-sm text-gray-500">This creates a real sale and deducts linked stock once.</p><div class="grid grid-cols-1 md:grid-cols-3 gap-3 mt-4"><div class="p-3 bg-gray-50 rounded"><span class="text-xs text-gray-500">Customer</span><div class="font-semibold">{{ $preOrder->customer->name }}</div></div><div class="p-3 bg-gray-50 rounded"><span class="text-xs text-gray-500">Items</span><div class="font-semibold">{{ $preOrder->items->count() }}</div></div><div class="p-3 bg-blue-50 rounded"><span class="text-xs text-blue-600">Grand Total</span><div class="font-bold text-blue-800">{{ $currency }}{{ number_format((float)$preOrder->grand_total,2) }}</div></div></div><div class="mt-4 overflow-x-auto"><table class="w-full text-sm"><thead><tr class="bg-gray-50"><th class="p-2 text-left">Item</th><th class="p-2 text-right">Needed</th><th class="p-2 text-right">Available</th><th class="p-2">Ready</th></tr></thead><tbody>@foreach($preOrder->items as $item)<tr class="border-b"><td class="p-2">{{ $item->original_product_name }}</td><td class="p-2 text-right">{{ $item->quantity }}</td><td class="p-2 text-right">{{ $item->current_stock ?? '—' }}</td><td class="p-2 text-center">{!! $item->product_id && $item->current_stock >= $item->quantity ? '<span class="text-green-600">✓</span>' : '<span class="text-red-600">Needs attention</span>' !!}</td></tr>@endforeach</tbody></table></div><div class="mt-5 flex justify-between items-center"><h4 class="font-semibold">Payments (multiple methods supported)</h4><button type="button" onclick="addCompletionPayment()" class="px-3 py-1.5 bg-blue-100 text-blue-700 rounded-lg text-sm"><i class="fas fa-plus mr-1"></i>Add Method</button></div><div id="completion-payments" class="space-y-3 mt-3"></div><div class="mt-4 p-3 bg-gray-50 rounded-lg flex flex-wrap justify-between gap-3 text-sm"><span>Invoice: <strong>{{ $currency }}{{ number_format((float)$preOrder->grand_total,2) }}</strong></span><span>Allocated: <strong id="allocated-total">{{ $currency }}0.00</strong></span><span>Remaining Due: <strong id="completion-due" class="text-red-600">{{ $currency }}{{ number_format((float)$preOrder->grand_total,2) }}</strong></span></div></div><div class="p-4 bg-gray-50 flex justify-end gap-2"><button type="button" onclick="closeModal('complete-modal')" class="px-4 py-2 bg-gray-200 rounded-lg">Review Again</button><button class="px-5 py-2 bg-green-600 text-white rounded-lg">Confirm Complete</button></div></form></div></div>

{{-- Future payment modal --}}
<div id="payment-modal" class="modal fixed inset-0 hidden items-center justify-center bg-black/50 z-[80] p-4"><div class="bg-white rounded-xl shadow-2xl max-w-lg w-full"><form method="POST" action="{{ route('preorders.payments.store',$preOrder) }}">@csrf<div class="p-6 space-y-4"><h3 class="text-xl font-bold">Collect Payment</h3><div class="p-3 bg-red-50 text-red-800 rounded-lg">Remaining due: <strong>{{ $currency }}{{ number_format((float)$preOrder->due_amount,2) }}</strong></div><div><label class="text-sm font-medium">Amount *</label><input name="amount" type="number" min="0.01" max="{{ $preOrder->due_amount }}" step="0.01" required class="mt-1 w-full p-3 border rounded-lg"></div><div><label class="text-sm font-medium">Method *</label><select name="payment_method" id="future-method" class="mt-1 w-full p-3 border rounded-lg"><option value="cash">Cash</option><option value="bank_deposit">Bank Deposit</option><option value="bank_transfer">Bank Transfer</option><option value="card">Card</option><option value="mobile_payment">Mobile Payment</option><option value="cheque">Cheque</option></select></div><div><label class="text-sm font-medium">Date *</label><input name="payment_date" type="date" value="{{ now()->format('Y-m-d') }}" required class="mt-1 w-full p-3 border rounded-lg"></div><div><label class="text-sm font-medium">Reference</label><input name="reference_no" class="mt-1 w-full p-3 border rounded-lg"></div><div id="future-cheque" class="hidden grid grid-cols-2 gap-3"><input name="cheque_number" placeholder="Cheque number" class="p-3 border rounded-lg"><input name="cheque_date" type="date" class="p-3 border rounded-lg"><input name="bank_name" placeholder="Bank" class="p-3 border rounded-lg"><input name="account_name" placeholder="Account name" class="p-3 border rounded-lg"></div><textarea name="notes" placeholder="Notes" class="w-full p-3 border rounded-lg"></textarea></div><div class="p-4 bg-gray-50 flex justify-end gap-2"><button type="button" onclick="closeModal('payment-modal')" class="px-4 py-2 bg-gray-200 rounded-lg">Cancel</button><button class="px-5 py-2 bg-blue-600 text-white rounded-lg">Save Payment</button></div></form></div></div>

<!-- Product Create Modal -->
<div id="createProductSyncModal" class="fixed inset-0 hidden items-center justify-center z-50">
    <div class="absolute inset-0 bg-black opacity-50" onclick="closeModal('createProductSyncModal')"></div>
    <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl relative z-10 p-6 max-h-screen overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold">Add New Product</h3>
            <button onclick="closeModal('createProductSyncModal')" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
        </div>
        <form id="createProductSyncForm">
<input type="hidden" name="pre_order_item_id" id="cps_item_id">
            @if(isset($isPreOrder) && $isPreOrder)
                <input type="hidden" name="is_pre_order" value="1">
            @endif
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Product Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" required class="w-full border rounded px-3 py-2" />
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">SKU / Barcode</label>
                    <input type="text" name="sku" class="w-full border rounded px-3 py-2" placeholder="Auto-generated if left blank" />
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Unit <span class="text-red-500">*</span></label>
                    <select name="unit_id" class="w-full border rounded px-3 py-2" required>
                        <option value="">-- Select --</option>
                        @foreach(\App\Models\Unit::where('is_active', true)->get() as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}{{ $u->short_name ? ' (' . $u->short_name . ')' : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Category</label>
                    <select name="categories[]" class="w-full border rounded px-3 py-2">
                        <option value="">-- Select --</option>
                        @foreach(\App\Models\Category::where('is_active', true)->get() as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Brand</label>
                    <select name="brands[]" class="w-full border rounded px-3 py-2">
                        <option value="">-- Select --</option>
                        @foreach(\App\Models\Brand::where('is_active', true)->get() as $b)
                        <option value="{{ $b->id }}">{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Show Unit Prices For</label>
                    <p class="text-xs text-gray-500 mb-2">Uncheck units you do not want to display. Leave all checked to show prices for every unit.</p>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2 border rounded px-3 py-2 bg-gray-50">
                        @foreach(\App\Models\Unit::where('is_active', true)->get() as $u)
                            @php
                                $m = rtrim(rtrim(number_format((float)$u->base_unit_multiplier, 3, '.', ''), '0'), '.');
                            @endphp
                            <label class="flex items-center space-x-2 px-2 py-1 border rounded bg-white">
                                <input type="checkbox" name="visible_units[]" value="{{ $u->id }}" class="text-blue-600 rounded" checked>
                                <span class="text-xs text-gray-700">{{ $u->short_name ?: $u->name }} (x{{ $m }})</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Cost Price <span class="text-red-500">*</span></label>
                    <input type="number" step="0.01" name="cost_price" required class="w-full border rounded px-3 py-2" />
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Selling Price <span class="text-red-500">*</span></label>
                    <input type="number" step="0.01" name="selling_price" required class="w-full border rounded px-3 py-2" />
                </div>
                @if(($canUseSellingSecretCode ?? false) && (bool) \App\Models\Setting::get('barcode_enable_selling_secret_code', false))
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Secret Selling Code</label>
                    <input type="text" id="quick_secret_selling_code" class="w-full border rounded px-3 py-2" placeholder="Type secret code to fill selling price" />
                </div>
                @endif
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Secret Cost Code</label>
                    <input type="text" id="quick_secret_cost_code" class="w-full border rounded px-3 py-2" placeholder="Type secret code to fill cost price" />
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Profit Margin</label>
                    <div class="flex gap-2">
                        <input type="number" step="0.01" min="0" id="quick_profit_margin_percent" class="w-full border rounded px-3 py-2" placeholder="Margin %" />
                        <input type="number" step="0.01" min="0" id="quick_profit_margin_fixed" class="w-full border rounded px-3 py-2" placeholder="Fixed" />
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Stock Quantity <span class="text-red-500">*</span></label>
                    <input type="number" name="stock_quantity" value="0" min="0" required class="w-full border rounded px-3 py-2" />
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Alert Quantity <span class="text-red-500">*</span></label>
                    <input type="number" name="alert_quantity" value="1" min="0" required class="w-full border rounded px-3 py-2" />
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="2" class="w-full border rounded px-3 py-2" placeholder="Optional"></textarea>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Product Image</label>
                    <input type="file" name="image" accept="image/*" class="w-full border rounded px-3 py-2" />
                </div>
                
                <!-- Mark as Purchase -->
                <div class="md:col-span-2 mt-2 p-4 border rounded-lg bg-blue-50">
                    <label class="flex items-center space-x-2 font-semibold text-blue-900 cursor-pointer">
                        <input type="checkbox" id="mark_as_purchase" class="w-5 h-5 rounded text-blue-600">
                        <span>Mark this as a Purchase</span>
                    </label>
                    
                    <div id="purchase_details_container" class="hidden mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Supplier <span class="text-red-500">*</span></label>
                            <div class="flex gap-2">
                                <select id="purchase_supplier_id" class="w-full border rounded px-3 py-2">
                                    <option value="">Please Select</option>
                                    @foreach(\App\Models\Supplier::where('is_active', true)->get() as $sup)
                                        <option value="{{ $sup->id }}">{{ $sup->name }}</option>
                                    @endforeach
                                </select>
                                <button type="button" onclick="openSupplierModal()" class="px-3 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 whitespace-nowrap">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Payment Method</label>
                            <select id="purchase_payment_method" class="w-full border rounded px-3 py-2">
                                <option value="cash">Cash</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="card">Card</option>
                                <option value="mobile_payment">Mobile Payment</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Amount Paid (Due: <span id="purchase_due_display">0.00</span>)</label>
                            <input type="number" step="0.01" min="0" id="purchase_paid_amount" class="w-full border rounded px-3 py-2" value="0.00" />
                        </div>
                    </div>
                </div>
            </div>
            <div class="mt-6 flex items-center gap-2">
                <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                    <i class="fas fa-plus mr-2"></i>Create Product
                </button>
                <button type="button" onclick="closeModal('createProductSyncModal')" class="px-6 py-2 bg-gray-200 rounded hover:bg-gray-300">Cancel</button>
            </div>
        </form>
    </div>
</div>


<script>
const currency=@json($currency);
function openModal(id){const m=document.getElementById(id);m.classList.remove('hidden');m.classList.add('flex');if(id==='complete-modal')enhanceCompleteSummary()}
function closeModal(id){const m=document.getElementById(id);m.classList.add('hidden');m.classList.remove('flex')}
document.querySelector('.fa-clock-rotate-left')?.closest('.bg-white')?.setAttribute('id','payment-history');
document.querySelector('.fa-list-check')?.closest('.bg-white')?.setAttribute('id','activity-history');
function enhanceCompleteSummary(){if(document.getElementById('complete-extra-summary'))return;const target=document.querySelector('#complete-modal .overflow-x-auto');if(!target)return;const summary=document.createElement('div');summary.id='complete-extra-summary';summary.className='grid grid-cols-3 gap-3 mt-3';summary.innerHTML=`<div class="p-3 bg-gray-50 rounded"><span class="text-xs text-gray-500">Subtotal</span><div class="font-semibold">{{ $currency }}{{ number_format((float)$preOrder->subtotal,2) }}</div></div><div class="p-3 bg-red-50 rounded"><span class="text-xs text-red-600">Discount</span><div class="font-semibold text-red-800">{{ $currency }}{{ number_format((float)$preOrder->discount_amount,2) }}</div></div>@if($preOrder->pdf_tax_display !== 'inclusive')<div class="p-3 bg-gray-50 rounded"><span class="text-xs text-gray-500">Tax</span><div class="font-semibold">{{ $currency }}{{ number_format((float)$preOrder->tax_amount,2) }}</div></div>@endif`;target.before(summary)}
const syncBase=@json(url('preorders/'.$preOrder->id.'/items'));
function openSync(item){document.getElementById('sync-form').action=`${syncBase}/${item.id}/sync`;document.getElementById('sync-product-id').value='';document.getElementById('sync-price-id').value='';document.getElementById('sync-selected').classList.add('hidden');document.getElementById('sync-search').value='';openModal('sync-modal')}
let syncTimer;document.getElementById('sync-search')?.addEventListener('input',function(){clearTimeout(syncTimer);syncTimer=setTimeout(async()=>{const u=new URL(@json(route('preorders.search-products')),location.origin);u.searchParams.set('q',this.value);u.searchParams.set('store_id',@json($preOrder->store_id));const r=await fetch(u,{headers:{Accept:'application/json'}});if(!r.ok)return;const data=await r.json(),box=document.getElementById('sync-results');box.innerHTML='';data.forEach(p=>{const b=document.createElement('button');b.type='button';b.className='w-full p-3 text-left border-b hover:bg-blue-50';b.textContent=`${p.name} · SKU ${p.sku||'—'} · Stock ${p.stock} · ${currency}${Number(p.selling_price).toFixed(2)}`;b.onclick=()=>{document.getElementById('sync-product-id').value=p.id;document.getElementById('sync-price-id').value=p.product_price_id||'';const s=document.getElementById('sync-selected');s.textContent=`Selected: ${p.name} · Stock ${p.stock} · Current price ${currency}${Number(p.selling_price).toFixed(2)}`;s.classList.remove('hidden');box.classList.add('hidden')};box.appendChild(b)});box.classList.remove('hidden')},250)});
document.getElementById('sync-price-action')?.addEventListener('change',function(){document.getElementById('sync-custom-price').classList.toggle('hidden',this.value!=='custom')});
function openPrice(item){document.getElementById('price-form').action=`${syncBase}/${item.id}/price`;document.getElementById('price-summary').textContent=`${item.name} — Quoted ${currency}${item.quoted.toFixed(2)}, Current ${currency}${Number(item.current||0).toFixed(2)}, Final ${currency}${item.final.toFixed(2)}`;openModal('price-modal')}
document.getElementById('price-action')?.addEventListener('change',function(){document.getElementById('custom-price').classList.toggle('hidden',this.value!=='custom')});
const total={{ (float)$preOrder->grand_total }};let paymentIndex=0;
function addCompletionPayment(){const i=paymentIndex++,row=document.createElement('div');row.className='payment-row border rounded-lg p-3';row.innerHTML=`<div class="grid grid-cols-1 md:grid-cols-5 gap-2"><select name="payments[${i}][method]" class="method p-2 border rounded"><option value="cash">Cash</option><option value="bank_deposit">Bank Deposit</option><option value="bank_transfer">Bank Transfer</option><option value="card">Card</option><option value="mobile_payment">Mobile</option><option value="cheque">Cheque</option><option value="due">Due (no collection)</option></select><input name="payments[${i}][amount]" type="number" min="0" step="0.01" placeholder="Amount" class="amount p-2 border rounded"><input name="payments[${i}][date]" type="date" value="{{ now()->format('Y-m-d') }}" class="p-2 border rounded"><input name="payments[${i}][reference]" placeholder="Reference" class="p-2 border rounded"><button type="button" class="text-red-600" onclick="this.closest('.payment-row').remove();calcPayments()"><i class="fas fa-trash"></i></button></div><div class="cheque-fields hidden grid grid-cols-1 md:grid-cols-4 gap-2 mt-2"><input name="payments[${i}][cheque_number]" placeholder="Cheque number" class="p-2 border rounded"><input name="payments[${i}][cheque_date]" type="date" class="p-2 border rounded"><input name="payments[${i}][bank_name]" placeholder="Bank" class="p-2 border rounded"><input name="payments[${i}][account_name]" placeholder="Account name" class="p-2 border rounded"></div>`;document.getElementById('completion-payments').appendChild(row);row.querySelector('.method').onchange=function(){row.querySelector('.cheque-fields').classList.toggle('hidden',this.value!=='cheque');calcPayments()};row.querySelector('.amount').oninput=calcPayments;calcPayments()}
function calcPayments(){let allocated=0;document.querySelectorAll('.payment-row').forEach(r=>{if(r.querySelector('.method').value!=='due')allocated+=Number(r.querySelector('.amount').value||0)});document.getElementById('allocated-total').textContent=currency+allocated.toFixed(2);document.getElementById('completion-due').textContent=currency+Math.max(0,total-allocated).toFixed(2)}
document.getElementById('future-method')?.addEventListener('change',function(){document.getElementById('future-cheque').classList.toggle('hidden',this.value!=='cheque')});

// Setup Quick Product Constants
const CAN_USE_SELLING_SECRET_CODE = @json((bool)\App\Models\Setting::get('barcode_enable_selling_secret_code', false));
const QUICK_COST_CODE_MAP = @json((array)\App\Models\Setting::get('barcode_cost_code_map'));
const QUICK_SELLING_CODE_MAP = CAN_USE_SELLING_SECRET_CODE ? @json((array)\App\Models\Setting::get('barcode_selling_code_map')) : {};

if (!Object.keys(QUICK_COST_CODE_MAP || {}).length) {
    Object.assign(QUICK_COST_CODE_MAP, {
        '0': 'E', '1': 'M', '2': 'O', '3': 'D', '4': 'T',
        '5': 'P', '6': 'C', '7': 'S', '8': 'K', '9': 'L'
    });
}
if (CAN_USE_SELLING_SECRET_CODE && !Object.keys(QUICK_SELLING_CODE_MAP || {}).length) {
    Object.assign(QUICK_SELLING_CODE_MAP, QUICK_COST_CODE_MAP);
}

function encodeNumberToSecret(numStr, map) {
    let str = String(numStr).trim();
    if (!str) return '';
    if (str.includes('.')) {
        str = parseFloat(str).toFixed(2);
    }
    let encoded = '';
    for (let char of str) {
        if (char === '.') {
            encoded += '.';
        } else if (map[char]) {
            encoded += map[char];
        } else {
            encoded += char;
        }
    }
    return encoded;
}

function attachQuickProductListeners() {
    const form = document.getElementById('createProductSyncForm');
    if (!form) return;

    const costInput = form.querySelector('input[name="cost_price"]');
    const sellingInput = form.querySelector('input[name="selling_price"]');
    const percentInput = document.getElementById('quick_profit_margin_percent');
    const fixedInput = document.getElementById('quick_profit_margin_fixed');
    const secretCodeInput = document.getElementById('quick_secret_cost_code');
    const sellingSecretCodeInput = document.getElementById('quick_secret_selling_code');
    const qtyInput = form.querySelector('input[name="stock_quantity"]');
    const dueDisplay = document.getElementById('purchase_due_display');

    if (!costInput || !sellingInput || !percentInput || !fixedInput || !secretCodeInput) return;

    const updateDueAmount = () => {
        if (!dueDisplay) return;
        const c = parseFloat(costInput.value || 0);
        const q = parseFloat(qtyInput?.value || 0);
        dueDisplay.textContent = (c * q).toFixed(2);
    };

    const onCostInput = () => {
        const cost = parseFloat(costInput.value || 0);
        let selling = 0;
        
        if (percentInput.value) {
            selling = cost * (1 + parseFloat(percentInput.value) / 100);
        } else if (fixedInput.value) {
            selling = cost + parseFloat(fixedInput.value);
        }
        
        if (selling > 0) {
            sellingInput.value = selling.toFixed(2);
        }
        
        secretCodeInput.value = encodeNumberToSecret(costInput.value, QUICK_COST_CODE_MAP);
        if (sellingSecretCodeInput && sellingInput.value) {
            sellingSecretCodeInput.value = encodeNumberToSecret(sellingInput.value, QUICK_SELLING_CODE_MAP);
        }
        updateDueAmount();
    };

    const onSellingInput = () => {
        const cost = parseFloat(costInput.value || 0);
        const selling = parseFloat(sellingInput.value || 0);
        
        if (cost > 0 && selling > 0) {
            percentInput.value = (((selling - cost) / cost) * 100).toFixed(2);
            fixedInput.value = (selling - cost).toFixed(2);
        }
        
        if (sellingSecretCodeInput) {
            sellingSecretCodeInput.value = encodeNumberToSecret(sellingInput.value, QUICK_SELLING_CODE_MAP);
        }
    };

    const onMarginInput = (isPercent) => {
        return () => {
            const cost = parseFloat(costInput.value || 0);
            if (isPercent) {
                const percent = parseFloat(percentInput.value || 0);
                sellingInput.value = (cost * (1 + percent / 100)).toFixed(2);
                fixedInput.value = '';
            } else {
                const fixed = parseFloat(fixedInput.value || 0);
                sellingInput.value = (cost + fixed).toFixed(2);
                percentInput.value = '';
            }
            if (sellingSecretCodeInput) {
                sellingSecretCodeInput.value = encodeNumberToSecret(sellingInput.value, QUICK_SELLING_CODE_MAP);
            }
        };
    };

    costInput.addEventListener('input', onCostInput);
    sellingInput.addEventListener('input', onSellingInput);
    percentInput.addEventListener('input', onMarginInput(true));
    fixedInput.addEventListener('input', onMarginInput(false));
    if(qtyInput) qtyInput.addEventListener('input', updateDueAmount);

    secretCodeInput.value = encodeNumberToSecret(costInput.value || 0, QUICK_COST_CODE_MAP);
}

document.getElementById('mark_as_purchase')?.addEventListener('change', function() {
    const container = document.getElementById('purchase_details_container');
    if (this.checked) {
        container.classList.remove('hidden');
    } else {
        container.classList.add('hidden');
    }
});

function openCreateProductForSync(item) {
    document.getElementById('cps_item_id').value = item.id;
    const form = document.getElementById('createProductSyncForm');
    form.reset();
    form.querySelector('[name="name"]').value = item.name;
    document.getElementById('purchase_details_container')?.classList.add('hidden');
    document.getElementById('purchase_due_display').textContent = '0.00';
    attachQuickProductListeners();
    openModal('createProductSyncModal');
}

document.getElementById('createProductSyncForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const form = this;
    const btn = form.querySelector('button[type="submit"]');
    const itemId = document.getElementById('cps_item_id').value;
    
    // Validate
    if(!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    btn.disabled = true;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Creating...';
    
    try {
        const formData = new FormData(form);
        formData.append('is_pre_order', '1');

        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        
        // 1. Create the product
        const createRes = await fetch('/products', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: formData
        });
        
        const createData = await createRes.json();
        if (!createRes.ok) {
            let msg = createData.message || 'Error creating product';
            if (createData.errors) {
                msg += '\n' + Object.values(createData.errors).map(e => e.join(', ')).join('\n');
            }
            throw new Error(msg);
        }
        
        const productId = createData.product.id;
        
        // 2. Mark as purchase (if checked)
        const markAsPurchase = document.getElementById('mark_as_purchase').checked;
        if (markAsPurchase) {
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Logging Purchase...';
            
            const supplierId = document.getElementById('purchase_supplier_id').value;
            const paymentMethod = document.getElementById('purchase_payment_method').value;
            const paidAmount = document.getElementById('purchase_paid_amount').value;
            const qty = parseFloat(formData.get('stock_quantity') || 0);
            const cost = parseFloat(formData.get('cost_price') || 0);
            const sell = parseFloat(formData.get('selling_price') || 0);

            const purchasePayload = {
                is_pre_order: 1,
                supplier_id: supplierId,
                purchase_date: new Date().toISOString().split('T')[0],
                status: 'received',
                items: [{
                    product_id: productId,
                    quantity: qty,
                    unit_cost: cost,
                    selling_price: sell,
                    add_to_price_stock: true
                }],
                payments: [{
                    method: paymentMethod,
                    amount: paidAmount
                }]
            };
            
            const purchRes = await fetch('/purchases', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(purchasePayload)
            });
            
            if (!purchRes.ok) {
                const purchData = await purchRes.json();
                let msg = purchData.message || 'Error logging purchase';
                if (purchData.errors) {
                    msg += '\n' + Object.values(purchData.errors).map(e => e.join(', ')).join('\n');
                }
                throw new Error(msg + '\n(Note: Product was created successfully)');
            }
        }
        
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Syncing...';
        
        // 3. Sync the product
        const syncUrl = `/preorders/${ @json($preOrder->id) }/items/${itemId}/sync`;
        const syncRes = await fetch(syncUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                product_id: productId,
                price_action: 'current'
            })
        });
        
        if (!syncRes.ok) {
            const syncData = await syncRes.json();
            throw new Error(syncData.message || 'Error syncing product. Product created, but sync failed.');
        }
        
        window.location.reload();
        
    } catch(err) {
        alert(err.message);
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
});

// Supplier Modal Logic
function openSupplierModal() {
    document.getElementById('supplierModal').classList.remove('hidden');
    document.getElementById('supplierModal').classList.add('flex');
}
function closeSupplierModal() {
    document.getElementById('supplierModal').classList.add('hidden');
}

document.getElementById('quickSupplierForm').addEventListener('submit', async function(e){
    e.preventDefault();
    const data = new FormData(e.target);
    const btn = e.target.querySelector('button[type="submit"]');
    const originalText = btn.innerHTML;
    
    try {
        btn.disabled = true;
        btn.innerHTML = 'Creating...';
        const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const res = await fetch("{{ route('suppliers.store') }}", {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
            body: data
        });
        const json = await res.json();
        if (!json.success) {
            alert(json.message || 'Failed to create supplier');
            return;
        }
        const s = json.supplier;
        const sel = document.getElementById('purchase_supplier_id');
        const opt = document.createElement('option');
        opt.value = s.id;
        opt.textContent = s.name;
        sel.appendChild(opt);
        sel.value = s.id;
        
        closeSupplierModal();
        e.target.reset();
    } catch (err) {
        console.error(err);
        alert('Error creating supplier');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
});

</script>

<!-- Supplier Modal -->
<div id="supplierModal" style="z-index: 1000;" class="fixed inset-0 hidden items-center justify-center">
    <div class="absolute inset-0 bg-black opacity-50" onclick="closeSupplierModal()"></div>
    <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl relative z-[1001] p-6 max-h-screen overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold">Add New Supplier</h3>
            <button onclick="closeSupplierModal()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
        </div>
        <form id="quickSupplierForm">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" required class="w-full border rounded px-3 py-2" />
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Company Name</label>
                    <input type="text" name="company_name" class="w-full border rounded px-3 py-2" />
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Supplier TIN</label>
                    <input type="text" name="tin" inputmode="numeric" pattern="[0-9]{9,12}" maxlength="12" class="w-full border rounded px-3 py-2" />
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" class="w-full border rounded px-3 py-2" />
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Phone <span class="text-red-500">*</span></label>
                    <input type="text" name="phone" required class="w-full border rounded px-3 py-2" />
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Address</label>
                    <input type="text" name="address" class="w-full border rounded px-3 py-2" />
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">City</label>
                    <input type="text" name="city" class="w-full border rounded px-3 py-2" />
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Country</label>
                    <input type="text" name="country" class="w-full border rounded px-3 py-2" />
                </div>
            </div>
            <div class="mt-6 flex items-center gap-2">
                <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                    <i class="fas fa-plus mr-2"></i>Create Supplier
                </button>
                <button type="button" onclick="closeSupplierModal()" class="px-6 py-2 bg-gray-200 rounded hover:bg-gray-300">Cancel</button>
            </div>
        </form>
    </div>
</div>

@endsection
