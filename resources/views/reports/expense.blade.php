@extends('layouts.app')

@section('title', 'Expense Report')
@section('page-title', 'Expense Report')

@section('content')
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
        <div class="flex items-center gap-2">
            <button class="bg-blue-600 text-white px-4 py-2 rounded text-sm">Filter</button>
            <a href="{{ route('reports.expense') }}" class="text-sm text-gray-600 hover:text-gray-800">Reset</a>
            <a href="{{ route('reports.expense.csv', request()->all()) }}" target="_blank" rel="noopener" class="px-3 py-2 bg-emerald-600 text-white rounded text-sm"><i class="fas fa-file-excel mr-1"></i>Excel</a>
            <a href="{{ route('reports.expense.pdf', request()->all()) }}" target="_blank" rel="noopener" class="px-3 py-2 bg-blue-600 text-white rounded text-sm"><i class="fas fa-file-pdf mr-1"></i>PDF</a>
        </div>
    </form>

    <div class="grid md:grid-cols-3 gap-4">
        <div class="bg-white p-4 rounded shadow">
            <p class="text-xs text-gray-500">Total Expense</p>
            <p class="text-xl font-semibold text-red-600">{{ number_format($summary['total_expense'],2) }}</p>
        </div>
        <div class="bg-white p-4 rounded shadow">
            <p class="text-xs text-gray-500">Entries</p>
            <p class="text-xl font-semibold">{{ $summary['count'] }}</p>
        </div>
        <div class="bg-white p-4 rounded shadow">
            <p class="text-xs text-gray-500">Avg Expense</p>
            <p class="text-xl font-semibold">{{ $summary['count'] ? number_format($summary['total_expense']/$summary['count'],2) : '0.00' }}</p>
        </div>
    </div>

    <div class="bg-white rounded shadow overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50">
                <tr class="text-left">
                    <th class="px-3 py-2">Date</th>
                    <th class="px-3 py-2">Category</th>
                    <th class="px-3 py-2">Description</th>
                    <th class="px-3 py-2">Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse($expenses as $expense)
                    <tr class="border-t">
                        <td class="px-3 py-2">{{ $expense->expense_date?->toDateString() }}</td>
                        <td class="px-3 py-2">{{ $expense->category?->name ?? 'Uncategorized' }}</td>
                        <td class="px-3 py-2 text-gray-600">{{ $expense->description }}</td>
                        <td class="px-3 py-2 text-red-600">{{ number_format($expense->amount,2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-3 py-6 text-center text-gray-500">No expenses found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="bg-white rounded shadow p-4">
        <h4 class="font-semibold mb-2 text-sm">By Category</h4>
        <div class="overflow-x-auto">
            <table class="min-w-full text-xs">
                <thead class="bg-gray-50">
                    <tr class="text-left">
                        <th class="px-2 py-1">Category</th>
                        <th class="px-2 py-1">Entries</th>
                        <th class="px-2 py-1">Total</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($byCategory as $c)
                    <tr class="border-t">
                        <td class="px-2 py-1">{{ $c['category'] }}</td>
                        <td class="px-2 py-1">{{ $c['count'] }}</td>
                        <td class="px-2 py-1">{{ number_format($c['total'],2) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
