@extends('layouts.app')

@section('title','Edit Expense')
@section('page-title','Edit Expense')

@section('content')
<div class="max-w-3xl">
    <div class="bg-white rounded-xl shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Edit Expense</h3>
        <form action="{{ route('expenses.update', $expense) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf
            @method('PUT')
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Date *</label>
                    <input type="date" name="expense_date" value="{{ old('expense_date', $expense->expense_date->format('Y-m-d')) }}" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 @error('expense_date') border-red-500 @enderror">
                    @error('expense_date')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Category *</label>
                    <select name="expense_category_id" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 @error('expense_category_id') border-red-500 @enderror">
                        <option value="">Select Category</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ old('expense_category_id', $expense->expense_category_id) == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                    @error('expense_category_id')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Amount *</label>
                    <input type="number" step="0.01" min="0" name="amount" value="{{ old('amount', $expense->amount) }}" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 @error('amount') border-red-500 @enderror">
                    @error('amount')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Pay From *</label>
                    <select name="payment_method" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 @error('payment_method') border-red-500 @enderror">
                        <option value="cash" {{ old('payment_method', $expense->payment_method ?? 'cash') === 'cash' ? 'selected' : '' }}>Cash</option>
                        <option value="bank_transfer" {{ old('payment_method', $expense->payment_method ?? 'cash') === 'bank_transfer' ? 'selected' : '' }}>Bank</option>
                    </select>
                    @error('payment_method')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Replace Receipt</label>
                    <input type="file" name="receipt" accept="image/*,application/pdf" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 @error('receipt') border-red-500 @enderror">
                    @error('receipt')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                    @if($expense->receipt)
                        <p class="text-xs text-gray-500 mt-1">Current: <a href="{{ asset('storage/'.$expense->receipt) }}" target="_blank" class="text-blue-600 underline">View Receipt</a></p>
                    @endif
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                    <textarea name="description" rows="3" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 @error('description') border-red-500 @enderror">{{ old('description', $expense->description) }}</textarea>
                    @error('description')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="flex items-center justify-end space-x-4 pt-4 border-t">
                <a href="{{ route('expenses.index') }}" class="px-5 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">Cancel</a>
                <button type="submit" class="px-6 py-2 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg hover:from-blue-700 hover:to-blue-800 shadow">Update Expense</button>
            </div>
        </form>
    </div>
</div>
@endsection
