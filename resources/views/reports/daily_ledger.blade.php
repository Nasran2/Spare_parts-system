@extends('layouts.app')

@section('title', 'Daily Ledger')
@section('page-title', 'Daily Ledger')

@section('content')
@php
    $maskMoney = function ($value) {
        if (\App\Services\PrivacyModeService::isActiveForUser(auth()->user()) && \App\Services\PrivacyModeService::shouldMaskForCurrentPage()) {
            return \App\Services\PrivacyModeService::maskAmount((float) $value);
        }
        return number_format((float) $value, 2, '.', ',');
    };
@endphp
<div class="space-y-6">
    <form method="get" class="bg-white p-4 rounded shadow flex flex-wrap gap-4 items-end">
        <div>
            <label class="text-sm font-medium text-gray-600">From</label>
            <input type="date" name="from" value="{{ request('from', $from) }}" class="mt-1 border rounded px-3 py-2 text-sm w-48" />
        </div>
        <div>
            <label class="text-sm font-medium text-gray-600">To</label>
            <input type="date" name="to" value="{{ request('to', $to) }}" class="mt-1 border rounded px-3 py-2 text-sm w-48" />
        </div>
        @include('partials.quick-date-filter', [
            'fromName' => 'from',
            'toName' => 'to',
            'labelClass' => 'text-sm font-medium text-gray-600',
            'selectClass' => 'mt-1 border rounded px-3 py-2 text-sm w-48',
        ])
        <div class="flex items-center gap-2">
            <button class="bg-blue-600 text-white px-4 py-2 rounded text-sm">Filter</button>
            <a href="{{ route('reports.daily-ledger') }}" class="text-sm text-gray-600 hover:text-gray-800">Reset</a>
            <a href="{{ route('reports.daily-ledger.csv', request()->all()) }}" target="_blank" rel="noopener" class="px-3 py-2 bg-emerald-600 text-white rounded text-sm"><i class="fas fa-file-excel mr-1"></i>Excel</a>
            <a href="{{ route('reports.daily-ledger.pdf', request()->all()) }}" target="_blank" rel="noopener" class="px-3 py-2 bg-blue-600 text-white rounded text-sm"><i class="fas fa-file-pdf mr-1"></i>PDF</a>
        </div>
    </form>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded shadow border-l-4 border-blue-500">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Main Account (Cash)</p>
            <p class="text-2xl font-bold text-gray-800 mt-1">{{ $maskMoney($mainAccountBalance) }}</p>
        </div>
        <div class="bg-white p-4 rounded shadow border-l-4 border-orange-500">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Petty Cash Balance</p>
            <p class="text-2xl font-bold text-gray-800 mt-1">{{ $maskMoney($pettyCashBalance) }}</p>
        </div>
        <div class="bg-white p-4 rounded shadow border-l-4 border-emerald-500">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Period Income (In)</p>
            <p class="text-2xl font-bold text-emerald-600 mt-1">{{ $maskMoney($totalIn) }}</p>
        </div>
        <div class="bg-white p-4 rounded shadow border-l-4 border-red-500">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Period Expenses (Out)</p>
            <p class="text-2xl font-bold text-red-600 mt-1">{{ $maskMoney($totalExpensePeriod) }}</p>
        </div>
    </div>

    <div class="bg-white rounded shadow p-4">
        <h4 class="font-semibold mb-4 text-gray-800 text-lg border-b pb-2"><i class="fas fa-book mr-2 text-gray-600"></i>Daily Ledger Transactions</h4>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 border-b">
                    <tr class="text-left text-gray-600">
                        <th class="px-4 py-3 font-medium">Date</th>
                        <th class="px-4 py-3 font-medium">Type</th>
                        <th class="px-4 py-3 font-medium">Account</th>
                        <th class="px-4 py-3 font-medium">Related Account</th>
                        <th class="px-4 py-3 font-medium">Reference</th>
                        <th class="px-4 py-3 font-medium">Description</th>
                        <th class="px-4 py-3 font-medium text-right text-emerald-700">Money In (Dr)</th>
                        <th class="px-4 py-3 font-medium text-right text-red-700">Money Out (Cr)</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                @php
                    $timezone = config('app.timezone', 'Asia/Colombo');
                @endphp
                @forelse($transactions as $t)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3 text-gray-700 whitespace-nowrap">{{ $t->transaction_date ? $t->transaction_date->format('Y-m-d') : '' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span class="px-2 py-1 rounded text-xs bg-gray-100 text-gray-700 border">{{ ucfirst(str_replace('_', ' ', $t->source_type)) }}</span>
                        </td>
                        <td class="px-4 py-3 text-gray-700 font-medium">{{ $t->account?->name }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $t->relatedAccount?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $t->reference_no ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600 text-xs">{{ $t->description ?? '—' }}</td>
                        <td class="px-4 py-3 text-right text-emerald-600 font-medium whitespace-nowrap">
                            {{ $t->direction === 'in' ? $maskMoney($t->amount) : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right text-red-600 font-medium whitespace-nowrap">
                            {{ $t->direction === 'out' ? $maskMoney($t->amount) : '—' }}
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-8 text-center text-gray-500 bg-gray-50 rounded"><i class="fas fa-folder-open text-3xl mb-3 text-gray-300 block"></i>No transactions found for the selected date range.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
