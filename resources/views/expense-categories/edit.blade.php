@extends('layouts.app')
@section('title','Edit Expense Category')
@section('page-title','Edit Expense Category')
@section('content')
<div class="max-w-xl">
    <div class="bg-white rounded-xl shadow p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Edit Expense Category</h3>
        <form action="{{ route('expense-categories.update',$category) }}" method="POST" class="space-y-5">
            @csrf @method('PUT')
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Name *</label>
                <input type="text" name="name" value="{{ old('name',$category->name) }}" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 @error('name') border-red-500 @enderror">
                @error('name')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                <textarea name="description" rows="3" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 @error('description') border-red-500 @enderror">{{ old('description',$category->description) }}</textarea>
                @error('description')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Expense Limit (0 for no limit)</label>
                <input type="number" step="0.01" name="limit" value="{{ old('limit', $category->limit) }}" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 @error('limit') border-red-500 @enderror">
                @error('limit')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Reset Frequency</label>
                    <select name="reset_frequency" id="reset_frequency" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="lifetime" {{ old('reset_frequency', $category->reset_frequency) == 'lifetime' ? 'selected' : '' }}>Lifetime</option>
                        <option value="monthly" {{ old('reset_frequency', $category->reset_frequency) == 'monthly' ? 'selected' : '' }}>Monthly</option>
                    </select>
                </div>
                <div id="reset_date_wrapper" class="{{ old('reset_frequency', $category->reset_frequency) == 'monthly' ? '' : 'hidden' }}">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Reset Date (Day of Month)</label>
                    <input type="number" min="1" max="31" name="reset_date" value="{{ old('reset_date', $category->reset_date) }}" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    <p class="text-xs text-gray-500 mt-1">Day of the month to reset limit (1-31)</p>
                </div>
            </div>

            <div class="flex items-center space-x-2">
                <input type="checkbox" name="is_active" value="1" {{ $category->is_active ? 'checked' : '' }} class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <label class="text-sm text-gray-700">Active</label>
            </div>
            <div class="flex items-center justify-end space-x-3 pt-4 border-t">
                <a href="{{ route('expense-categories.index') }}" class="px-5 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">Cancel</a>
                <button type="submit" class="px-6 py-2 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg hover:from-blue-700 hover:to-blue-800">Update Category</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.getElementById('reset_frequency').addEventListener('change', function() {
        const wrapper = document.getElementById('reset_date_wrapper');
        if (this.value === 'monthly') {
            wrapper.classList.remove('hidden');
        } else {
            wrapper.classList.add('hidden');
        }
    });
</script>
@endpush