@extends('layouts.app')

@section('title', 'Receive Report')
@section('page-title', 'Receive Report')

@section('content')
<div class="space-y-6">
    <div class="bg-white rounded-xl shadow-md p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-bold text-gray-800"><i class="fas fa-arrow-down text-green-600 mr-2"></i>Payments Received</h3>
            <div class="flex items-center gap-2">
                <a href="{{ route('reports.receive.csv', request()->all()) }}" target="_blank" rel="noopener" class="px-3 py-2 bg-emerald-600 text-white rounded-lg"><i class="fas fa-file-excel mr-1"></i>Excel</a>
                <a href="{{ route('reports.receive.pdf', request()->all()) }}" target="_blank" rel="noopener" class="px-3 py-2 bg-blue-600 text-white rounded-lg"><i class="fas fa-file-pdf mr-1"></i>PDF</a>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="p-4 bg-blue-50 rounded-lg">
                <div class="text-sm text-gray-600">Total Received</div>
                <div class="text-lg font-bold text-gray-800">{{ number_format($summary['total_received'], 2) }}</div>
            </div>
            <div class="p-4 bg-blue-50 rounded-lg">
                <div class="text-sm text-gray-600">Transactions</div>
                <div class="text-lg font-bold text-gray-800">{{ $summary['count'] }}</div>
            </div>
            <div class="p-4 bg-blue-50 rounded-lg">
                <div class="text-sm font-semibold text-gray-700">By Method</div>
                <ul class="text-sm text-gray-700 mt-1">
                    @foreach($summary['by_method'] as $m => $amt)
                        <li>{{ $m ?? 'Unknown' }} — {{ number_format($amt, 2) }}</li>
                    @endforeach
                </ul>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full border rounded-lg">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-sm text-gray-600">Date</th>
                        <th class="px-4 py-2 text-left text-sm text-gray-600">Customer</th>
                        <th class="px-4 py-2 text-left text-sm text-gray-600">Sale</th>
                        <th class="px-4 py-2 text-left text-sm text-gray-600">Method</th>
                        <th class="px-4 py-2 text-left text-sm text-gray-600">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payments as $p)
                        <tr class="border-t">
                            <td class="px-4 py-2">{{ optional($p->payment_date)->toDateString() }}</td>
                            <td class="px-4 py-2">{{ $p->customer?->name ?? '-' }}</td>
                            <td class="px-4 py-2">{{ $p->sale?->invoice_no ?? '-' }}</td>
                            <td class="px-4 py-2">{{ $p->payment_method ?? '-' }}</td>
                            <td class="px-4 py-2">{{ number_format($p->amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-4 text-center text-gray-500">No payments found</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
