@extends('layouts.app')

@section('title', 'Pre-Orders')
@section('page-title', 'Pre-Order Management')

@section('content')
@php $statusColors = ['pending'=>'bg-amber-100 text-amber-800','completed'=>'bg-green-100 text-green-800','cancelled'=>'bg-red-100 text-red-800']; $paymentColors = ['unpaid'=>'bg-red-100 text-red-800','partial'=>'bg-amber-100 text-amber-800','paid'=>'bg-green-100 text-green-800']; @endphp
<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div><h2 class="text-2xl font-bold text-gray-800">Pre-Orders</h2><p class="text-gray-500 text-sm">Quotations, product syncing, completion and payment tracking.</p></div>
        @if(auth()->user()->hasPermission('preorder_create'))<a href="{{ route('preorders.create') }}" class="px-5 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg shadow hover:from-blue-700 hover:to-blue-800 text-center"><i class="fas fa-plus mr-2"></i>Create Pre-Order</a>@endif
    </div>

    <div class="bg-white rounded-xl shadow-lg p-5">
        <form method="GET" action="{{ route('preorders.index') }}" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-6 gap-3">
            <div class="xl:col-span-2"><label class="block text-xs font-semibold text-gray-600 mb-1">Search</label><input name="q" value="{{ request('q') }}" placeholder="Pre-Order, customer, phone, vehicle, product, invoice..." class="w-full px-3 py-2.5 border rounded-lg"></div>
            <div><label class="block text-xs font-semibold text-gray-600 mb-1">Status</label><select name="status" class="w-full px-3 py-2.5 border rounded-lg"><option value="">All</option>@foreach(['pending','completed','cancelled'] as $s)<option value="{{ $s }}" @selected(request('status', $activeStatus)===$s)>{{ ucfirst($s) }}</option>@endforeach</select></div>
            <div><label class="block text-xs font-semibold text-gray-600 mb-1">Payment</label><select name="payment_status" class="w-full px-3 py-2.5 border rounded-lg"><option value="">All</option>@foreach(['unpaid','partial','paid'] as $s)<option value="{{ $s }}" @selected(request('payment_status')===$s)>{{ $s === 'partial' ? 'Partially Paid' : ucfirst($s) }}</option>@endforeach</select></div>
            <div><label class="block text-xs font-semibold text-gray-600 mb-1">Customer</label><select name="customer_id" class="w-full px-3 py-2.5 border rounded-lg"><option value="">All</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected((string)request('customer_id')===(string)$customer->id)>{{ $customer->name }}{{ $customer->phone ? ' — '.$customer->phone : '' }}</option>@endforeach</select></div>
            <div class="flex items-end gap-2"><button class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-lg"><i class="fas fa-search mr-1"></i>Filter</button><a href="{{ route('preorders.index') }}" class="px-3 py-2.5 bg-gray-200 rounded-lg" title="Clear"><i class="fas fa-rotate-left"></i></a></div>
            <div><label class="block text-xs font-semibold text-gray-600 mb-1">From</label><input type="date" name="date_from" value="{{ request('date_from') }}" class="w-full px-3 py-2.5 border rounded-lg"></div>
            <div><label class="block text-xs font-semibold text-gray-600 mb-1">To</label><input type="date" name="date_to" value="{{ request('date_to') }}" class="w-full px-3 py-2.5 border rounded-lg"></div>
            <div><label class="block text-xs font-semibold text-gray-600 mb-1">Delivery From</label><input type="date" name="delivery_from" value="{{ request('delivery_from') }}" class="w-full px-3 py-2.5 border rounded-lg"></div>
            <div><label class="block text-xs font-semibold text-gray-600 mb-1">Delivery To</label><input type="date" name="delivery_to" value="{{ request('delivery_to') }}" class="w-full px-3 py-2.5 border rounded-lg"></div>
            <div class="xl:col-span-2 flex items-end flex-wrap gap-2 text-xs">
                <a href="{{ route('preorders.index', ['date_from'=>today()->format('Y-m-d'),'date_to'=>today()->format('Y-m-d')]) }}" class="px-3 py-2 bg-gray-100 rounded-lg">Today</a>
                <a href="{{ route('preorders.index', ['date_from'=>today()->subDay()->format('Y-m-d'),'date_to'=>today()->subDay()->format('Y-m-d')]) }}" class="px-3 py-2 bg-gray-100 rounded-lg">Yesterday</a>
                <a href="{{ route('preorders.index', ['date_from'=>now()->startOfWeek()->format('Y-m-d'),'date_to'=>now()->endOfWeek()->format('Y-m-d')]) }}" class="px-3 py-2 bg-gray-100 rounded-lg">This Week</a>
                <a href="{{ route('preorders.index', ['date_from'=>now()->startOfMonth()->format('Y-m-d'),'date_to'=>now()->endOfMonth()->format('Y-m-d')]) }}" class="px-3 py-2 bg-gray-100 rounded-lg">This Month</a>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1550px] text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-600"><tr><th class="px-4 py-3 text-left">Pre-Order #</th><th class="px-4 py-3 text-left">Date</th><th class="px-4 py-3 text-left">Customer</th><th class="px-4 py-3 text-left">Phone</th><th class="px-4 py-3 text-left">Vehicle</th><th class="px-4 py-3 text-right">Total</th><th class="px-4 py-3 text-right">Paid</th><th class="px-4 py-3 text-right">Due</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Payment</th><th class="px-4 py-3 text-left">Delivery</th><th class="px-4 py-3 text-left">Created By</th><th class="px-4 py-3 text-right">Actions</th></tr></thead>
                <tbody class="divide-y">
                    @forelse($preOrders as $order)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono font-bold text-blue-700"><a href="{{ route('preorders.show', $order) }}">{{ $order->pre_order_number }}</a>@if($order->sale)<div class="text-xs text-gray-500">{{ $order->sale->sale_no }}</div>@endif</td>
                        <td class="px-4 py-3">{{ $order->pre_order_date->format('Y-m-d') }}</td><td class="px-4 py-3 font-medium">{{ $order->customer->name }}</td><td class="px-4 py-3">{{ $order->customer->phone ?: '—' }}</td><td class="px-4 py-3">{{ $order->vehicle_name }}</td>
                        <td class="px-4 py-3 text-right font-semibold">{{ $currency }}{{ number_format((float)$order->grand_total, 2) }}</td><td class="px-4 py-3 text-right">{{ $currency }}{{ number_format((float)$order->paid_amount, 2) }}</td><td class="px-4 py-3 text-right {{ (float)$order->due_amount > 0 ? 'text-red-600 font-semibold':'' }}">{{ $currency }}{{ number_format((float)$order->due_amount, 2) }}</td>
                        <td class="px-4 py-3 text-center"><span class="px-2 py-1 rounded-full text-xs font-semibold {{ $statusColors[$order->status] ?? 'bg-gray-100' }}">{{ ucfirst($order->status) }}</span></td><td class="px-4 py-3 text-center"><span class="px-2 py-1 rounded-full text-xs font-semibold {{ $paymentColors[$order->payment_status] ?? 'bg-gray-100' }}">{{ $order->payment_status === 'partial' ? 'Partially Paid' : ucfirst($order->payment_status) }}</span></td>
                        <td class="px-4 py-3">{{ $order->expected_delivery_date?->format('Y-m-d') ?? '—' }}</td><td class="px-4 py-3">{{ $order->creator?->name ?? '—' }}</td>
                        <td class="px-4 py-3"><div class="flex justify-end gap-1"><a href="{{ route('preorders.show', $order) }}" class="p-2 text-blue-600 hover:bg-blue-50 rounded" title="View"><i class="fas fa-eye"></i></a>@if($order->status==='pending' && auth()->user()->hasPermission('preorder_edit'))<a href="{{ route('preorders.edit', $order) }}" class="p-2 text-indigo-600 hover:bg-indigo-50 rounded" title="Edit"><i class="fas fa-pen"></i></a>@endif @if(auth()->user()->hasPermission('preorder_print_quotation'))<a href="{{ route('preorders.quotation-pdf', $order) }}" target="_blank" class="p-2 text-purple-600 hover:bg-purple-50 rounded" title="PDF"><i class="fas fa-file-pdf"></i></a>@endif</div></td>
                    </tr>
                    @empty<tr><td colspan="13" class="px-6 py-14 text-center text-gray-500"><i class="fas fa-clipboard-list text-4xl mb-3"></i><p>No Pre-Orders match these filters.</p></td></tr>@endforelse
                </tbody>
            </table>
        </div>
        @if($preOrders->hasPages())<div class="p-4 border-t">{{ $preOrders->links() }}</div>@endif
    </div>
</div>
@endsection
