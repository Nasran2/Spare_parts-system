@extends('layouts.app')

@section('title', 'Expenses')
@section('page-title', 'Expense Management')

@section('content')
<div class="space-y-6">
    
    <!-- Header Actions -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Expense Management</h3>
            <p class="text-sm text-gray-600">Track and manage business expenses</p>
        </div>
        <a href="{{ route('expenses.create') }}" class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg hover:from-blue-700 hover:to-blue-800 transition shadow-lg">
            <i class="fas fa-plus mr-2"></i>Add New Expense
        </a>
    </div>

    <!-- Expenses Table -->
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Date</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Category</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Description</th>
                        <th class="px-6 py-4 text-right text-xs font-semibold text-gray-600 uppercase">Amount</th>
                        <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase">Payment</th>
                        <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($expenses as $expense)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-6 py-4">
                            <span class="text-sm text-gray-600">{{ $expense->expense_date->format('M d, Y') }}</span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-3 py-1 bg-purple-100 text-purple-700 rounded-full text-xs font-semibold">
                                {{ $expense->category->name ?? 'Uncategorized' }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-gray-800">{{ $expense->description }}</span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <span class="font-semibold text-red-600">${{ number_format($expense->amount, 2) }}</span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="px-3 py-1 {{ ($expense->payment_method ?? 'cash') === 'bank_transfer' ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-700' }} rounded-full text-xs font-semibold">
                                {{ ($expense->payment_method ?? 'cash') === 'bank_transfer' ? 'Bank' : 'Cash' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <div class="flex items-center justify-center space-x-2">
                                <a href="{{ route('expenses.show',$expense) }}" class="p-2 text-gray-600 hover:bg-gray-50 rounded-lg" title="View"><i class="fas fa-eye"></i></a>
                                <a href="{{ route('expenses.edit',$expense) }}" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg" title="Edit"><i class="fas fa-edit"></i></a>
                                <form action="{{ route('expenses.destroy',$expense) }}" method="POST" onsubmit="return confirm('Delete this expense?');" class="inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="p-2 text-red-600 hover:bg-red-50 rounded-lg" title="Delete"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center">
                            <i class="fas fa-wallet text-6xl text-gray-300 mb-4"></i>
                            <p class="text-gray-500 text-lg">No expenses found</p>
                            <a href="{{ route('expenses.create') }}" class="mt-4 inline-flex px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                                <i class="fas fa-plus mr-2"></i>Add Expense
                            </a>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Scripts can be added later for dynamic features -->
@endsection
