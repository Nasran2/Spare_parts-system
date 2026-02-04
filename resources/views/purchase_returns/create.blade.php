@extends('layouts.app')

@section('title', 'Create Purchase Return')
@section('page-title', 'Create Purchase Return')

@section('content')
<div class="space-y-6">
    
    <!-- Step 1: Select Purchase -->
    <div class="bg-white rounded-xl shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">1. Select Purchase</h3>
        
        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline">{{ session('error') }}</span>
            </div>
        @endif

        <form action="{{ route('purchase-returns.create') }}" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="col-span-1">
                <label class="block text-sm font-medium text-gray-700 mb-1">Purchase ID / Ref</label>
                <input 
                    type="text" 
                    name="purchase_id" 
                    value="{{ request('purchase_id') }}"
                    placeholder="e.g. PO-2025..."
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                >
            </div>
            
            <div class="col-span-1">
                <label class="block text-sm font-medium text-gray-700 mb-1">Supplier</label>
                <select name="supplier_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">All Suppliers</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" {{ request('supplier_id') == $supplier->id ? 'selected' : '' }}>
                            {{ $supplier->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-span-1">
                <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                <input 
                    type="date" 
                    name="date" 
                    value="{{ request('date') }}"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                >
            </div>

            <div class="col-span-1 flex items-end">
                <button type="submit" class="w-full px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    <i class="fas fa-search mr-2"></i>Search
                </button>
            </div>
        </form>
    </div>

    @if(isset($purchases) && $purchases->count() > 0)
    <!-- Search Results -->
    <div class="bg-white rounded-xl shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Search Results</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50 border-b">
                        <th class="p-3 font-semibold text-gray-600">Purchase No</th>
                        <th class="p-3 font-semibold text-gray-600">Date</th>
                        <th class="p-3 font-semibold text-gray-600">Supplier</th>
                        <th class="p-3 font-semibold text-gray-600">Total</th>
                        <th class="p-3 font-semibold text-gray-600">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($purchases as $p)
                    <tr class="border-b hover:bg-gray-50">
                        <td class="p-3">{{ $p->purchase_no }}</td>
                        <td class="p-3">{{ $p->purchase_date->format('Y-m-d') }}</td>
                        <td class="p-3">{{ $p->supplier->name ?? 'Unknown' }}</td>
                        <td class="p-3">{{ number_format($p->total_amount, 2) }}</td>
                        <td class="p-3">
                            <a href="{{ route('purchase-returns.create', ['purchase_id' => $p->id]) }}" class="text-blue-600 hover:text-blue-800 font-medium">
                                Select
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    @if(isset($purchase))
    <!-- Step 2: Select Items -->
    <div class="bg-white rounded-xl shadow-md p-6">
        <div class="flex justify-between items-start mb-6">
            <div>
                <h3 class="text-lg font-semibold text-gray-800">2. Select Items to Return</h3>
                <p class="text-sm text-gray-600">Purchase #{{ $purchase->purchase_no ?? $purchase->id }} - {{ $purchase->supplier->name ?? 'Unknown' }}</p>
                @if($purchase->due_amount > 0)
                    <p class="text-sm text-red-600 font-semibold mt-1">
                        Current Due Amount: {{ number_format($purchase->due_amount, 2) }}
                    </p>
                @endif
            </div>
            <div class="text-right">
                <p class="text-sm text-gray-500">Purchase Date: {{ $purchase->purchase_date->format('M d, Y') }}</p>
            </div>
        </div>

        <form action="{{ route('purchase-returns.store') }}" method="POST">
            @csrf
            <input type="hidden" name="purchase_id" value="{{ $purchase->id }}">

            <div class="overflow-x-auto mb-6">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Product</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Purchased Qty</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Unit Cost</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Return Qty</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Refund Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($purchase->items as $index => $item)
                        <tr>
                            <td class="px-6 py-4 text-sm text-gray-800">
                                {{ $item->product->name }}
                                <input type="hidden" name="items[{{ $index }}][id]" value="{{ $item->id }}">
                            </td>
                            <td class="px-6 py-4 text-sm text-right text-gray-600">
                                {{ $item->quantity }}
                            </td>
                            <td class="px-6 py-4 text-sm text-right text-gray-600">
                                {{ number_format($item->unit_cost, 2) }}
                                <input type="hidden" class="unit-price" value="{{ $item->unit_cost }}">
                            </td>
                            <td class="px-6 py-4 text-right">
                                <input 
                                    type="number" 
                                    name="items[{{ $index }}][quantity]" 
                                    min="0" 
                                    max="{{ $item->quantity }}" 
                                    value="0"
                                    class="w-24 px-2 py-1 border border-gray-300 rounded text-right return-qty"
                                    onchange="calculateTotal()"
                                >
                            </td>
                            <td class="px-6 py-4 text-sm text-right font-semibold text-gray-800 line-total">
                                0.00
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <td colspan="4" class="px-6 py-4 text-right font-bold text-gray-700">Total Refund:</td>
                            <td class="px-6 py-4 text-right font-bold text-red-600" id="total-refund">0.00</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Refund Method *</label>
                    <select name="refund_method" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" required>
                        <option value="cash">Cash Refund (Receive Cash)</option>
                        <option value="account">Account Adjustment (Deduct from Due)</option>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">
                        <strong>Cash:</strong> Supplier pays you cash. <br>
                        <strong>Account:</strong> Reduces amount you owe to supplier.
                        @if($purchase->due_amount > 0)
                            <br><span class="text-red-600 font-semibold">Current Due: {{ number_format($purchase->due_amount, 2) }}</span>
                        @endif
                    </p>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Notes</label>
                    <textarea name="notes" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="px-6 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition font-semibold shadow-lg">
                    Process Return
                </button>
            </div>
        </form>
    </div>
    @endif
</div>

<script>
function calculateTotal() {
    let total = 0;
    const rows = document.querySelectorAll('tbody tr');
    
    rows.forEach(row => {
        const price = parseFloat(row.querySelector('.unit-price').value);
        const qty = parseInt(row.querySelector('.return-qty').value) || 0;
        const lineTotal = price * qty;
        
        row.querySelector('.line-total').textContent = lineTotal.toFixed(2);
        total += lineTotal;
    });
    
    document.getElementById('total-refund').textContent = total.toFixed(2);
}
</script>
@endsection
