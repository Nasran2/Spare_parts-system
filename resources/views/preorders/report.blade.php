@extends('layouts.app')
@section('title', 'Pre-Order Report')
@section('page-title', 'Pre-Order Report')
@section('content')
<div class="space-y-6">
    <div class="grid grid-cols-2 lg:grid-cols-6 gap-4">
        @foreach([['Total Pre-Orders',$summary['total'],'blue'],['Pending Amount',$summary['pending'],'amber'],['Completed Sales',$summary['completed'],'green'],['Cancelled',$summary['cancelled'],'red'],['Paid Amount',$summary['paid'],'emerald'],['Due Amount',$summary['due'],'orange']] as $card)
        <div class="bg-white rounded-xl shadow p-4 border-t-4 border-{{ $card[2] }}-500"><div class="text-xs uppercase text-gray-500">{{ $card[0] }}</div><div class="text-xl font-bold mt-2">{{ in_array($card[0], ['Total Pre-Orders','Cancelled']) ? number_format($card[1]) : 'Rs '.number_format((float)$card[1],2) }}</div></div>
        @endforeach
    </div>
    <div class="bg-white rounded-xl shadow-lg p-5">
        <form class="grid grid-cols-1 md:grid-cols-5 gap-3"><input type="date" name="date_from" value="{{ request('date_from') }}" class="px-3 py-2.5 border rounded-lg"><input type="date" name="date_to" value="{{ request('date_to') }}" class="px-3 py-2.5 border rounded-lg"><select name="status" class="px-3 py-2.5 border rounded-lg"><option value="">All statuses</option>@foreach(['pending','completed','cancelled'] as $s)<option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst($s) }}</option>@endforeach</select><select name="payment_status" class="px-3 py-2.5 border rounded-lg"><option value="">All payment statuses</option>@foreach(['unpaid','partial','paid'] as $s)<option value="{{ $s }}" @selected(request('payment_status')===$s)>{{ ucfirst($s) }}</option>@endforeach</select><button class="bg-blue-600 text-white rounded-lg">Apply Filters</button></form>
    </div>
    <div class="bg-white rounded-xl shadow overflow-x-auto"><table class="w-full min-w-[900px] text-sm"><thead class="bg-gray-50"><tr><th class="p-3 text-left">Pre-Order</th><th class="p-3 text-left">Date</th><th class="p-3 text-left">Customer</th><th class="p-3">Status</th><th class="p-3 text-right">Total</th><th class="p-3 text-right">Paid</th><th class="p-3 text-right">Due</th></tr></thead><tbody class="divide-y">@forelse($orders as $order)<tr><td class="p-3"><a class="text-blue-600 font-mono" href="{{ route('preorders.show',$order) }}">{{ $order->pre_order_number }}</a></td><td class="p-3">{{ $order->pre_order_date->format('Y-m-d') }}</td><td class="p-3">{{ $order->customer?->name }}</td><td class="p-3 text-center">{{ ucfirst($order->status) }}</td><td class="p-3 text-right">{{ number_format((float)$order->grand_total,2) }}</td><td class="p-3 text-right">{{ number_format((float)$order->paid_amount,2) }}</td><td class="p-3 text-right">{{ number_format((float)$order->due_amount,2) }}</td></tr>@empty<tr><td colspan="7" class="p-10 text-center text-gray-500">No records.</td></tr>@endforelse</tbody></table></div>
</div>
@endsection
