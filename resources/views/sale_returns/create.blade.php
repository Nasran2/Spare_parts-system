@extends('layouts.app')

@section('title', 'Create Sale Return')
@section('page-title', 'Create Sale Return')

@section('content')
<div class="space-y-6">
    
    <!-- Step 1: Select Sale -->
    <div class="bg-white rounded-xl shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">1. Select Sale</h3>
        
        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline">{{ session('error') }}</span>
            </div>
        @endif

        <form action="{{ route('sale-returns.create') }}" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="col-span-1">
                <label class="block text-sm font-medium text-gray-700 mb-1">Sale ID / Number</label>
                <input 
                    type="text" 
                    name="sale_id" 
                    value="{{ request('sale_id') }}"
                    placeholder="e.g. INV-2025..."
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                >
            </div>
            
            <div class="col-span-1">
                <label class="block text-sm font-medium text-gray-700 mb-1">Customer</label>
                <select name="customer_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">All Customers</option>
                    @foreach($customers as $customer)
                        <option value="{{ $customer->id }}" {{ request('customer_id') == $customer->id ? 'selected' : '' }}>
                            {{ $customer->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-span-1">
                <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                <input 
                    type="date" 
                    name="date" 
                    value="{{ request('date') }}"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                >
            </div>

            <div class="col-span-1 flex items-end">
                <button type="submit" class="w-full px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    <i class="fas fa-search mr-2"></i>Search
                </button>
            </div>
        </form>
    </div>

    @if(isset($sales) && $sales->count() > 0)
    <!-- Search Results -->
    <div class="bg-white rounded-xl shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Search Results</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50 border-b">
                        <th class="p-3 font-semibold text-gray-600">Sale No</th>
                        <th class="p-3 font-semibold text-gray-600">Date</th>
                        <th class="p-3 font-semibold text-gray-600">Customer</th>
                        <th class="p-3 font-semibold text-gray-600">Total</th>
                        <th class="p-3 font-semibold text-gray-600">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($sales as $s)
                    <tr class="border-b hover:bg-gray-50">
                        <td class="p-3">{{ $s->sale_no }}</td>
                        <td class="p-3">{{ $s->sale_date->format('Y-m-d') }}</td>
                        <td class="p-3">{{ $s->customer->name ?? 'Walk-in' }}</td>
                        <td class="p-3">{{ number_format($s->total_amount, 2) }}</td>
                        <td class="p-3">
                            <a href="{{ route('sale-returns.create', ['sale_id' => $s->id]) }}" class="text-blue-600 hover:text-blue-800 font-medium">
                                Select
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    @if(isset($sale))
    <!-- Step 2: Select Items -->
    <div class="bg-white rounded-xl shadow-md p-6">
        <div class="flex justify-between items-start mb-6">
            <div>
                <h3 class="text-lg font-semibold text-gray-800">2. Select Items to Return</h3>
                <p class="text-sm text-gray-600">Sale #{{ $sale->sale_no ?? $sale->id }} - {{ $sale->customer->name ?? 'Walk-in' }}</p>
                @if($sale->customer && $sale->customer->due_amount > 0)
                    <p class="text-sm text-red-600 font-semibold mt-1">
                        Current Due Amount: {{ number_format($sale->customer->due_amount, 2) }}
                    </p>
                @endif
            </div>
            <div class="text-right">
                <p class="text-sm text-gray-500">Sale Date: {{ $sale->sale_date->format('M d, Y') }}</p>
            </div>
        </div>

        <form action="{{ route('sale-returns.store') }}" method="POST">
            @csrf
            <input type="hidden" name="sale_id" value="{{ $sale->id }}">

            <div class="overflow-x-auto mb-6">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Product</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Sold Qty</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Unit Price</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Return Qty</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Refund Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($sale->items as $index => $item)
                        @php
                            $returnedQty = \App\Models\SaleReturnItem::where('sale_item_id', $item->id)->sum('quantity');
                            $remainingQty = $item->quantity - $returnedQty;
                        @endphp
                        <tr class="{{ $remainingQty == 0 ? 'bg-gray-100 opacity-60' : '' }}">
                            <td class="px-6 py-4 text-sm text-gray-800">
                                {{ $item->product->name }}
                                @if($returnedQty > 0)
                                    <div class="text-xs text-red-600 mt-1">Returned: {{ $returnedQty }}</div>
                                @endif
                                <input type="hidden" name="items[{{ $index }}][id]" value="{{ $item->id }}">
                            </td>
                            <td class="px-6 py-4 text-sm text-right text-gray-600">
                                {{ $item->quantity }}
                            </td>
                            <td class="px-6 py-4 text-sm text-right text-gray-600">
                                {{ number_format($item->unit_price, 2) }}
                                <input type="hidden" class="unit-price" value="{{ $item->unit_price }}">
                            </td>
                            <td class="px-6 py-4 text-right">
                                <input 
                                    type="number" 
                                    name="items[{{ $index }}][quantity]" 
                                    min="0" 
                                    max="{{ $remainingQty }}" 
                                    value="0"
                                    class="w-24 px-2 py-1 border border-gray-300 rounded text-right return-qty"
                                    onchange="calculateTotal()"
                                    {{ $remainingQty == 0 ? 'disabled' : '' }}
                                >
                            </td>
                            <td class="px-6 py-4 text-sm text-right font-semibold text-gray-800 line-total">
                                0.00
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <td colspan="4" class="px-6 py-4 text-right font-bold text-gray-700">Total Refund:</td>
                            <td class="px-6 py-4 text-right font-bold text-red-600" id="total-refund">0.00</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Return Action Selection -->
            <div class="bg-gray-50 rounded-xl p-6 mb-6 border border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">3. Return Action</h3>
                <div class="flex items-center space-x-6">
                    <label class="inline-flex items-center cursor-pointer">
                        <input type="radio" name="return_action" value="refund" class="form-radio text-blue-600 h-5 w-5" checked onchange="toggleExchangeSection()">
                        <span class="ml-2 text-gray-700 font-medium">Refund Only (Cash/Credit)</span>
                    </label>
                    <label class="inline-flex items-center cursor-pointer">
                        <input type="radio" name="return_action" value="exchange" class="form-radio text-blue-600 h-5 w-5" onchange="toggleExchangeSection()">
                        <span class="ml-2 text-gray-700 font-medium">Exchange / Swap Items</span>
                    </label>
                </div>
            </div>

            <!-- Exchange Section -->
            <div id="exchange-section" class="bg-blue-50 rounded-xl p-6 mb-6 border border-blue-100 hidden">
                <h3 class="text-lg font-semibold text-blue-800 mb-4">Exchange / Add New Items</h3>
                
                <!-- Product Search -->
                <div class="relative mb-4">
                    <input 
                        type="text" 
                        id="product-search" 
                        placeholder="Search products to exchange..."
                        class="w-full pl-10 pr-4 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-500"
                    >
                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-blue-400"></i>
                    <div id="search-results" class="absolute z-10 w-full bg-white shadow-lg rounded-lg mt-1 hidden max-h-60 overflow-y-auto border border-gray-200"></div>
                </div>

                <!-- New Items Table -->
                <div class="overflow-x-auto bg-white rounded-lg border border-gray-200 mb-4">
                    <table class="w-full" id="exchange-table">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Product</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600">Price</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600">Qty</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600">Total</th>
                                <th class="px-4 py-2 text-center text-xs font-semibold text-gray-600">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200" id="exchange-items-body">
                            <!-- Items added via JS -->
                        </tbody>
                        <tfoot class="bg-gray-50">
                            <tr>
                                <td colspan="3" class="px-4 py-2 text-right font-bold text-gray-700">Total New Items:</td>
                                <td class="px-4 py-2 text-right font-bold text-blue-600" id="total-exchange">0.00</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Net Calculation -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 bg-white p-4 rounded-lg border border-blue-200">
                    <div class="space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Total Refund (Return):</span>
                            <span class="font-semibold text-red-600" id="summary-refund">0.00</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Total New Items (Exchange):</span>
                            <span class="font-semibold text-blue-600" id="summary-exchange">0.00</span>
                        </div>
                        <div class="flex justify-between text-lg font-bold border-t pt-2 mt-2">
                            <span id="net-label">Net Payable:</span>
                            <span id="net-amount">0.00</span>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <div id="payment-section" class="hidden">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Customer Pays (Difference)</label>
                            <input type="number" name="exchange_paid" id="exchange-paid" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="Amount Paid">
                        </div>
                        <div id="refund-section" class="hidden">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Refund Method</label>
                            <select name="refund_method" id="refund-method" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                <option value="cash">Cash Refund</option>
                                <option value="account">Deduct from Due</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Notes</label>
                    <textarea name="notes" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold shadow-lg">
                    Process Return / Exchange
                </button>
            </div>
        </form>
    </div>
    @endif
</div>

<script>
let exchangeItems = [];

function calculateTotal() {
    let totalRefund = 0;
    const rows = document.querySelectorAll('.return-qty');
    
    rows.forEach(input => {
        const row = input.closest('tr');
        const price = parseFloat(row.querySelector('.unit-price').value);
        const qty = parseInt(input.value) || 0;
        const lineTotal = price * qty;
        
        row.querySelector('.line-total').textContent = lineTotal.toFixed(2);
        totalRefund += lineTotal;
    });
    
    document.getElementById('total-refund').textContent = totalRefund.toFixed(2);
    updateNetSummary(totalRefund);
}

function updateNetSummary(totalRefund) {
    let totalExchange = 0;
    
    // Check if exchange is active
    const isExchange = document.querySelector('input[name="return_action"][value="exchange"]').checked;
    
    if (isExchange) {
        exchangeItems.forEach(item => {
            totalExchange += item.price * item.qty;
        });
    }

    document.getElementById('total-exchange').textContent = totalExchange.toFixed(2);
    document.getElementById('summary-refund').textContent = totalRefund.toFixed(2);
    document.getElementById('summary-exchange').textContent = totalExchange.toFixed(2);

    const net = totalExchange - totalRefund;
    const netLabel = document.getElementById('net-label');
    const netAmount = document.getElementById('net-amount');
    const paymentSection = document.getElementById('payment-section');
    const refundSection = document.getElementById('refund-section');

    if (net > 0) {
        netLabel.textContent = "Customer Pays:";
        netLabel.className = "text-blue-800";
        netAmount.textContent = net.toFixed(2);
        netAmount.className = "text-blue-800";
        paymentSection.classList.remove('hidden');
        refundSection.classList.add('hidden');
        document.getElementById('exchange-paid').value = net.toFixed(2);
    } else if (net < 0) {
        netLabel.textContent = "Refund Amount:";
        netLabel.className = "text-red-800";
        netAmount.textContent = Math.abs(net).toFixed(2);
        netAmount.className = "text-red-800";
        paymentSection.classList.add('hidden');
        refundSection.classList.remove('hidden');
    } else {
        netLabel.textContent = "Balanced:";
        netAmount.textContent = "0.00";
        paymentSection.classList.add('hidden');
        refundSection.classList.add('hidden');
    }
}

// Product Search Logic
const searchInput = document.getElementById('product-search');
const searchResults = document.getElementById('search-results');

if (searchInput) {
    searchInput.addEventListener('input', debounce(async (e) => {
        const term = e.target.value;
        if (term.length < 2) {
            searchResults.classList.add('hidden');
            return;
        }

        // Use existing POS search or create a new endpoint. 
        // For now, let's assume we can use a simple product search API or reuse POS search logic if available.
        // Since we don't have a dedicated JSON search endpoint visible, I'll mock it or use a known one.
        // Actually, let's use the POS search endpoint if it exists, or create a quick one.
        // I'll assume there is a route 'products.index' that accepts JSON, or I'll use a new one.
        // Let's try to fetch from a new endpoint I'll create in controller, or just fetch all products for now if list is small? No.
        
        // Let's use a simple fetch to a new route I will add: /api/products/search
        const res = await fetch(`/pos/search-products?term=${term}`, {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        
        if (res.ok) {
            const products = await res.json();
            renderSearchResults(products);
        }
    }, 300));
}

function renderSearchResults(products) {
    searchResults.innerHTML = '';
    if (products.length === 0) {
        searchResults.innerHTML = '<div class="p-3 text-gray-500 text-sm">No products found</div>';
    } else {
        products.forEach(p => {
            const div = document.createElement('div');
            div.className = 'p-3 hover:bg-blue-50 cursor-pointer border-b last:border-b-0';
            div.innerHTML = `
                <div class="font-semibold text-sm text-gray-800">${p.name}</div>
                <div class="text-xs text-gray-500">Price: ${p.selling_price} | Stock: ${p.stock_quantity}</div>
            `;
            div.onclick = () => addExchangeItem(p);
            searchResults.appendChild(div);
        });
    }
    searchResults.classList.remove('hidden');
}

function addExchangeItem(product) {
    const existing = exchangeItems.find(i => i.id === product.id);
    if (existing) {
        existing.qty++;
    } else {
        exchangeItems.push({
            id: product.id,
            name: product.name,
            price: parseFloat(product.selling_price),
            qty: 1
        });
    }
    renderExchangeTable();
    searchInput.value = '';
    searchResults.classList.add('hidden');
    calculateTotal(); // Recalculate net
}

function renderExchangeTable() {
    const tbody = document.getElementById('exchange-items-body');
    tbody.innerHTML = '';
    
    exchangeItems.forEach((item, index) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td class="px-4 py-2 text-sm text-gray-800">
                ${item.name}
                <input type="hidden" name="exchange_items[${index}][id]" value="${item.id}">
                <input type="hidden" name="exchange_items[${index}][price]" value="${item.price}">
            </td>
            <td class="px-4 py-2 text-sm text-right text-gray-600">${item.price.toFixed(2)}</td>
            <td class="px-4 py-2 text-right">
                <input type="number" name="exchange_items[${index}][qty]" value="${item.qty}" min="1" 
                    class="w-16 px-2 py-1 border border-gray-300 rounded text-right"
                    onchange="updateExchangeQty(${index}, this.value)">
            </td>
            <td class="px-4 py-2 text-sm text-right font-semibold text-gray-800">
                ${(item.price * item.qty).toFixed(2)}
            </td>
            <td class="px-4 py-2 text-center">
                <button type="button" class="text-red-600 hover:text-red-800" onclick="removeExchangeItem(${index})">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function updateExchangeQty(index, qty) {
    exchangeItems[index].qty = parseInt(qty) || 1;
    renderExchangeTable();
    calculateTotal();
}

function removeExchangeItem(index) {
    exchangeItems.splice(index, 1);
    renderExchangeTable();
    calculateTotal();
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Close search results on click outside
document.addEventListener('click', function(e) {
    if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
        searchResults.classList.add('hidden');
    }
});

function toggleExchangeSection() {
    const action = document.querySelector('input[name="return_action"]:checked').value;
    const section = document.getElementById('exchange-section');
    // Select all inputs that should be disabled/enabled. 
    // We need to be careful not to disable the search input if we want to keep it usable when re-enabled, 
    // but actually we want to disable everything in that section.
    const inputs = section.querySelectorAll('input, select, button');
    
    if (action === 'exchange') {
        section.classList.remove('hidden');
        inputs.forEach(el => el.disabled = false);
    } else {
        section.classList.add('hidden');
        inputs.forEach(el => el.disabled = true);
    }
    calculateTotal(); // Recalculate net
}
</script>
@endsection
