@extends('layouts.app')

@section('title', 'VAT Payments')
@section('page-title', 'VAT Payment Management')

@section('content')
<div class="space-y-6">
    @if(session('success'))<div class="rounded-lg bg-green-50 border border-green-200 p-3 text-green-700">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="rounded-lg bg-red-50 border border-red-200 p-3 text-red-700">{{ $errors->first() }}</div>@endif

    @if(auth()->user()?->hasPermission('tax.payment.create'))
    <form method="POST" action="{{ route('tax.payments.store') }}" enctype="multipart/form-data" class="rounded-xl bg-white border p-5 shadow-sm">
        @csrf
        <h3 class="font-bold text-gray-800 mb-4">Record VAT Payment</h3>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-semibold mb-1">Tax Period</label>
                <input type="month" name="tax_period" id="tax_period" value="{{ old('tax_period', now()->format('Y-m')) }}" class="w-full rounded-lg border-gray-300" required>
                <div class="relative mt-1 group hidden" id="payable-balance-info">
                    <div class="text-xs text-blue-600 font-semibold cursor-pointer flex items-center gap-1">
                        Balance: {{ $currency }}<span id="payable-balance-amount">0.00</span>
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <div class="absolute left-0 top-full mt-1 w-56 bg-white border border-gray-200 rounded-lg shadow-xl p-3 z-50 text-gray-700 text-xs hidden group-hover:block pointer-events-none">
                        <div class="flex justify-between py-1 border-b border-gray-100">
                            <span>Output VAT:</span><span id="bd-output" class="font-medium text-right">0.00</span>
                        </div>
                        <div class="flex justify-between py-1 border-b border-gray-100">
                            <span>Input VAT:</span><span id="bd-input" class="font-medium text-right">0.00</span>
                        </div>
                        <div class="flex justify-between py-1 border-b border-gray-100">
                            <span>Adjustments:</span><span id="bd-adj" class="font-medium text-right">0.00</span>
                        </div>
                        <div class="flex justify-between py-1 border-b border-gray-100">
                            <span>Prev Balance:</span><span id="bd-prev" class="font-medium text-right">0.00</span>
                        </div>
                        <div class="flex justify-between pt-2 pb-1 font-bold text-blue-700">
                            <span>Payable:</span><span id="bd-payable" class="text-right">0.00</span>
                        </div>
                    </div>
                </div>
            </div>
            <div><label class="block text-xs font-semibold mb-1">Payment Date</label><input type="date" name="payment_date" value="{{ old('payment_date', now()->toDateString()) }}" class="w-full rounded-lg border-gray-300" required></div>
            <div><label class="block text-xs font-semibold mb-1">Reference</label><input name="reference" value="{{ old('reference') }}" class="w-full rounded-lg border-gray-300" required></div>
            <div><label class="block text-xs font-semibold mb-1">Amount Paid</label><input type="number" step="0.0001" min="0.0001" name="paid_amount" value="{{ old('paid_amount') }}" class="w-full rounded-lg border-gray-300" required></div>
            <div><label class="block text-xs font-semibold mb-1">Payment Method</label><select name="payment_method" class="w-full rounded-lg border-gray-300">@foreach(['cash','bank_deposit','bank_transfer','card','mobile_payment','cheque'] as $method)<option value="{{ $method }}">{{ ucwords(str_replace('_',' ',$method)) }}</option>@endforeach</select></div>
            <div><label class="block text-xs font-semibold mb-1">Cash / Bank Account</label><select name="account_id" class="w-full rounded-lg border-gray-300" required>@foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->code }} - {{ $account->name }}</option>@endforeach</select></div>
            <div><label class="block text-xs font-semibold mb-1">Receipt Attachment</label><input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png" class="w-full rounded-lg border p-1.5"></div>
            <div><label class="block text-xs font-semibold mb-1">Notes</label><input name="notes" class="w-full rounded-lg border-gray-300"></div>
        </div>
        <div class="mt-4 flex items-center justify-end gap-3">
            <button name="finalize" value="0" class="rounded-lg bg-gray-700 px-4 py-2 text-white">Save Draft</button>
            <button name="finalize" value="1" class="rounded-lg bg-blue-600 px-4 py-2 text-white">Save & Finalize</button>
        </div>
    </form>
    @endif

    <div class="rounded-xl bg-white border shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-gray-600"><tr><th class="p-3">Period</th><th class="p-3">Date</th><th class="p-3">Reference</th><th class="p-3 text-right">Payable</th><th class="p-3 text-right">Paid</th><th class="p-3 text-right">Remaining</th><th class="p-3">Status</th><th class="p-3">Actions</th></tr></thead>
            <tbody class="divide-y">
                @forelse($payments as $payment)
                <tr>
                    <td class="p-3">{{ $payment->tax_period }}</td><td class="p-3">{{ $payment->payment_date?->format('Y-m-d') }}</td><td class="p-3 font-semibold">{{ $payment->reference }}</td>
                    <td class="p-3 text-right">Rs {{ number_format((float) $payment->payable_amount, 2) }}</td><td class="p-3 text-right">Rs {{ number_format((float) $payment->paid_amount, 2) }}</td><td class="p-3 text-right">Rs {{ number_format((float) $payment->remaining_amount, 2) }}</td>
                    <td class="p-3">{{ ucfirst($payment->status) }}</td>
                    <td class="p-3"><div class="flex gap-2">
                        @if($payment->status === 'draft' && auth()->user()?->hasPermission('tax.payment.edit'))<form method="POST" action="{{ route('tax.payments.finalize', $payment) }}">@csrf<button class="text-blue-600">Finalize</button></form>@endif
                        @if($payment->status === 'draft' && auth()->user()?->hasPermission('tax.payment.edit'))
                            <details class="relative">
                                <summary class="cursor-pointer text-indigo-600">Edit</summary>
                                <form method="POST" action="{{ route('tax.payments.update', $payment) }}" enctype="multipart/form-data" class="absolute right-0 z-20 mt-2 w-80 space-y-2 rounded-xl border bg-white p-4 text-left shadow-xl">
                                    @csrf @method('PATCH')
                                    <input type="month" name="tax_period" value="{{ $payment->tax_period }}" class="w-full rounded-lg border-gray-300" required>
                                    <input type="date" name="payment_date" value="{{ $payment->payment_date?->format('Y-m-d') }}" class="w-full rounded-lg border-gray-300" required>
                                    <input name="reference" value="{{ $payment->reference }}" class="w-full rounded-lg border-gray-300" required>
                                    <input type="number" step="0.0001" min="0.0001" name="paid_amount" value="{{ $payment->paid_amount }}" class="w-full rounded-lg border-gray-300" required>
                                    <select name="payment_method" class="w-full rounded-lg border-gray-300">@foreach(['cash','bank_deposit','bank_transfer','card','mobile_payment','cheque'] as $method)<option value="{{ $method }}" @selected($payment->payment_method===$method)>{{ ucwords(str_replace('_',' ',$method)) }}</option>@endforeach</select>
                                    <select name="account_id" class="w-full rounded-lg border-gray-300" required>@foreach($accounts as $account)<option value="{{ $account->id }}" @selected($payment->account_id===$account->id)>{{ $account->code }} - {{ $account->name }}</option>@endforeach</select>
                                    <input name="notes" value="{{ $payment->notes }}" class="w-full rounded-lg border-gray-300" placeholder="Notes">
                                    <input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png" class="w-full rounded-lg border p-1.5">
                                    <button class="w-full rounded-lg bg-indigo-600 px-3 py-2 text-white">Update Draft</button>
                                </form>
                            </details>
                        @endif
                        @if($payment->status === 'finalized' && auth()->user()?->hasPermission('tax.payment.edit'))<form method="POST" action="{{ route('tax.payments.reverse', $payment) }}">@csrf<button class="text-amber-600">Reverse</button></form>@endif
                        @if($payment->status === 'draft' && auth()->user()?->hasPermission('tax.payment.delete'))<form method="POST" action="{{ route('tax.payments.destroy', $payment) }}">@csrf @method('DELETE')<button class="text-red-600">Remove</button></form>@endif
                    </div></td>
                </tr>
                @empty<tr><td colspan="8" class="p-8 text-center text-gray-500">No VAT payments recorded.</td></tr>@endforelse
            </tbody>
        </table>
        <div class="p-4">{{ $payments->links() }}</div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const periodInput = document.getElementById('tax_period');
        const infoDiv = document.getElementById('payable-balance-info');
        const amountSpan = document.getElementById('payable-balance-amount');

        async function fetchBalance() {
            const period = periodInput.value;
            if (!period) return;
            try {
                const response = await fetch(`{{ route('tax.payments.balance') }}?period=${period}`);
                const data = await response.json();
                if (data && data.payable_amount !== undefined) {
                    const format = (num) => Number(num).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                    amountSpan.textContent = format(data.payable_amount);
                    
                    // Breakdown details
                    document.getElementById('bd-output').textContent = format(data.output_vat);
                    document.getElementById('bd-input').textContent = format(data.input_vat);
                    document.getElementById('bd-adj').textContent = format(data.adjustments);
                    document.getElementById('bd-prev').textContent = format(data.previous_balance);
                    document.getElementById('bd-payable').textContent = format(data.payable_amount);

                    infoDiv.classList.remove('hidden');
                }
            } catch (e) {
                console.error('Failed to fetch balance', e);
                infoDiv.classList.add('hidden');
            }
        }

        if (periodInput) {
            periodInput.addEventListener('change', fetchBalance);
            // Fetch initial balance if value exists
            if (periodInput.value) {
                fetchBalance();
            }
        }
    });
</script>
@endpush
