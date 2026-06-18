@extends('layouts.app')

@section('title', 'Customer Ledger')
@section('page-title', 'Customer Ledger')

@section('content')
@php
    $formatVal = function ($val) {
        return is_numeric($val) ? number_format((float) $val, 2) : $val;
    };
@endphp
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-2 text-sm text-gray-600">
            <a href="{{ route('customers.index') }}" class="text-blue-600 hover:underline"><i class="fas fa-arrow-left mr-1"></i>Back to Customers</a>
            <span>/</span>
            <span class="font-semibold text-gray-800">{{ $customer->name }}</span>
        </div>
        <div class="text-right">
            <p class="text-xs text-gray-500">Mobile</p>
            <p class="font-semibold text-gray-800">{{ $customer->phone ?: '—' }}</p>
        </div>
    </div>

    <!-- Tabs -->
    <div class="bg-white rounded-lg shadow border">
        <div class="px-4 pt-4">
            <nav class="flex flex-wrap gap-2 border-b border-gray-200">
                <a class="px-4 py-2 -mb-px border-b-2 border-blue-600 text-blue-600 font-semibold flex items-center gap-2">
                    <i class="fas fa-book"></i>
                    Ledger
                </a>
                <span class="px-4 py-2 text-gray-500 flex items-center gap-2"><i class="fas fa-shopping-cart"></i>Sales</span>
                <span class="px-4 py-2 text-gray-500 flex items-center gap-2"><i class="fas fa-file-alt"></i>Documents & Note</span>
                <span class="px-4 py-2 text-gray-500 flex items-center gap-2"><i class="fas fa-money-bill-wave"></i>Payments</span>
                <span class="px-4 py-2 text-gray-500 flex items-center gap-2"><i class="fas fa-bolt"></i>Activities</span>
                <span class="px-4 py-2 text-gray-500 flex items-center gap-2"><i class="fas fa-user-friends"></i>Contact Persons</span>
            </nav>
        </div>

        <!-- Filters + Header -->
        <div class="p-4 grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="lg:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-4">
                <form method="GET" class="flex items-end gap-2">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600">Date Range</label>
                        <div class="flex gap-2">
                            @include('partials.quick-date-filter', [
                                'fromName' => 'start_date',
                                'toName' => 'end_date',
                                'inline' => true,
                                'selectClass' => 'px-3 py-2 border rounded w-40',
                            ])
                            <input type="date" name="start_date" value="{{ $start }}" class="px-3 py-2 border rounded w-40">
                            <span class="self-center text-gray-500">to</span>
                            <input type="date" name="end_date" value="{{ $end }}" class="px-3 py-2 border rounded w-40">
                            <button class="px-3 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Apply</button>
                        </div>
                    </div>
                    <div class="hidden md:block">
                        <label class="block text-xs font-semibold text-gray-600">Ledger format</label>
                        <div class="flex gap-2 mt-1">
                            <button type="button" class="px-3 py-1.5 text-sm border rounded bg-gray-100">Format 1</button>
                            <button type="button" class="px-3 py-1.5 text-sm border rounded">Format 2</button>
                            <button type="button" class="px-3 py-1.5 text-sm border rounded">Format 3</button>
                        </div>
                    </div>
                </form>
                <div>
                    <label class="block text-xs font-semibold text-gray-600">Business Location</label>
                    <select class="px-3 py-2 border rounded w-full max-w-xs">
                        <option>All locations</option>
                    </select>
                </div>
            </div>

            <!-- Account Summary Card -->
            <div class="lg:col-span-1">
                <div class="border rounded-lg overflow-hidden">
                    <div class="bg-blue-600 text-white px-4 py-2 font-semibold">Account Summary</div>
                    <div class="p-4 border-b">
                        <div class="flex items-center gap-2 text-sm text-gray-600 mb-2">
                            <i class="fas fa-info-circle text-blue-500"></i>
                            <span>{{ \Carbon\Carbon::parse($start)->format('m/d/Y') }} To {{ \Carbon\Carbon::parse($end)->format('m/d/Y') }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Total invoice</span>
                            <span class="font-semibold">{{ $currency }} {{ $formatVal($periodTotals['invoice']) }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Total paid</span>
                            <span class="font-semibold">{{ $currency }} {{ $formatVal($periodTotals['paid']) }}</span>
                        </div>
                    </div>
                    <div class="p-4">
                        <div class="text-sm text-gray-600 mb-2">Overall Summary</div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Total invoice</span>
                            <span class="font-semibold">{{ $currency }} {{ $formatVal($overallTotals['invoice']) }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Total paid</span>
                            <span class="font-semibold">{{ $currency }} {{ $formatVal($overallTotals['paid']) }}</span>
                        </div>
                        <div class="flex justify-between text-sm mt-2 pt-2 border-t">
                            <span class="text-gray-700 font-semibold">Balance due</span>
                            <span class="font-bold {{ is_numeric($overallTotals['balance']) && $overallTotals['balance'] > 0 ? 'text-red-600' : 'text-green-600' }}">{{ $currency }} {{ $formatVal($overallTotals['balance']) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recipient info -->
        <div class="px-4">
            <div class="bg-blue-50 border border-blue-200 rounded text-sm w-full max-w-lg">
                <div class="bg-blue-600 text-white px-4 py-1 rounded-t">To:</div>
                <div class="px-4 py-2 text-gray-800">
                    <div class="font-semibold">{{ $customer->name }}</div>
                    <div>{{ $customer->address ?: $customer->city }}</div>
                    <div>Mobile: {{ $customer->phone ?: '—' }}</div>
                </div>
            </div>
        </div>

        <!-- Ledger Table -->
        <div class="p-4">
            <p class="text-center text-xs text-gray-500 mb-2">Showing all invoices and payments between {{ \Carbon\Carbon::parse($start)->format('m/d/Y') }} and {{ \Carbon\Carbon::parse($end)->format('m/d/Y') }}</p>
            <div class="overflow-x-auto border rounded-lg">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-100 text-gray-700">
                        <tr>
                            <th class="px-4 py-2 text-left">Date</th>
                            <th class="px-4 py-2 text-left">Reference No</th>
                            <th class="px-4 py-2 text-left">Type</th>
                            <th class="px-4 py-2 text-left">Location</th>
                            <th class="px-4 py-2 text-center">Payment Status</th>
                            <th class="px-4 py-2 text-right">Debit</th>
                            <th class="px-4 py-2 text-right">Credit</th>
                            <th class="px-4 py-2 text-center">Payment Method</th>
                            <th class="px-4 py-2 text-left">Others</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($transactions as $t)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2">{{ \Carbon\Carbon::parse($t['date'])->format('m/d/Y') }}</td>
                                <td class="px-4 py-2">{{ $t['reference'] }}</td>
                                <td class="px-4 py-2">{{ $t['type'] }}</td>
                                <td class="px-4 py-2">{{ $t['location'] }}</td>
                                <td class="px-4 py-2 text-center">
                                    @php $status = strtolower($t['payment_status'] ?? 'pending'); @endphp
                                    <span class="px-2 py-1 rounded-full text-xs font-semibold
                                        {{ $status === 'paid' ? 'bg-green-100 text-green-700' : ($status === 'partial' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-700') }}">
                                        {{ ucfirst($status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-right">{{ $t['debit'] ? $formatVal($t['debit']) : '—' }}</td>
                                <td class="px-4 py-2 text-right">{{ $t['credit'] ? $formatVal($t['credit']) : '—' }}</td>
                                <td class="px-4 py-2 text-center">{{ $t['payment_method'] ?: '—' }}</td>
                                <td class="px-4 py-2">{{ $t['notes'] ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-6 text-center text-gray-500">No transactions in the selected date range.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <p class="text-xs text-gray-400 text-center">Twin Pos - v6.4 | Copyright © {{ now()->year }} All rights reserved.</p>
</div>
@endsection
