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

<div class="space-y-6">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Cheque Payment Checking</h3>
            <p class="text-sm text-gray-600">Review cheque pass dates, numbers, banks, customers, and current status.</p>
        </div>
        <a href="{{ route('sales.index') }}" class="inline-flex items-center rounded-lg bg-gray-100 px-4 py-2 text-gray-700 hover:bg-gray-200">
            <i class="fas fa-list-alt mr-2"></i>Sales List
        </a>
    </div>

    <form method="GET" class="rounded-xl bg-white p-4 shadow-md">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-6">
            <div class="md:col-span-2">
                <label class="text-xs font-semibold text-gray-600">Search</label>
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Invoice, customer, cheque no, bank..." class="mt-1 w-full rounded-lg border px-3 py-2">
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-600">Status</label>
                <select name="status" class="mt-1 w-full rounded-lg border px-3 py-2">
                    <option value="">All</option>
                    @foreach(['pending', 'passed', 'returned'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-600">Date From</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="mt-1 w-full rounded-lg border px-3 py-2">
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-600">Date To</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="mt-1 w-full rounded-lg border px-3 py-2">
            </div>
            <div class="flex items-end gap-2">
                <button class="rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">Filter</button>
                <a href="{{ route('cheque-payments.index') }}" class="rounded-lg bg-gray-100 px-4 py-2 text-gray-700 hover:bg-gray-200">Reset</a>
            </div>
        </div>
    </form>

    <div class="overflow-hidden rounded-xl bg-white shadow-md">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="border-b bg-gray-50">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase text-gray-600">Pass Date</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase text-gray-600">Cheque Details</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase text-gray-600">Customer</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase text-gray-600">Invoice</th>
                        <th class="px-6 py-4 text-right text-xs font-semibold uppercase text-gray-600">Amount</th>
                        <th class="px-6 py-4 text-center text-xs font-semibold uppercase text-gray-600">Status</th>
                        <th class="px-6 py-4 text-center text-xs font-semibold uppercase text-gray-600">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($cheques as $cheque)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
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
                            <td class="px-6 py-4">
                                <div class="font-semibold text-gray-800">No: {{ $cheque->cheque_number }}</div>
                                <div class="text-xs text-gray-500">Bank: {{ $cheque->bank_name ?: '-' }}</div>
                                <div class="text-xs text-gray-500">Name: {{ $cheque->account_name ?: '-' }}</div>
                            </td>
                            <td class="px-6 py-4">
                                {{ $cheque->customer?->name ?? $cheque->sale?->customer?->name ?? 'Walk-in Customer' }}
                            </td>
                            <td class="px-6 py-4">
                                @if($cheque->sale)
                                    <a href="{{ route('sales.show', $cheque->sale_id) }}" class="font-mono font-semibold text-blue-600 hover:underline">{{ $cheque->sale->sale_no }}</a>
                                @else
                                    <span class="text-gray-500">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right font-semibold text-gray-800">
                                {{ trim($currency) }} {{ number_format((float) $cheque->amount, 2) }}
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $statusClasses[$cheque->status] ?? 'bg-gray-100 text-gray-700' }}">
                                    {{ ucfirst($cheque->status) }}
                                </span>
                                @if($cheque->auto_passed)
                                    <div class="mt-1 text-[11px] font-semibold text-green-600">Auto passed</div>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-center gap-2">
                                    @if($canManage && $cheque->status === 'pending')
                                        <form method="POST" action="{{ route('cheque-payments.pass', $cheque) }}" class="js-cheque-action-form" data-action-label="pass" data-cheque-date="{{ $cheque->cheque_date?->format('Y-m-d') }}">
                                            @csrf
                                            <button class="rounded-lg bg-green-600 px-3 py-2 text-xs font-semibold text-white hover:bg-green-700">
                                                <i class="fas fa-check mr-1"></i>Pass
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('cheque-payments.return', $cheque) }}" class="js-cheque-action-form" data-action-label="return" data-cheque-date="{{ $cheque->cheque_date?->format('Y-m-d') }}">
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

<div id="cheque-confirm-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 px-4">
    <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
        <div class="mb-4 flex items-start justify-between gap-4">
            <div>
                <h3 id="cheque-confirm-title" class="text-lg font-bold text-gray-900">Confirm Cheque Action</h3>
                <p id="cheque-confirm-message" class="mt-2 text-sm text-gray-600"></p>
            </div>
            <button type="button" class="text-gray-400 hover:text-gray-600" data-cheque-confirm-cancel>
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="flex justify-end gap-3">
            <button type="button" class="rounded-lg bg-gray-100 px-4 py-2 text-gray-700 hover:bg-gray-200" data-cheque-confirm-cancel>Cancel</button>
            <button type="button" id="cheque-confirm-submit" class="rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">Confirm</button>
        </div>
    </div>
</div>

@push('scripts')
<script>
(() => {
    const modal = document.getElementById('cheque-confirm-modal');
    const message = document.getElementById('cheque-confirm-message');
    const submitButton = document.getElementById('cheque-confirm-submit');
    let pendingForm = null;

    const closeModal = () => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        pendingForm = null;
    };

    document.querySelectorAll('[data-cheque-confirm-cancel]').forEach((button) => button.addEventListener('click', closeModal));
    submitButton?.addEventListener('click', () => {
        if (pendingForm) {
            pendingForm.submit();
        }
    });

    document.querySelectorAll('.js-cheque-action-form').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const chequeDate = form.dataset.chequeDate;
            const actionLabel = form.dataset.actionLabel || 'process';
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const chequeDay = chequeDate ? new Date(`${chequeDate}T00:00:00`) : null;

            if (chequeDay && chequeDay > today) {
                event.preventDefault();
                pendingForm = form;
                message.textContent = `This cheque date is ${chequeDate}. Do you really want to ${actionLabel} it before the cheque date?`;
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            }
        });
    });
})();
</script>
@endpush
@endsection
