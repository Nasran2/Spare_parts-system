@extends('layouts.app')

@section('title', 'Trending Products')
@section('page-title', 'Trending Products')

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
        <div>
            <button class="bg-blue-600 text-white px-4 py-2 rounded text-sm">Filter</button>
            <a href="{{ route('reports.trending') }}" class="ml-2 text-sm text-gray-600 hover:text-gray-800">Reset</a>
        </div>
    </form>

    <div class="bg-white rounded shadow overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50">
                <tr class="text-left">
                    <th class="px-3 py-2">Product</th>
                    <th class="px-3 py-2">Quantity Sold</th>
                    <th class="px-3 py-2">Revenue</th>
                </tr>
            </thead>
            <tbody>
                @forelse($top as $row)
                    <tr class="border-t">
                        <td class="px-3 py-2 font-medium">{{ $row['product']?->name }}</td>
                        <td class="px-3 py-2">{{ $row['quantity'] }}</td>
                        <td class="px-3 py-2">{{ number_format($row['revenue'],2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-3 py-6 text-center text-gray-500">No data available.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
