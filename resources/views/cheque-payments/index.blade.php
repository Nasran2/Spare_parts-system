@extends('layouts.app')

@section('title', 'Cheque Details')
@section('page-title', 'Cheque Details')

@section('content')
@php
    $canManage = auth()->user()?->hasPermission('cheque_payments.manage');
    $statusClasses = [
        'pending' => 'bg-amber-100 text-amber-700',
        'passed' => 'bg-green-100 text-green-700',
        'returned' => 'bg-red-100 text-red-700',
    ];
@endphp

<link rel="stylesheet" href="{{ asset('css/cheque-payments.css') }}?v=20261017b">
<div class="cheque-page space-y-6">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Cheque Payment Checking</h3>
            <p class="text-sm text-gray-600">Review cheque pass dates, numbers, banks, customers, and current status.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('cheque-payments.export.pdf', request()->only(['search', 'status', 'date_from', 'date_to'])) }}" download data-skip-page-loader class="inline-flex items-center rounded-lg bg-red-600 px-4 py-2 text-white hover:bg-red-700">
                <i class="fas fa-file-pdf mr-2"></i>Download PDF
            </a>
            <a href="{{ route('cheque-payments.export.excel', request()->only(['search', 'status', 'date_from', 'date_to'])) }}" download data-skip-page-loader class="inline-flex items-center rounded-lg bg-green-600 px-4 py-2 text-white hover:bg-green-700">
                <i class="fas fa-file-excel mr-2"></i>Download Excel
            </a>
            <a href="{{ route('sales.index') }}" class="inline-flex items-center rounded-lg bg-gray-100 px-4 py-2 text-gray-700 hover:bg-gray-200">
                <i class="fas fa-list-alt mr-2"></i>Sales List
            </a>
        </div>
    </div>

    <form method="GET" class="rounded-xl bg-white p-4 shadow-md">
        <div class="cheque-filters">
            <div class="md:col-span-2">
                <label for="cheque-filter-search" class="text-xs font-semibold text-gray-600">Search</label>
                <input type="search" id="cheque-filter-search" name="search" value="{{ request('search') }}" placeholder="Invoice, customer, cheque no, bank..." class="mt-1 w-full rounded-lg border px-3 py-2">
            </div>
            <div>
                <label for="cheque-filter-status" class="text-xs font-semibold text-gray-600">Status</label>
                <select id="cheque-filter-status" name="status" class="mt-1 w-full rounded-lg border px-3 py-2">
                    <option value="">All</option>
                    @foreach(['pending', 'passed', 'returned'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="cheque-filter-date_from" class="text-xs font-semibold text-gray-600">Date From</label>
                <input type="date" id="cheque-filter-date_from" name="date_from" value="{{ request('date_from') }}" class="mt-1 w-full rounded-lg border px-3 py-2">
            </div>
            <div>
                <label for="cheque-filter-date_to" class="text-xs font-semibold text-gray-600">Date To</label>
                <input type="date" id="cheque-filter-date_to" name="date_to" value="{{ request('date_to') }}" class="mt-1 w-full rounded-lg border px-3 py-2">
            </div>
            <div class="flex items-end gap-2">
                <button class="rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">Filter</button>
                <a href="{{ route('cheque-payments.index') }}" class="rounded-lg bg-gray-100 px-4 py-2 text-gray-700 hover:bg-gray-200">Reset</a>
            </div>
        </div>
        <p class="mt-3 text-xs text-gray-500">Downloads include all cheques matching these filters.</p>
    </form>

    <div class="overflow-hidden rounded-xl bg-white shadow-md">
        <div class="overflow-x-auto">
            <table class="cheque-table w-full">
                <thead class="border-b bg-gray-50">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase text-gray-600">Pass Date</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase text-gray-600">Cheque Details</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase text-gray-600">Party</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase text-gray-600">Reference</th>
                        <th class="px-6 py-4 text-right text-xs font-semibold uppercase text-gray-600">Amount</th>
                        <th class="px-6 py-4 text-center text-xs font-semibold uppercase text-gray-600">Status</th>
                        <th class="px-6 py-4 text-center text-xs font-semibold uppercase text-gray-600">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($cheques as $cheque)
                        <tr class="hover:bg-gray-50">
                            <td data-label="Pass date" class="px-6 py-4">
                                <div class="font-semibold text-gray-800">{{ $cheque->cheque_date?->format('Y-m-d') ?? '-' }}</div>
                                @if($cheque->status === 'pending' && $cheque->cheque_date)
                                    <div class="text-xs text-gray-500">
                                        @php
                                            $daysAway = (int) now()->startOfDay()->diffInDays($cheque->cheque_date->copy()->startOfDay(), false);
                                        @endphp
                                        {{ $daysAway === 0 ? 'Today' : ($daysAway > 0 ? $daysAway.' days from now' : abs($daysAway).' days overdue') }}
                                    </div>
                                @endif
                            </td>
                            <td data-label="Cheque details" class="px-6 py-4">
                                <div class="font-semibold text-gray-800">No: {{ $cheque->cheque_number }}</div>
                                <div class="text-xs text-gray-500">Bank: {{ $cheque->bank_name ?: '-' }}</div>
                                <div class="text-xs text-gray-500">Name: {{ $cheque->account_name ?: '-' }}</div>
                            </td>
                            <td data-label="Party" class="px-6 py-4">
                                                                @if($cheque->type === 'own')
                                    Supplier: <span class="font-semibold">{{ $cheque->supplier?->name ?? 'Unknown Supplier' }}</span>
                                @else
                                    Customer: <span class="font-semibold">{{ $cheque->customer?->name ?? $cheque->sale?->customer?->name ?? 'Walk-in Customer' }}</span>
                                    @if($cheque->supplier_id)
                                        <div class="text-xs text-blue-600 mt-1"><i class="fas fa-arrow-right mr-1"></i>Endorsed to: {{ $cheque->supplier?->name }}</div>
                                    @endif
                                @endif
                            </td>
                            <td data-label="Reference" class="px-6 py-4">
                                                                @if($cheque->type === 'own')
                                    @if($cheque->purchase)
                                        <a href="{{ route('purchases.show', $cheque->purchase_id) }}" class="font-mono font-semibold text-blue-600 hover:underline">PUR-{{ $cheque->purchase_id }}</a>
                                    @else
                                        <span class="text-gray-500">-</span>
                                    @endif
                                @else
                                    @if($cheque->sale)
                                        <a href="{{ route('sales.show', $cheque->sale_id) }}" class="font-mono font-semibold text-blue-600 hover:underline">{{ $cheque->sale->sale_no }}</a>
                                    @else
                                        <span class="text-gray-500">{{ $cheque->preOrder?->pre_order_number ?? 'Advance / Opening Balance' }}</span>
                                    @endif
                                @endif
                            </td>
                            <td data-label="Amount" class="px-6 py-4 text-right font-semibold text-gray-800">
                                {{ trim($currency) }} {{ number_format((float) $cheque->amount, 2) }}
                            </td>
                            <td data-label="Status" class="px-6 py-4 text-center">
                                                                <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $statusClasses[$cheque->status] ?? 'bg-gray-100 text-gray-700' }}">
                                    {{ ucfirst($cheque->status) }}
                                </span>
                                @if($cheque->type === 'own')
                                    <div class="mt-2"><span class="px-2 py-1 bg-indigo-100 text-indigo-700 text-[10px] font-bold uppercase rounded-full border border-indigo-200">Own Cheque</span></div>
                                @endif
                                @if($cheque->status === 'passed' && $cheque->bankAccount)
                                    <div class="mt-2 text-xs text-gray-600">{{ $cheque->type === 'own' ? 'Paid from' : 'Deposited to' }}: {{ $cheque->bankAccount->bank_name }} · {{ $cheque->bankAccount->account_number }}</div>
                                @endif
                                @if($cheque->auto_passed)
                                    <div class="mt-1 text-[11px] font-semibold text-green-600">Auto passed</div>
                                @endif
                            </td>
                            <td data-label="Action" class="cheque-actions-cell px-6 py-4">
                                <div class="flex items-center justify-center gap-2">
                                    @if($canManage && $cheque->status === 'pending')
                                        <form method="POST" action="{{ route('cheque-payments.pass', $cheque) }}" class="js-cheque-action-form" data-cheque-number="{{ $cheque->cheque_number }}" data-cheque-amount="{{ trim($currency) }} {{ number_format((float)$cheque->amount, 2) }}" data-action-label="pass" data-cheque-date="{{ $cheque->cheque_date?->format('Y-m-d') }}" data-cheque-type="{{ $cheque->type }}" data-bank-account-id="{{ $cheque->bank_account_id }}" data-can-endorse="{{ $cheque->sale_id ? '1' : '0' }}">
                                            @csrf
                                            <button class="rounded-lg bg-green-600 px-3 py-2 text-xs font-semibold text-white hover:bg-green-700">
                                                <i class="fas fa-check mr-1"></i>Pass
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('cheque-payments.return', $cheque) }}" class="js-cheque-action-form" data-cheque-number="{{ $cheque->cheque_number }}" data-cheque-amount="{{ trim($currency) }} {{ number_format((float)$cheque->amount, 2) }}" data-action-label="return" data-cheque-date="{{ $cheque->cheque_date?->format('Y-m-d') }}" data-cheque-type="{{ $cheque->type }}" data-bank-account-id="{{ $cheque->bank_account_id }}" data-can-endorse="{{ $cheque->sale_id ? '1' : '0' }}">
                                            @csrf
                                            <button class="rounded-lg bg-red-600 px-3 py-2 text-xs font-semibold text-white hover:bg-red-700">
                                                <i class="fas fa-undo mr-1"></i>Return
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-sm text-gray-500">{{ $cheque->processed_at?->format('Y-m-d H:i') ?? '-' }}</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-money-check-alt mb-3 text-5xl text-gray-300"></i>
                                <div>No cheque payments found</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $cheques->links() }}</div>
    </div>
</div>

<dialog id="cheque-confirm-modal" class="cheque-dialog" aria-labelledby="cheque-confirm-title" aria-describedby="cheque-confirm-message">
    <form id="cheque-confirm-form">
        <div class="cheque-dialog-heading">
            <div><h3 id="cheque-confirm-title">Pass cheque</h3><p id="cheque-confirm-summary"></p></div>
            <button type="button" aria-label="Close" data-cheque-confirm-cancel><i class="fas fa-times" aria-hidden="true"></i></button>
        </div>
        <div class="cheque-dialog-body">
            <p id="cheque-confirm-message"></p>
            <div id="cheque-pass-options">
                <div id="cheque-clear-method" class="cheque-clear-method">
                    <label><input type="radio" name="clear_method" value="bank" checked> Deposit to bank</label>
                    <label><input type="radio" name="clear_method" value="supplier"> Endorse to supplier</label>
                </div>
                <div id="bank-deposit-container">
                    <label for="modal-bank-account-id" id="cheque-bank-label">Which bank received this cheque?</label>
                    <select id="modal-bank-account-id" required>
                        <option value="">Select a bank account</option>
                        @foreach($bankAccounts as $bank)
                        <option value="{{ $bank->id }}">{{ $bank->bank_name }} · {{ $bank->account_name }} · {{ $bank->account_number }}</option>
                        @endforeach
                    </select>
                    @if($bankAccounts->isEmpty())<p class="cheque-bank-warning">Add a bank account before passing this cheque.</p>@endif
                    <p class="cheque-bank-help">Need another bank? <a href="{{ route('accounting.banks') }}#add-bank-account" target="_blank" rel="noopener">Add a bank account <i class="fas fa-external-link-alt" aria-hidden="true"></i></a>, then refresh this page.</p>
                </div>
                <div id="endorse-container" hidden>
                    <label for="modal-supplier-id">Endorse to supplier</label>
                    <select id="modal-supplier-id" disabled>
                        <option value="">Select a supplier</option>
                        @foreach($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->name }} {{ $supplier->company_name ? '('.$supplier->company_name.')' : '' }}</option>@endforeach
                    </select>
                    <p class="cheque-bank-help">This reduces the supplier's due balance. The cheque will not be deposited to a bank.</p>
                </div>
            </div>
        </div>
        <div class="cheque-dialog-footer">
            <button type="button" class="cheque-cancel" data-cheque-confirm-cancel>Cancel</button>
            <button type="submit" id="cheque-confirm-submit">Pass cheque</button>
        </div>
    </form>
</dialog>
@push('scripts')
<script src="{{ asset('js/cheque-payments.js') }}?v=20261017b"></script>
@endpush
@endsection
