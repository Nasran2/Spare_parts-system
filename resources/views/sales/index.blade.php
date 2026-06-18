@extends('layouts.app')

@section('title', 'Sales')
@section('page-title', 'Sales Management')

@section('content')
@php
    $isSalesPrivacy = ($privacyModeActive ?? false) && ($privacySettings->apply_to_sales_list ?? false);
    $formatRealMoney = fn ($amount) => trim($currency) . ' ' . number_format((float) $amount, 2);
@endphp
<div class="space-y-6">
    <!-- Header Actions -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Sales Management</h3>
            <p class="text-sm text-gray-600">Track and manage all sales transactions</p>
        </div>
        <a href="{{ route('pos.index') }}" class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg hover:from-blue-700 hover:to-blue-800 transition shadow-lg">
            <i class="fas fa-cash-register mr-2"></i>Open POS
        </a>
    </div>

    <!-- Filters -->
    <form method="GET" class="bg-white rounded-xl shadow-md p-4 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
            <div>
                <label class="text-xs font-semibold text-gray-600">Date From</label>
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="mt-1 w-full px-3 py-2 border rounded-lg">
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-600">Date To</label>
                <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="mt-1 w-full px-3 py-2 border rounded-lg">
            </div>
            @include('partials.quick-date-filter', ['fromName' => 'date_from', 'toName' => 'date_to'])
            <div>
                <label class="text-xs font-semibold text-gray-600">Customer</label>
                <select name="customer_id" class="mt-1 w-full px-3 py-2 border rounded-lg">
                    <option value="">All</option>
                    @foreach($customers as $c)
                        <option value="{{ $c->id }}" @selected(($filters['customer_id'] ?? '') == $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-600">Payment Status</label>
                <select name="payment_status" class="mt-1 w-full px-3 py-2 border rounded-lg">
                    <option value="">All</option>
                    @foreach(['paid','hold','partial','unpaid'] as $st)
                        <option value="{{ $st }}" @selected(($filters['payment_status'] ?? '') === $st)>{{ ucfirst($st) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end space-x-2">
                <button class="px-4 py-2 bg-blue-600 text-white rounded-lg">Filter</button>
                <a href="{{ route('sales.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg">Reset</a>
            </div>
        </div>
        <div class="flex flex-wrap gap-2 pt-2">
            <a href="{{ route('sales.export.csv', request()->query()) }}" target="_blank" rel="noopener" class="px-3 py-2 bg-green-600 text-white rounded text-sm"><i class="fas fa-file-csv mr-1"></i>CSV</a>
            <a href="{{ route('sales.export.pdf', request()->query()) }}" target="_blank" rel="noopener" class="px-3 py-2 bg-red-600 text-white rounded text-sm"><i class="fas fa-file-pdf mr-1"></i>PDF</a>
        </div>
    </form>

    <div class="mt-4">
        <label class="text-xs font-semibold text-gray-600">Quick search</label>
        <input id="salesSearchInput" type="search" placeholder="Search invoices, customers..." class="mt-2 w-full px-4 py-2 border rounded-lg focus:border-blue-500 focus:ring focus:ring-blue-100">
    </div>

    <!-- Sales Table -->
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Invoice No</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Customer</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Date</th>
                        <th class="px-6 py-4 text-right text-xs font-semibold text-gray-600 uppercase">Amount</th>
                        <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase">Pay Status</th>
                        <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase min-w-[150px]">Method</th>
                        <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase">Status</th>
                        <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($sales as $sale)
                    @php
                        $privacyInvoiceLabel = $isSalesPrivacy
                            ? \App\Services\PrivacyModeService::displayInvoiceNumber($sale)
                            : (string) $sale->sale_no;
                        $searchText = $isSalesPrivacy
                            ? strtolower($privacyInvoiceLabel . ' ' . ($sale->customer->name ?? '') . ' ' . ($sale->payment_status ?? '') . ' ' . ($sale->payment_method ?? '') . ' ' . $sale->total_amount . ' ' . $sale->paid_amount . ' ' . $sale->due_amount)
                            : strtolower($sale->sale_no . ' ' . ($sale->customer->name ?? '') . ' ' . ($sale->payment_status ?? '') . ' ' . ($sale->payment_method ?? ''));
                        $heldChequeAmount = (float) ($sale->held_cheque_amount ?? 0);
                        $hasChequeHold = $heldChequeAmount > 0;
                        $primaryCheque = $sale->chequePayments?->sortByDesc('created_at')->first();
                        $displayPayStatus = $hasChequeHold ? 'Hold' : ucfirst((string) ($sale->payment_status ?? 'unpaid'));
                        $displayPayStatusClass = $hasChequeHold
                            ? 'bg-indigo-100 text-indigo-700'
                            : (($sale->payment_status === 'paid') ? 'bg-green-100 text-green-700' : (($sale->payment_status === 'partial') ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700'));
                    @endphp
                    <tr data-sales-row class="hover:bg-gray-50 transition" data-search-text="{{ $searchText }}">
                        <td class="px-6 py-4">
                            <span class="font-mono font-semibold text-blue-600">{{ $privacyInvoiceLabel }}</span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-gray-800">{{ $sale->customer->name ?? 'Walk-in Customer' }}</span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-sm text-gray-600">{{ $sale->sale_date?->format('M d, Y') ?? $sale->created_at->format('M d, Y') }}</span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="text-sm">
                                @php
                                    $exchangeCredit = (float) ($sale->exchange_return_amount ?? 0);
                                    $netPayable = round(((float) $sale->total_amount) - $exchangeCredit, 2);
                                    $refundDue = max(0.0, -1 * $netPayable);
                                @endphp

                                @if($exchangeCredit > 0)
                                    <div class="font-semibold text-gray-800">
                                        @if($refundDue > 0)
                                            Refund: {{ $isSalesPrivacy ? $formatRealMoney($refundDue) : \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $refundDue, $currency) }}
                                        @else
                                            Net: {{ $isSalesPrivacy ? $formatRealMoney(max(0, $netPayable)) : \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, max(0, $netPayable), $currency) }}
                                        @endif
                                    </div>
                                    <div class="mt-0.5 text-[11px] text-gray-500">
                                        Total {{ $isSalesPrivacy ? $formatRealMoney($sale->total_amount) : \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, (float) $sale->total_amount, $currency) }}
                                        • Return {{ $isSalesPrivacy ? $formatRealMoney(-1 * $exchangeCredit) : \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, -1 * $exchangeCredit, $currency) }}
                                    </div>
                                @else
                                    <div class="font-semibold text-gray-800">{{ $isSalesPrivacy ? $formatRealMoney($sale->total_amount) : \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $sale->total_amount, $currency) }}</div>
                                @endif
                                <div class="mt-0.5 text-xs">
                                    <span class="text-green-700">Paid: {{ $isSalesPrivacy ? $formatRealMoney($sale->paid_amount) : \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $sale->paid_amount, $currency) }}</span>
                                    @if($heldChequeAmount > 0)
                                        <span class="ml-2 inline-block whitespace-nowrap text-indigo-700 font-semibold">Held: {{ $isSalesPrivacy ? $formatRealMoney($heldChequeAmount) : \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $heldChequeAmount, $currency) }}</span>
                                    @endif
                                    <span class="ml-2 inline-block whitespace-nowrap {{ ($sale->due_amount ?? 0) > 0 ? 'text-red-700 font-semibold' : 'text-gray-500' }}">Balance: {{ $isSalesPrivacy ? $formatRealMoney($sale->due_amount) : \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $sale->due_amount, $currency) }}</span>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $displayPayStatusClass }}">
                                {{ $displayPayStatus }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center min-w-[150px]">
                            <span class="inline-flex items-center justify-center whitespace-nowrap px-3 py-1 text-xs font-semibold rounded-full {{ match ((string) ($sale->payment_method ?? 'cash')) { 'cash' => 'bg-gray-200 text-gray-800', 'card' => 'bg-blue-100 text-blue-700', 'bank_transfer' => 'bg-purple-100 text-purple-700', 'mobile_payment' => 'bg-teal-100 text-teal-700', 'cheque' => 'bg-indigo-100 text-indigo-700', default => 'bg-gray-100 text-gray-600', } }}">
                                @if(((string) ($sale->payment_method ?? 'cash')) === 'cheque')
                                    <i class="fas fa-money-check-alt mr-1.5 text-[10px]"></i>
                                @endif
                                {{ ((string) ($sale->payment_method ?? 'cash')) === 'cheque' ? 'Cheque Payment' : str_replace('_', ' ', ucfirst((string) ($sale->payment_method ?? 'cash'))) }}
                            </span>
                            @if($primaryCheque)
                                <div class="mt-2 mx-auto max-w-[145px] rounded-md bg-gray-50 px-2 py-1 text-[11px] leading-4 text-gray-500">
                                    <div class="truncate">No: {{ $primaryCheque->cheque_number }}</div>
                                    <div class="whitespace-nowrap">{{ $primaryCheque->cheque_date?->format('Y-m-d') }} · {{ ucfirst($primaryCheque->status) }}</div>
                                </div>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span title="{{ ucfirst((string) ($sale->sale_type ?? 'sale')) }}" class="inline-flex h-3 w-3 rounded-full align-middle {{ (($sale->sale_type ?? 'sale') === 'sale') ? 'bg-blue-500' : 'bg-amber-500' }}"></span>
                            @if(((float) ($sale->exchange_return_amount ?? 0)) > 0)
                                <span class="ml-1 px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-700 border border-amber-200">EXCHANGE</span>
                            @elseif($sale->returns->count() > 0)
                                <span class="ml-1 px-2 py-0.5 rounded text-[10px] font-bold bg-red-100 text-red-700 border border-red-200">RETURNED</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center">
                            <div class="flex items-center justify-center space-x-2">
                                <a href="{{ route('sales.show', $sale->id) }}" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="{{ route('sales.print', $sale->id) }}" target="_blank" class="p-2 text-green-600 hover:bg-green-50 rounded-lg transition" title="Print">
                                    <i class="fas fa-print"></i>
                                </a>
                                @if($sale->payment_status !== 'paid' && (float) ($sale->due_amount ?? 0) > 0)
                                <button onclick="openPaymentModal({{ $sale->id }}, '{{ $privacyInvoiceLabel }}', {{ $sale->due_amount }}, '{{ $sale->customer ? $sale->customer->name : 'Walk-in Customer' }}', {{ \App\Support\SecretPos::isHidden($sale->total_amount) ? 'true' : 'false' }})" 
                                        class="p-2 text-orange-600 hover:bg-orange-50 rounded-lg transition" 
                                        title="Add Payment">
                                    <i class="fas fa-money-bill-wave"></i>
                                </button>
                                @endif
                                @if(auth()->user()?->hasPermission('sales.delete'))
                                <form action="{{ route('sales.destroy', $sale->id) }}" method="POST" onsubmit="return confirm('Delete this {{ $sale->sale_type === 'quotation' ? 'quotation' : 'sale' }}? This cannot be undone.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-2 text-red-700 hover:bg-red-50 rounded-lg transition" title="Delete {{ $sale->sale_type === 'quotation' ? 'Quotation' : 'Sale' }}">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center">
                            <i class="fas fa-receipt text-6xl text-gray-300 mb-4"></i>
                            <p class="text-gray-500 text-lg">No sales found</p>
                            <a href="{{ route('pos.index') }}" class="mt-4 inline-block px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                                <i class="fas fa-cash-register mr-2"></i>Start Selling
                            </a>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $sales->links() }}</div>
    </div>

</div>

<!-- Payment Modal -->
<div id="paymentModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-6 border w-full max-w-md shadow-lg rounded-lg bg-white">
        <div class="flex items-center justify-between mb-4 pb-3 border-b">
            <h3 class="text-xl font-bold text-gray-900 flex items-center">
                <i class="fas fa-money-bill-wave text-green-600 mr-2"></i>Add Payment
            </h3>
            <button onclick="closePaymentModal()" class="text-gray-400 hover:text-gray-600 text-xl">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="paymentForm" method="POST" action="{{ route('sales.payment.store') }}">
            @csrf
            <input type="hidden" id="sale_id" name="sale_id">
            
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Invoice</label>
                <input type="text" id="sale_no" readonly class="w-full bg-gray-50 border border-gray-300 rounded-lg p-2.5 text-gray-700">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Customer</label>
                <input type="text" id="customer_name" readonly class="w-full bg-gray-50 border border-gray-300 rounded-lg p-2.5 text-gray-700">
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Due Amount</label>
                <input type="text" id="due_amount_display" readonly class="w-full bg-gray-50 border border-gray-300 rounded-lg p-2.5 text-gray-700 font-bold text-red-600">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Payment Amount <span class="text-red-500">*</span></label>
                <input type="number" name="amount" id="payment_amount" step="0.01" min="0.01" required 
                       class="w-full border border-gray-300 rounded-lg p-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Payment Method <span class="text-red-500">*</span></label>
                <select name="payment_method" required class="w-full border border-gray-300 rounded-lg p-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="cash">Cash</option>
                    <option value="card">Card</option>
                    <option value="bank_transfer">Bank Transfer</option>
                    <option value="mobile_payment">Mobile Payment</option>
                </select>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Payment Date <span class="text-red-500">*</span></label>
                <input type="date" name="payment_date" required value="{{ date('Y-m-d') }}"
                       class="w-full border border-gray-300 rounded-lg p-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Notes (Optional)</label>
                <textarea name="notes" rows="2" class="w-full border border-gray-300 rounded-lg p-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t">
                <button type="button" onclick="closePaymentModal()" class="px-5 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">
                    <i class="fas fa-times mr-1"></i> Cancel
                </button>
                <button type="submit" class="px-5 py-2.5 bg-green-600 hover:bg-green-700 text-white rounded-lg transition">
                    <i class="fas fa-check mr-1"></i> Add Payment
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
function openPaymentModal(saleId, saleNo, dueAmount, customerName, isHidden) {
    document.getElementById('sale_id').value = saleId;
    document.getElementById('sale_no').value = saleNo;
    document.getElementById('customer_name').value = customerName;
    const CURRENCY = @json($currency);
    document.getElementById('due_amount_display').value = isHidden ? '—' : (CURRENCY + ' ' + parseFloat(dueAmount).toFixed(2));
    document.getElementById('payment_amount').value = parseFloat(dueAmount).toFixed(2);
    document.getElementById('payment_amount').max = parseFloat(dueAmount).toFixed(2);
    document.getElementById('paymentModal').classList.remove('hidden');
}

function closePaymentModal() {
    document.getElementById('paymentModal').classList.add('hidden');
    document.getElementById('paymentForm').reset();
}

// Remove any global loading overlay after exports if still visible
document.addEventListener('visibilitychange', () => {
    if (!document.hidden) {
        const loader = document.querySelector('.global-loading, .loading-overlay');
        if (loader) loader.classList.add('hidden');
    }
});

const salesSearchInput = document.getElementById('salesSearchInput');
const salesRows = document.querySelectorAll('[data-sales-row]');
salesSearchInput?.addEventListener('input', function () {
    const term = this.value.trim().toLowerCase();
    salesRows.forEach(row => {
        const text = row.dataset.searchText || '';
        row.style.display = text.includes(term) ? '' : 'none';
    });
});
</script>
@endpush
@endsection
