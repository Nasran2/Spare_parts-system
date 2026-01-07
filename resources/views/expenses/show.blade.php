@extends('layouts.app')
@section('title','Expense Details')
@section('page-title','Expense Details')
@section('content')
<div class="max-w-3xl space-y-6">
    <div class="bg-white rounded-xl shadow p-6">
        <div class="flex items-start justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-800">Expense #{{ $expense->id }}</h3>
            <a href="{{ route('expenses.edit',$expense) }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"><i class="fas fa-edit mr-1"></i>Edit</a>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div><span class="font-medium text-gray-600">Date:</span> {{ $expense->expense_date->format('M d, Y') }}</div>
            <div><span class="font-medium text-gray-600">Category:</span> {{ $expense->category->name ?? 'N/A' }}</div>
            <div><span class="font-medium text-gray-600">Amount:</span> <span class="font-semibold text-red-600">${{ number_format($expense->amount,2) }}</span></div>
            <div><span class="font-medium text-gray-600">Recorded By:</span> {{ $expense->user->name ?? 'N/A' }}</div>
            <div class="md:col-span-2"><span class="font-medium text-gray-600">Description:</span> {{ $expense->description ?? '—' }}</div>
        </div>
        @if($expense->receipt)
        <div class="mt-6">
            <h4 class="text-sm font-semibold text-gray-700 mb-2">Receipt</h4>
            @if(Str::endsWith($expense->receipt, ['.jpg','.jpeg','.png','.gif']))
                <img src="{{ asset('storage/'.$expense->receipt) }}" alt="Receipt" class="max-h-64 rounded border">
            @else
                <a href="{{ asset('storage/'.$expense->receipt) }}" target="_blank" class="text-blue-600 underline">Download Receipt</a>
            @endif
        </div>
        @endif
        <div class="mt-6 flex items-center justify-between">
            <a href="{{ route('expenses.index') }}" class="text-blue-600 hover:underline text-sm">Back to list</a>
            <form action="{{ route('expenses.destroy',$expense) }}" method="POST" onsubmit="return confirm('Delete this expense?');">
                @csrf @method('DELETE')
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm"><i class="fas fa-trash mr-1"></i>Delete</button>
            </form>
        </div>
    </div>
</div>
@endsection