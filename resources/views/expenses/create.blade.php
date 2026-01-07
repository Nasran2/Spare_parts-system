@extends('layouts.app')

@section('title','Add Expense')
@section('page-title','Add Expense')

@section('content')
<div class="max-w-3xl">
    <div class="bg-white rounded-xl shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Record New Expense</h3>
        <form action="{{ route('expenses.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Date *</label>
                    <input type="date" name="expense_date" value="{{ old('expense_date', date('Y-m-d')) }}" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 @error('expense_date') border-red-500 @enderror">
                    @error('expense_date')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Category * </label>
                    <div class="flex gap-2">
                        <select id="expense_category_id" name="expense_category_id" required class="flex-1 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 @error('expense_category_id') border-red-500 @enderror">
                            <option value="">Select Category</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" {{ old('expense_category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                            @endforeach
                        </select>
                        <button type="button" onclick="openExpenseCategoryModal()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700" title="Add New Expense Category">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    @error('expense_category_id')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Amount *</label>
                    <input type="number" step="0.01" min="0" name="amount" value="{{ old('amount') }}" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 @error('amount') border-red-500 @enderror" placeholder="0.00">
                    @error('amount')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Receipt (Image/PDF)</label>
                    <input type="file" name="receipt" accept="image/*,application/pdf" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 @error('receipt') border-red-500 @enderror">
                    @error('receipt')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                    <textarea name="description" rows="3" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 @error('description') border-red-500 @enderror" placeholder="Expense details...">{{ old('description') }}</textarea>
                    @error('description')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="flex items-center justify-end space-x-4 pt-4 border-t">
                <a href="{{ route('expenses.index') }}" class="px-5 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">Cancel</a>
                <button type="submit" class="px-6 py-2 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg hover:from-blue-700 hover:to-blue-800 shadow">Save Expense</button>
            </div>
        </form>
    </div>
</div>
@endsection
@section('extra-modals')
<!-- Expense Category Modal -->
<div id="expenseCategoryModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-md w-full">
        <div class="bg-gradient-to-r from-blue-600 to-blue-700 p-4 text-white rounded-t-xl flex justify-between items-center">
            <h3 class="text-lg font-bold"><i class="fas fa-folder-open mr-2"></i>Add Expense Category</h3>
            <button onclick="closeExpenseCategoryModal()" class="text-white hover:text-gray-200">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <form id="expenseCategoryForm" class="p-6 space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Name *</label>
                <input type="text" id="expense_category_name" name="name" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500" required>
                <p id="expense_category_error" class="text-red-500 text-xs mt-1 hidden"></p>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                <textarea id="expense_category_description" name="description" rows="3" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
            </div>
            <div class="flex gap-3 pt-4">
                <button type="submit" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-semibold">
                    <i class="fas fa-save mr-2"></i>Save
                </button>
                <button type="button" onclick="closeExpenseCategoryModal()" class="flex-1 px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 font-semibold">Cancel</button>
            </div>
        </form>
    </div>
</div>
@endsection
@section('scripts')
<script>
function openExpenseCategoryModal() {
    document.getElementById('expenseCategoryModal').classList.remove('hidden');
    document.getElementById('expense_category_name').value='';
    document.getElementById('expense_category_description').value='';
    document.getElementById('expense_category_error').classList.add('hidden');
}
function closeExpenseCategoryModal(){
    document.getElementById('expenseCategoryModal').classList.add('hidden');
}
document.getElementById('expenseCategoryForm').addEventListener('submit', async function(e){
    e.preventDefault();
    const formData = new FormData(this);
    try {
        const response = await fetch('{{ route("expense-categories.store") }}', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With':'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content || formData.get('_token')
            }
        });
        const data = await response.json();
        if(data.success){
            const select = document.getElementById('expense_category_id');
            const option = new Option(data.category.name, data.category.id, true, true);
            select.add(option);
            closeExpenseCategoryModal();
            alert('Expense category created successfully!');
        } else {
            document.getElementById('expense_category_error').textContent = data.message || 'Error creating category';
            document.getElementById('expense_category_error').classList.remove('hidden');
        }
    } catch (err){
        document.getElementById('expense_category_error').textContent = 'Error creating category';
        document.getElementById('expense_category_error').classList.remove('hidden');
    }
});
</script>
@endsection