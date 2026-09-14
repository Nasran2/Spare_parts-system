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
    <div class="bg-white rounded-xl shadow overflow-x-auto">
        <table class="w-full min-w-[900px] text-sm">
            <thead class="bg-gray-50">
                <tr><th class="p-3 text-left">Customer Name</th><th class="p-3 text-center">Total Pre-Orders</th><th class="p-3 text-right">Total Amount</th><th class="p-3 text-right">Paid Amount</th><th class="p-3 text-right">Due Amount</th><th class="p-3 text-center">Actions</th></tr>
            </thead>
            <tbody class="divide-y">
                @forelse($customers as $customer)
                <tr>
                    <td class="p-3"><a class="text-blue-600 font-semibold cursor-pointer hover:underline" onclick="openModal('{{ addslashes($customer->name) }}', 'customer-preorders-{{ $customer->id }}')">{{ $customer->name }}</a></td>
                    <td class="p-3 text-center">{{ number_format($customer->total_preorders) }}</td>
                    <td class="p-3 text-right">{{ number_format((float)$customer->total_amount,2) }}</td>
                    <td class="p-3 text-right">{{ number_format((float)$customer->paid_amount,2) }}</td>
                    <td class="p-3 text-right text-red-600 font-semibold">{{ number_format((float)$customer->preorder_due_amount,2) }}</td>
                    <td class="p-3 text-center">
                        <a href="#" onclick="openModal('{{ addslashes($customer->name) }}', 'customer-preorders-{{ $customer->id }}'); return false;" class="text-gray-500 hover:text-blue-600"><i class="fas fa-eye"></i> View</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="p-10 text-center text-gray-500">No customers with pre-orders found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @foreach($customers as $customer)
    <template id="customer-preorders-{{ $customer->id }}">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse whitespace-nowrap">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="p-2 border-b">Pre-Order #</th>
                        <th class="p-2 border-b">Date</th>
                        <th class="p-2 border-b">Status</th>
                        <th class="p-2 border-b">Payments</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($customer->preOrders as $po)
                    <tr>
                        <td class="p-2 border-b font-medium text-gray-900">
                            <a href="{{ route('preorders.show', $po->id) }}" class="text-blue-600 hover:underline">
                                {{ $po->pre_order_number }}
                            </a>
                        </td>
                        <td class="p-2 border-b">{{ optional($po->pre_order_date)->format('Y-m-d') ?: $po->created_at->format('Y-m-d') }}</td>
                        <td class="p-2 border-b">
                            <span class="px-2 py-1 text-xs rounded-full {{ $po->status === 'completed' ? 'bg-green-100 text-green-800' : ($po->status === 'cancelled' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') }}">
                                {{ ucfirst($po->status) }}
                            </span>
                        </td>
                        <td class="p-2 border-b">
                            @if($po->payments->isEmpty())
                                <span class="text-gray-400 italic">No payments</span>
                            @else
                                <ul class="list-disc pl-4 text-xs text-gray-700">
                                    @foreach($po->payments as $payment)
                                        <li>
                                            {{ optional($payment->payment_date)->format('Y-m-d') ?: $payment->created_at->format('Y-m-d') }}: 
                                            <strong>Rs {{ number_format((float)$payment->amount, 2) }}</strong>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </template>
    @endforeach

    <!-- Modal -->
    <div id="preOrderModal" class="fixed inset-0 z-[100] overflow-y-auto" style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
            <div onclick="closeModal()" class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75"></div>
            <div class="inline-block px-4 pt-5 pb-4 overflow-hidden text-left align-bottom transition-all transform bg-white rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-3xl sm:w-full sm:p-6">
                <div class="flex justify-between items-center mb-4 border-b pb-3">
                    <h3 class="text-lg font-bold text-gray-900" id="modalTitle"></h3>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 focus:outline-none"><i class="fas fa-times text-xl"></i></button>
                </div>
                <div id="modalContent" class="mt-2 text-sm text-gray-600 max-h-[60vh] overflow-y-auto"></div>
                <div class="mt-5 sm:mt-6 border-t pt-3 flex justify-end">
                    <button type="button" onclick="closeModal()" class="inline-flex justify-center px-4 py-2 text-sm font-medium text-white bg-gray-700 border border-transparent rounded-md shadow-sm hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function openModal(title, templateId) {
        document.getElementById('modalTitle').innerText = 'Pre-Orders for ' + title;
        document.getElementById('modalContent').innerHTML = document.getElementById(templateId).innerHTML;
        document.getElementById('preOrderModal').style.display = 'block';
    }
    
    function closeModal() {
        document.getElementById('preOrderModal').style.display = 'none';
    }
</script>
@endpush
@endsection
