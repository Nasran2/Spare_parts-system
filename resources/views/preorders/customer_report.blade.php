@extends('layouts.app')
@section('title', 'Customer Pre-Order Report')
@section('page-title', 'Customer Pre-Order Report')
@section('content')
<div class="space-y-6">
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach([['Total Customers',$summary['total_customers'],'blue'],['Total Amount',$summary['total_amount'],'amber'],['Paid Amount',$summary['total_paid'],'green'],['Due Amount',$summary['total_due'],'red']] as $card)
        <div class="bg-white rounded-xl shadow p-4 border-t-4 border-{{ $card[2] }}-500"><div class="text-xs uppercase text-gray-500">{{ $card[0] }}</div><div class="text-xl font-bold mt-2">{{ in_array($card[0], ['Total Customers']) ? number_format($card[1]) : 'Rs '.number_format((float)$card[1],2) }}</div></div>
        @endforeach
    </div>
    <div class="bg-white rounded-xl shadow-lg p-5">
        <form class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <input type="date" name="date_from" value="{{ request('date_from') }}" class="px-3 py-2.5 border rounded-lg" title="From Date">
            <input type="date" name="date_to" value="{{ request('date_to') }}" class="px-3 py-2.5 border rounded-lg" title="To Date">
            <select name="customer_id" class="px-3 py-2.5 border rounded-lg">
                <option value="">All Customers</option>
                @foreach($allCustomersWithPreorders as $c)
                    <option value="{{ $c->id }}" @selected(request('customer_id') == $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <button type="submit" class="w-full bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700">Apply</button>
                <button type="submit" name="export" value="pdf" class="w-full bg-red-600 text-white rounded-lg text-sm font-semibold hover:bg-red-700 flex items-center justify-center gap-1" title="Export PDF">
                    <i class="fas fa-file-pdf"></i> PDF
                </button>
            </div>
        </form>
    </div>
    <div class="bg-white rounded-xl shadow overflow-x-auto"><table class="w-full min-w-[900px] text-sm"><thead class="bg-gray-50"><tr><th class="p-3 text-left">Customer Name</th><th class="p-3 text-center">Total Pre-Orders</th><th class="p-3 text-right">Total Amount</th><th class="p-3 text-right">Paid Amount</th><th class="p-3 text-right">Due Amount</th><th class="p-3 text-center">Actions</th></tr></thead><tbody class="divide-y">@forelse($customers as $customer)<tr><td class="p-3"><a class="text-blue-600 font-semibold" href="{{ route('customers.show',$customer) }}">{{ $customer->name }}</a></td><td class="p-3 text-center">{{ number_format($customer->total_preorders) }}</td><td class="p-3 text-right">{{ number_format((float)$customer->total_amount,2) }}</td><td class="p-3 text-right">{{ number_format((float)$customer->paid_amount,2) }}</td><td class="p-3 text-right text-red-600 font-semibold">{{ number_format((float)$customer->due_amount,2) }}</td><td class="p-3 text-center"><a href="{{ route('customers.show', $customer) }}" class="text-gray-500 hover:text-blue-600"><i class="fas fa-eye"></i> View</a></td></tr>@empty<tr><td colspan="6" class="p-10 text-center text-gray-500">No customers with pre-orders found.</td></tr>@endforelse</tbody></table></div>
</div>
@endsection
