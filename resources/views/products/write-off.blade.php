@extends('layouts.app')

@section('title', 'Product Write-off')
@section('page-title', 'Product Write-off')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    
    <!-- Write-off Form -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-xl shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-trash-alt text-red-600 mr-2"></i>Record Write-off
            </h3>
            
            <form action="{{ route('products.write-off.store') }}" method="POST">
                @csrf
                
                <!-- Date -->
                <div class="mb-4">
                    <label for="date" class="block text-sm font-semibold text-gray-700 mb-2">Date *</label>
                    <input 
                        type="date" 
                        id="date" 
                        name="date" 
                        value="{{ date('Y-m-d') }}"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('date') border-red-500 @enderror" 
                        required
                    >
                    @error('date')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Product -->
                <div class="mb-4">
                    <label for="product_id" class="block text-sm font-semibold text-gray-700 mb-2">Product *</label>
                    <select 
                        id="product_id" 
                        name="product_id" 
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('product_id') border-red-500 @enderror"
                        required
                        onchange="updateMaxQuantity(this)"
                    >
                        <option value="">Select Product</option>
                        @foreach($products as $product)
                            <option value="{{ $product->id }}" data-stock="{{ $product->stock_quantity }}">
                                {{ $product->name }} (Stock: {{ $product->stock_quantity }})
                            </option>
                        @endforeach
                    </select>
                    @error('product_id')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Quantity -->
                <div class="mb-4">
                    <label for="quantity" class="block text-sm font-semibold text-gray-700 mb-2">Quantity *</label>
                    <input 
                        type="number" 
                        id="quantity" 
                        name="quantity" 
                        min="1"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('quantity') border-red-500 @enderror" 
                        required
                    >
                    <p class="text-xs text-gray-500 mt-1" id="stock-hint">Select a product to see available stock.</p>
                    @error('quantity')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Reason -->
                <div class="mb-6">
                    <label for="reason" class="block text-sm font-semibold text-gray-700 mb-2">Reason *</label>
                    <textarea 
                        id="reason" 
                        name="reason" 
                        rows="3"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('reason') border-red-500 @enderror" 
                        placeholder="e.g., Damaged during shipping, Expired, etc."
                        required
                    ></textarea>
                    @error('reason')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <button 
                    type="submit" 
                    class="w-full px-6 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition font-semibold shadow-lg"
                >
                    <i class="fas fa-save mr-2"></i>Submit Write-off
                </button>
            </form>
        </div>
    </div>

    <!-- Recent Write-offs List -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">
                    <i class="fas fa-history text-blue-600 mr-2"></i>Recent Write-offs
                </h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Description</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Cost Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">User</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($recentWriteOffs as $expense)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                {{ $expense->expense_date->format('M d, Y') }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700">
                                {{ $expense->description }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-red-600 font-semibold text-right">
                                {{ number_format($expense->amount, 2) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $expense->user->name ?? 'Unknown' }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="px-6 py-8 text-center text-gray-500">
                                No recent write-offs found.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    function updateMaxQuantity(select) {
        const option = select.options[select.selectedIndex];
        const stock = option.getAttribute('data-stock');
        const quantityInput = document.getElementById('quantity');
        const hint = document.getElementById('stock-hint');
        
        if (stock) {
            quantityInput.max = stock;
            hint.textContent = `Available Stock: ${stock}`;
            hint.classList.remove('text-red-500');
            hint.classList.add('text-gray-500');
        } else {
            quantityInput.removeAttribute('max');
            hint.textContent = 'Select a product to see available stock.';
        }
    }
</script>
@endsection
