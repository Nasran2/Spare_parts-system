@extends(request()->ajax() ? 'layouts.empty' : 'layouts.app')

@section('title', 'Make Payment')
@section('page-title', 'Make Payment - ' . $supplier->name)

@section('content')
<div class="space-y-6">
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-xl font-bold text-gray-800">Payment Allocation for {{ $supplier->name }}</h2>
            <p class="text-gray-600 text-sm mt-1">Total Due: <span class="font-bold text-red-600">{{ \App\Models\Setting::get('currency_symbol', 'Rs') }} <span id="total-due-display">{{ number_format($unpaidPurchases->sum('due_amount') + $openingBalanceDue, 2, '.', '') }}</span></span></p>
        </div>

        <form action="{{ route('suppliers.bulk-payment.store', $supplier->id) }}" method="POST" class="p-6" onsubmit="return validateAllocations()">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8 bg-gray-50 p-4 rounded-lg">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Payment Date</label>
                    <input type="date" name="payment_date" value="{{ date('Y-m-d') }}" class="w-full px-4 py-2 border rounded-lg focus:border-blue-500 focus:ring focus:ring-blue-100" required>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Payment Method</label>
                    <select name="payment_method" class="w-full px-4 py-2 border rounded-lg focus:border-blue-500 focus:ring focus:ring-blue-100" required>
                        <option value="cash">Cash</option>
                        <option value="card">Card</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="cheque">Cheque</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Total Payment Amount</label>
                    <div class="flex">
                        <input type="number" step="0.01" id="global-payment-amount" placeholder="Enter amount to auto-allocate" class="w-full px-4 py-2 border rounded-l-lg focus:border-blue-500 focus:ring focus:ring-blue-100">
                        <button type="button" onclick="autoAllocate()" class="bg-blue-600 text-white px-4 py-2 rounded-r-lg hover:bg-blue-700 font-semibold transition">Allocate</button>
                    </div>
                </div>
            </div>

            <h3 class="text-lg font-semibold text-gray-800 mb-4">Outstanding Bills</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-100 text-gray-600 uppercase font-semibold">
                        <tr>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Reference / Bill</th>
                            <th class="px-4 py-3 text-right">Total Amount</th>
                            <th class="px-4 py-3 text-right">Due Amount</th>
                            <th class="px-4 py-3 text-right">Payment to Apply</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200" id="bills-table-body">
                        @php $allocIndex = 0; @endphp
                        
                        @if($openingBalanceDue > 0)
                            <tr class="hover:bg-gray-50" data-due="{{ $openingBalanceDue }}">
                                <td class="px-4 py-3 text-gray-500">N/A</td>
                                <td class="px-4 py-3 font-semibold text-gray-800">Opening Balance</td>
                                <td class="px-4 py-3 text-right text-gray-600">{{ number_format($openingBalanceDue, 2) }}</td>
                                <td class="px-4 py-3 text-right font-bold text-red-600">{{ number_format($openingBalanceDue, 2) }}</td>
                                <td class="px-4 py-3 text-right">
                                    <input type="hidden" name="allocations[{{ $allocIndex }}][type]" value="opening_balance">
                                    <input type="number" step="0.01" name="allocations[{{ $allocIndex }}][amount]" class="allocation-input w-32 px-3 py-1 text-right border rounded focus:border-blue-500 focus:ring focus:ring-blue-100" value="0" min="0" max="{{ $openingBalanceDue }}" onchange="updateTotalApplied()">
                                </td>
                            </tr>
                            @php $allocIndex++; @endphp
                        @endif

                        @foreach($unpaidPurchases as $purchase)
                            <tr class="hover:bg-gray-50" data-due="{{ $purchase->due_amount }}">
                                <td class="px-4 py-3 text-gray-600">{{ $purchase->purchase_date ? \Carbon\Carbon::parse($purchase->purchase_date)->format('Y-m-d') : $purchase->created_at->format('Y-m-d') }}</td>
                                <td class="px-4 py-3 font-mono text-gray-800">{{ $purchase->purchase_no }}</td>
                                <td class="px-4 py-3 text-right text-gray-600">{{ number_format($purchase->total_amount, 2) }}</td>
                                <td class="px-4 py-3 text-right font-bold text-red-600">{{ number_format($purchase->due_amount, 2) }}</td>
                                <td class="px-4 py-3 text-right">
                                    <input type="hidden" name="allocations[{{ $allocIndex }}][type]" value="purchase">
                                    <input type="hidden" name="allocations[{{ $allocIndex }}][id]" value="{{ $purchase->id }}">
                                    <input type="number" step="0.01" name="allocations[{{ $allocIndex }}][amount]" class="allocation-input w-32 px-3 py-1 text-right border rounded focus:border-blue-500 focus:ring focus:ring-blue-100" value="0" min="0" max="{{ $purchase->due_amount }}" onchange="updateTotalApplied()">
                                </td>
                            </tr>
                            @php $allocIndex++; @endphp
                        @endforeach
                        
                        @if($unpaidPurchases->isEmpty() && $openingBalanceDue <= 0)
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-gray-500">No outstanding bills found.</td>
                            </tr>
                        @endif
                    </tbody>
                    <tfoot class="bg-gray-50 font-bold">
                        <tr>
                            <td colspan="4" class="px-4 py-3 text-right text-gray-700">Total Applied:</td>
                            <td class="px-4 py-3 text-right text-green-600 text-lg">
                                {{ \App\Models\Setting::get('currency_symbol', 'Rs') }} <span id="total-applied-display">0.00</span>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="mt-6 flex justify-end space-x-3">
                @if(request()->ajax())
                    <button type="button" onclick="closeBulkPaymentModal()" class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition">Cancel</button>
                @else
                    <a href="{{ route('suppliers.index') }}" class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition">Cancel</a>
                @endif
                <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 font-semibold transition shadow" id="submit-btn" {{ ($unpaidPurchases->isEmpty() && $openingBalanceDue <= 0) ? 'disabled' : '' }}>
                    <i class="fas fa-check mr-2"></i> Confirm Payment
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function autoAllocate() {
        let totalToAllocate = parseFloat(document.getElementById('global-payment-amount').value) || 0;
        if (totalToAllocate <= 0) return;

        const rows = document.querySelectorAll('#bills-table-body tr[data-due]');
        
        rows.forEach(row => {
            const due = parseFloat(row.getAttribute('data-due')) || 0;
            const input = row.querySelector('.allocation-input');
            
            if (totalToAllocate >= due) {
                input.value = due.toFixed(2);
                totalToAllocate -= due;
            } else if (totalToAllocate > 0) {
                input.value = totalToAllocate.toFixed(2);
                totalToAllocate = 0;
            } else {
                input.value = 0;
            }
        });
        
        updateTotalApplied();
    }

    function updateTotalApplied() {
        let total = 0;
        document.querySelectorAll('.allocation-input').forEach(input => {
            const val = parseFloat(input.value) || 0;
            const max = parseFloat(input.getAttribute('max')) || 0;
            
            if (val > max) {
                input.value = max.toFixed(2);
                total += max;
            } else if (val < 0) {
                input.value = 0;
            } else {
                total += val;
            }
        });
        document.getElementById('total-applied-display').innerText = total.toFixed(2);
    }

    function validateAllocations() {
        let total = 0;
        document.querySelectorAll('.allocation-input').forEach(input => {
            total += (parseFloat(input.value) || 0);
        });
        if (total <= 0) {
            alert('Please allocate at least an amount greater than 0 to make a payment.');
            return false;
        }
        return true;
    }
</script>
@endsection
