@extends('layouts.app')

@section('title', 'Purchases')
@section('page-title', 'Purchase Management')

@section('content')
<div class="space-y-6">
    
    <!-- Header Actions -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Purchase Management</h3>
            <p class="text-sm text-gray-600">Track and manage all purchase orders</p>
        </div>
        <a href="{{ route('purchases.create') }}" class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg hover:from-blue-700 hover:to-blue-800 transition shadow-lg">
            <i class="fas fa-plus mr-2"></i>Add New Purchase
        </a>
    </div>

    <!-- Purchases Table -->
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Purchase No</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Supplier</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Date</th>
                        <th class="px-6 py-4 text-right text-xs font-semibold text-gray-600 uppercase">Amount</th>
                        <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase">Payment</th>
                        <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase">Status</th>
                        <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($purchases as $purchase)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-6 py-4">
                            <span class="font-mono font-semibold text-orange-600">{{ $purchase->purchase_no }}</span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-gray-800">{{ $purchase->supplier->name ?? 'N/A' }}</span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-sm text-gray-600">{{ optional($purchase->purchase_date)->format('M d, Y') }}</span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <span class="font-semibold text-gray-800">${{ number_format($purchase->total_amount ?? 0, 2) }}</span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            @if($purchase->payment_status === 'paid')
                                <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-semibold">Paid</span>
                            @elseif($purchase->payment_status === 'partial')
                                <span class="px-3 py-1 bg-yellow-100 text-yellow-700 rounded-full text-xs font-semibold">Partial</span>
                            @else
                                <span class="px-3 py-1 bg-red-100 text-red-700 rounded-full text-xs font-semibold">Unpaid</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center">
                            @if($purchase->status === 'received')
                                <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-semibold">Received</span>
                            @else
                                <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-semibold">{{ ucfirst($purchase->status) }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center">
                            <div class="flex items-center justify-center space-x-2">
                                <button onclick="viewPurchase({{ $purchase->id }})" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition" title="View">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button onclick="editPurchase({{ $purchase->id }})" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center">
                            <i class="fas fa-shopping-cart text-6xl text-gray-300 mb-4"></i>
                            <p class="text-gray-500 text-lg">No purchases found</p>
                            <a href="{{ route('purchases.create') }}" class="mt-4 inline-block px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                                <i class="fas fa-plus mr-2"></i>Add Purchase
                            </a>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
function openCreateModal() {
    // fallback: navigate to create page
    window.location.href = '{{ route('purchases.create') }}';
}
function viewPurchase(id) {
    window.location.href = '{{ url('purchases') }}/' + id;
}
function editPurchase(id) {
    window.location.href = '{{ url('purchases') }}/' + id + '/edit';
}
</script>
@endsection
