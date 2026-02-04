@extends('layouts.app')

@section('title', 'POS System')
@section('page-title', 'Point of Sale')

@section('content')
<div class="space-y-4">
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Products Section -->
        <div class="lg:col-span-2 space-y-4">
            <!-- Search -->
            <div class="bg-white rounded-xl shadow-md p-4">
                <div class="relative">
                    <input 
                        id="product-search"
                        type="text" 
                        placeholder="Search products by name, SKU, or barcode..."
                        class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                </div>
            </div>

            <!-- Products Grid -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">
                    <i class="fas fa-box text-blue-600 mr-2"></i>Products
                </h3>
                @php
                    $products = \App\Models\Product::with(['unit', 'categories', 'brands'])
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->get();
                    $productPayload = $products->map(function ($product) {
                        return [
                            'id' => $product->id,
                            'name' => $product->name,
                            'selling_price' => (float)$product->selling_price,
                            'stock_quantity' => (int)($product->stock_quantity ?? 0),
                            'image' => $product->image ? asset('storage/' . $product->image) : null,
                            'categories' => $product->categories->pluck('name')->values()->toArray(),
                            'brands' => $product->brands->pluck('name')->values()->toArray(),
                            'unit' => $product->unit ? [
                                'id' => $product->unit->id,
                                'name' => $product->unit->name,
                                'short_name' => $product->unit->short_name,
                            ] : null,
                            'visible_units' => $product->visible_units ?? [],
                        ];
                    })->values();
                    $allUnits = \App\Models\Unit::where('is_active', true)->get();
                @endphp
                <div id="product-grid" class="grid grid-cols-2 md:grid-cols-3 gap-4 max-h-[600px] overflow-y-auto">
                    <div class="col-span-full text-center py-12 text-gray-500">
                        <i class="fas fa-spinner fa-spin text-blue-500 text-3xl mb-2"></i>
                        Loading products...
                    </div>
                </div>
            </div>
        </div>

        <!-- Cart Section -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-md p-6 sticky top-4">
                <h3 class="text-lg font-bold text-gray-800 mb-4">
                    <i class="fas fa-shopping-cart text-green-600 mr-2"></i>Cart
                </h3>

                <!-- Cart Items -->
                <div id="cart-items" class="space-y-3 mb-4 max-h-64 overflow-y-auto"></div>

                <!-- Cart Summary -->
                <div class="border-t border-gray-200 pt-4 space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Subtotal:</span>
                        <span id="subtotal" class="font-semibold">{{ $currency }} 0.00</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Tax (0%):</span>
                        <span id="tax-amount" class="font-semibold">{{ $currency }} 0.00</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Discount:</span>
                        <span id="discount-amount" class="font-semibold text-red-600">{{ $currency }} 0.00</span>
                    </div>
                    <div class="border-t border-gray-200 pt-2 flex justify-between">
                        <span class="font-bold text-lg">Total:</span>
                        <span id="total" class="font-bold text-2xl text-blue-600">{{ $currency }} 0.00</span>
                    </div>
                </div>

                <!-- Customer Selection -->
                <div class="mt-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Customer</label>
                    <div class="flex items-center space-x-2 mb-2">
                        <label class="text-xs font-semibold text-gray-600">Customer</label>
                        <button id="btn-new-customer" type="button" class="text-xs px-2 py-1 bg-emerald-100 text-emerald-700 rounded hover:bg-emerald-200" title="Add New Customer">
                            <i class="fas fa-user-plus"></i>
                        </button>
                    </div>
                    <select id="customer-select" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">Walk-in Customer</option>
                        @isset($customers)
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        @endisset
                    </select>
                    <div id="customer-due" class="mt-2 hidden text-sm p-2 rounded border bg-yellow-50 border-yellow-200 text-yellow-800">
                        Outstanding Due: <span id="customer-due-amount">{{ $currency }} 0.00</span>
                    </div>
                </div>

                <!-- Discount Controls -->
                <div class="mt-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Discount</label>
                    <div class="flex items-center space-x-3 mb-2">
                        <label class="inline-flex items-center space-x-2">
                            <input type="radio" name="discount_type" value="fixed" class="discount-type" checked>
                            <span>Fixed</span>
                        </label>
                        <label class="inline-flex items-center space-x-2">
                            <input type="radio" name="discount_type" value="percent" class="discount-type">
                            <span>Percent</span>
                        </label>
                    </div>
                    <input type="number" step="0.01" min="0" id="discount-value" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="Enter discount value">
                    <p class="text-xs text-gray-500 mt-1">When Percent is selected, value is treated as % of subtotal.</p>
                </div>

                <!-- Payment Section -->
                <div class="mt-4 border-2 border-emerald-300 rounded-xl bg-emerald-50 p-4">
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-bold text-emerald-800">Payment</label>
                        <span class="text-xs text-emerald-700">Highlight</span>
                    </div>
                    <input type="number" step="0.01" min="0" id="cash-amount" class="w-full px-3 py-2 border-2 border-emerald-300 rounded-lg focus:ring-2 focus:ring-emerald-500" placeholder="Enter cash amount">
                    <div class="grid grid-cols-2 gap-2 mt-3">
                        <div id="change-display" class="p-2 bg-green-50 border border-green-200 rounded-lg hidden">
                            <div class="flex justify-between items-center">
                                <span class="text-xs font-semibold text-gray-700">Change</span>
                                <span id="change-amount" class="text-base font-bold text-green-600">Rs 0.00</span>
                            </div>
                        </div>
                        <div id="due-display" class="p-2 bg-amber-50 border border-amber-200 rounded-lg hidden">
                            <div class="flex justify-between items-center">
                                <span class="text-xs font-semibold text-gray-700">Due</span>
                                <span id="due-amount" class="text-base font-bold text-amber-600">Rs 0.00</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="mt-6 space-y-2">
                    <button id="btn-checkout" class="w-full py-3 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-lg hover:from-green-700 hover:to-green-800 transition shadow-lg font-semibold">
                        <i class="fas fa-check-circle mr-2"></i>Complete Sale
                    </button>
                    <button id="btn-hold" class="w-full py-2 bg-amber-100 text-amber-800 rounded-lg hover:bg-amber-200 transition font-semibold">
                        <i class="fas fa-pause-circle mr-2"></i>Hold Bill
                    </button>
                    <button id="btn-open-holds" class="w-full py-2 bg-blue-50 text-blue-800 rounded-lg hover:bg-blue-100 transition font-semibold">
                        <i class="fas fa-archive mr-2"></i>Held Bills (<span id="hold-count">0</span>)
                    </button>
                    <button id="btn-return-mode" class="w-full py-2 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition" title="Load a previous sale for return/exchange">
                        <i class="fas fa-undo mr-2"></i>Return / Exchange
                    </button>
                    <button id="btn-draft" class="w-full py-2 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition" title="Save cart as a Quotation without affecting stock">
                        <i class="fas fa-file-invoice mr-2"></i>Save as Quotation
                    </button>
                    <button id="btn-print-quotation" class="w-full py-2 bg-indigo-500 text-white rounded-lg hover:bg-indigo-600 transition hidden" title="Print the last saved quotation">
                        <i class="fas fa-print mr-2"></i>Print Quotation
                    </button>
                    <button id="btn-clear" class="w-full py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                        <i class="fas fa-times mr-2"></i>Clear Cart
                    </button>
                </div>
            </div>
        </div>

    </div>

</div>

<!-- Return/Exchange Modal -->
<div id="return-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl mx-4 overflow-hidden">
        <div class="p-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
            <h3 class="text-lg font-bold text-gray-800">Return / Exchange</h3>
            <button id="close-return-modal" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-6 space-y-4">
            <div class="flex space-x-2">
                <input type="text" id="return-search-term" placeholder="Enter Sale ID or Invoice Number (e.g. INV-2025...)" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                <button id="btn-search-sale" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    Search
                </button>
            </div>
            
            <div id="return-search-results" class="hidden">
                <div class="bg-blue-50 p-3 rounded-lg mb-3 text-sm text-blue-800 flex justify-between">
                    <span id="return-sale-info"></span>
                </div>
                <div class="overflow-y-auto max-h-64 border rounded-lg">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-gray-100 text-gray-600 font-semibold">
                            <tr>
                                <th class="px-4 py-2">Product</th>
                                <th class="px-4 py-2 text-right">Sold</th>
                                <th class="px-4 py-2 text-right">Returned</th>
                                <th class="px-4 py-2 text-right">Remaining</th>
                                <th class="px-4 py-2 text-right">Price</th>
                                <th class="px-4 py-2">Action</th>
                            </tr>
                        </thead>
                        <tbody id="return-items-list" class="divide-y divide-gray-200">
                            <!-- Items will be injected here -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hold Bills Modal -->
<div id="hold-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl mx-4 overflow-hidden">
        <div class="p-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
            <h3 class="text-lg font-bold text-gray-800">Held Bills</h3>
            <button class="text-gray-500 hover:text-gray-700" data-close-hold>
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-6 space-y-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Hold Label</label>
                <input id="hold-label" type="text" class="w-full px-4 py-2 border rounded-lg" placeholder="Optional label (e.g. Customer name)">
            </div>
            <button id="confirm-hold" class="w-full py-3 bg-amber-500 text-white rounded-lg hover:bg-amber-600 transition font-semibold">
                <i class="fas fa-pause-circle mr-2"></i>Hold Current Bill
            </button>
            <div class="border-t border-gray-200 pt-4">
                <h4 class="text-sm font-semibold text-gray-700 mb-3">Existing Holds</h4>
                <div id="hold-list" class="space-y-3 max-h-64 overflow-y-auto">
                    <div class="text-sm text-gray-500 text-center py-6">
                        <i class="fas fa-archive text-3xl mb-2"></i>
                        No holds yet
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    // Defensive: wrap everything so syntax error inside template literals cannot break page
    (function(){
    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    function currency(v){ return '{{ $currency }} ' + Number(v).toFixed(2); }

    // Units map for quick lookup (populated from backend)
    const UNITS = @json($allUnits ?? []);
    window.unitsMap = {};
    UNITS.forEach(u => { window.unitsMap[u.id] = u; });

    const PRELOADED_PRODUCTS = @json($productPayload ?? []);
    const productGridElement = document.getElementById('product-grid');
    const productSearchInput = document.getElementById('product-search');
    const SEARCH_PRODUCTS_URL = '{{ route('pos.search-products') }}';
    let activeProductList = PRELOADED_PRODUCTS;

    // Enforce: Walk-in customer (no customer selected) cannot have due amount
    function updateCheckoutState(){
        const checkoutBtn = document.getElementById('btn-checkout');
        if(!checkoutBtn) return;
        const customerSelectEl = document.getElementById('customer-select');
        const isWalkIn = customerSelectEl && !customerSelectEl.value;
        const totalText = document.getElementById('total')?.textContent || '0';
        const total = parseFloat(totalText.replace(/[^0-9.]/g,'') || '0');
        const cashVal = parseFloat(document.getElementById('cash-amount')?.value || '0');
        const due = Math.max(0, total - cashVal);
        if(isWalkIn && due > 0){
            checkoutBtn.disabled = true;
            checkoutBtn.classList.add('opacity-50','cursor-not-allowed');
            checkoutBtn.setAttribute('title','Select a customer to allow due / partial payment');
        } else {
            checkoutBtn.disabled = false;
            checkoutBtn.classList.remove('opacity-50','cursor-not-allowed');
            checkoutBtn.removeAttribute('title');
        }
    }

    function renderCart(cart){
        const itemsWrap = document.getElementById('cart-items');
        itemsWrap.innerHTML = '';
        const items = cart.items || {};
        if(Object.keys(items).length === 0){
            itemsWrap.innerHTML = `<div class="text-center py-8 text-gray-400">
                <i class="fas fa-shopping-cart text-4xl mb-2"></i>
                <p class="text-sm">Cart is empty</p>
            </div>`;
        } else {
            Object.values(items).forEach(it => {
                const row = document.createElement('div');
                const isReturn = it.qty < 0;
                const isOutOfStock = !isReturn && (typeof it.stock_quantity !== 'undefined') && Number(it.stock_quantity) <= 0;
                row.className = `flex items-center justify-between rounded-lg p-3 ${isReturn ? 'bg-red-50 border border-red-100' : (isOutOfStock ? 'bg-red-50 border border-red-200' : 'bg-gray-50')}`;
                const cartKey = it.cart_key || it.id;
                
                // Build unit selection UI (disabled for returns)
                const vUnits = it.visible_units || [];
                let unitControl = '';
                if (!isReturn && vUnits.length > 1) {
                    unitControl = '<select class="unit-select text-xs border rounded px-2 py-1 bg-white" data-cart-key="' + cartKey + '\">' +
                        vUnits.map(uid => {
                            const u = window.unitsMap[uid];
                            if (!u) return '';
                            const sel = uid === it.unit_id ? 'selected' : '';
                            return '<option value="' + uid + '" ' + sel + '>' + (u.short_name || u.name) + '</option>';
                        }).join('') + '</select>';
                } else if (vUnits.length === 1 || isReturn) {
                    const u = it.unit_id ? window.unitsMap[it.unit_id] : null;
                    if (u) unitControl = '<span class="text-xs px-2 py-1 bg-blue-50 border border-blue-200 rounded">' + (u.short_name || u.name) + '</span>';
                }
                
                // Quantity Controls
                let qtyControls = '';
                if (isReturn) {
                    qtyControls = `
                        <div class="flex items-center space-x-2">
                            <span class="text-xs font-bold text-red-600 uppercase mr-2">Return</span>
                            <span class="font-mono font-bold text-red-700">${it.qty}</span>
                            <button class="ml-3 text-red-600 hover:underline remove-item" data-id="${cartKey}">Remove</button>
                        </div>
                    `;
                } else {
                    qtyControls = `
                        <div class="flex items-center space-x-2">
                            <button class="px-2 py-1 bg-gray-200 rounded qty-dec" data-id="${cartKey}">-</button>
                            <input type="number" min="1" value="${it.qty}" class="w-14 text-center border rounded qty-input" data-id="${cartKey}">
                            <button class="px-2 py-1 bg-gray-200 rounded qty-inc" data-id="${cartKey}">+</button>
                            <button class="ml-3 text-red-600 hover:underline remove-item" data-id="${cartKey}">Remove</button>
                        </div>
                    `;
                }

                row.innerHTML = `
                    <div class="space-y-1">
                        <p class="font-semibold text-gray-800 text-sm">${it.name}</p>
                        ${isOutOfStock ? '<p class="text-xs font-semibold text-red-600 uppercase">Out of stock</p>' : ''}
                        <p class="text-xs text-gray-500">${currency(it.price)}${it.unit_multiplier && it.unit_multiplier>1 ? ' (x'+it.unit_multiplier+')' : ''}</p>
                        ${unitControl}
                    </div>
                    ${qtyControls}`;
                itemsWrap.appendChild(row);
            });
        }

        document.getElementById('subtotal').textContent = currency(cart.totals.subtotal);
        document.getElementById('discount-amount').textContent = currency(cart.totals.discount_amount);
        document.getElementById('tax-amount').textContent = currency(cart.totals.tax_amount);
        document.getElementById('total').textContent = currency(cart.totals.total);

        bindCartRowEvents();
        updateCheckoutState();
    }
    
    // Expose renderCart to window for use in modal
    window.renderCart = renderCart;

    async function postJSON(url, data){
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: JSON.stringify(data || {})
        });
        if (!res.ok) {
            let message = `Request failed (${res.status})`;
            try { const j = await res.json(); if (j && j.message) message = j.message; } catch(_) {}
            throw new Error(message);
        }
        return await res.json();
    }

    // Toasts
    const toastHost = document.createElement('div');
    toastHost.className = 'fixed top-4 right-4 z-50 space-y-2';
    document.body.appendChild(toastHost);
    function showToast(type, message){
        const color = type === 'error' ? 'bg-red-600' : (type === 'warning' ? 'bg-amber-500' : 'bg-emerald-600');
        const el = document.createElement('div');
        el.className = `${color} text-white px-4 py-3 rounded shadow-lg transition-opacity`;
        el.textContent = message;
        toastHost.appendChild(el);
        setTimeout(() => { el.style.opacity = '0'; setTimeout(() => el.remove(), 300); }, 2500);
    }

    const escapeHtml = (value = '') => String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

    const escapeAttr = (value = '') => String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

    function buildProductCard(product){
        const isOutOfStock = (product.stock_quantity ?? 0) <= 0;
        const visibleUnits = Array.isArray(product.visible_units) ? product.visible_units : [];
        const categories = (product.categories || []).join(', ');
        const brands = (product.brands || []).join(', ');
        const unitShort = product.unit && product.unit.short_name ? product.unit.short_name : null;
        const visibleUnitsAttr = escapeAttr(JSON.stringify(visibleUnits));
        const unitHtml = unitShort ? `<p class="text-xs text-gray-600">Base: ${escapeHtml(unitShort)}</p>` : '';
        const categoriesHtml = categories ? `<p class="text-xs text-gray-500 truncate" title="${escapeAttr(categories)}"><i class="fas fa-tags mr-1"></i>${escapeHtml(categories)}</p>` : '';
        const brandsHtml = brands ? `<p class="text-xs text-gray-500 truncate" title="${escapeAttr(brands)}"><i class="fas fa-copyright mr-1"></i>${escapeHtml(brands)}</p>` : '';
        const imageContent = product.image ? `<img src="${escapeAttr(product.image)}" alt="${escapeAttr(product.name)}" class="w-full h-full object-cover rounded-lg">` : `<i class="fas fa-cog text-blue-600 text-3xl"></i>`;
        return `
            <div class="rounded-lg p-4 cursor-pointer transition border-2 add-to-cart ${isOutOfStock ? 'bg-red-50 hover:bg-red-100 border-red-300' : 'bg-gray-50 hover:bg-blue-50 border-transparent hover:border-blue-500'}"
                 data-product-id="${product.id}"
                 data-product-name="${escapeAttr(product.name)}"
                 data-product-price="${product.selling_price}"
                 data-visible-units="${visibleUnitsAttr}"
                 data-stock-quantity="${product.stock_quantity ?? 0}">
                <div class="w-full h-24 bg-blue-100 rounded-lg flex items-center justify-center mb-3">
                    ${imageContent}
                </div>
                <h4 class="font-semibold text-gray-800 text-sm mb-1 truncate">${escapeHtml(product.name)}</h4>
                <p class="text-xs mb-2 ${isOutOfStock ? 'text-red-600 font-semibold' : 'text-gray-500'}">
                    Stock: ${product.stock_quantity ?? 0}
                    ${isOutOfStock ? '<span class="ml-2 uppercase">Out of stock</span>' : ''}
                </p>
                <p class="text-lg font-bold text-blue-600">${currency(product.selling_price ?? 0)}</p>
                ${categoriesHtml}
                ${brandsHtml}
                ${unitHtml}
            </div>
        `;
    }

    function renderProductGrid(products = []){
        if (!productGridElement) return;
        if (!products.length) {
            productGridElement.innerHTML = `
                <div class="col-span-full text-center py-12 text-gray-500">
                    <i class="fas fa-box-open text-4xl mb-2"></i>
                    No products found
                </div>
            `;
            return;
        }
        productGridElement.innerHTML = products.map(buildProductCard).join('');
    }

    async function fetchProducts(term = ''){
        const url = new URL(SEARCH_PRODUCTS_URL, window.location.origin);
        if (term) url.searchParams.set('term', term);
        const res = await fetch(url);
        if (!res.ok) {
            const payload = await res.json().catch(() => ({}));
            throw new Error(payload.message || 'Unable to load products');
        }
        return await res.json();
    }

    async function loadProducts(term = ''){
        if (!term) {
            activeProductList = PRELOADED_PRODUCTS;
            renderProductGrid(activeProductList);
            return;
        }
        try {
            const data = await fetchProducts(term);
            activeProductList = Array.isArray(data) ? data : [];
            renderProductGrid(activeProductList);
        } catch(err) {
            console.error('Product search failed', err);
            showToast('error', err.message || 'Product search failed');
        }
    }

    let productSearchTimer;
    if (productSearchInput) {
        productSearchInput.addEventListener('input', () => {
            const term = productSearchInput.value.trim();
            clearTimeout(productSearchTimer);
            productSearchTimer = setTimeout(() => {
                loadProducts(term);
            }, 250);
        });
    }

    renderProductGrid(activeProductList);

    const holdModal = document.getElementById('hold-modal');
    const holdLabelInput = document.getElementById('hold-label');
    const holdListEl = document.getElementById('hold-list');
    const holdCountBadge = document.getElementById('hold-count');
    const holdEndpoint = '{{ route('pos.cart.hold') }}';
    const holdListEndpoint = '{{ route('pos.cart.holds') }}';
    const holdLoadEndpoint = '{{ route('pos.cart.holds.load') }}';
    const holdRemoveEndpoint = '{{ route('pos.cart.holds.remove') }}';
    const quotationPdfTemplate = '{{ route('quotations.pdf', ['sale' => '__SALE_ID__']) }}';
    const printQuotationBtn = document.getElementById('btn-print-quotation');
    const printModal = document.getElementById('quotation-print-modal');
    const previewIframe = document.getElementById('quotation-preview-iframe');
    const confirmPrintBtn = document.getElementById('confirm-print-quotation');
    const cancelPrintBtn = document.getElementById('cancel-print-quotation');
    let currentQuotationUrl = '';
    function buildQuotationUrl(saleId){
        return quotationPdfTemplate.replace('__SALE_ID__', encodeURIComponent(saleId));
    }

    async function fetchHoldList(){
        const res = await fetch(holdListEndpoint);
        if (!res.ok) { return []; }
        return await res.json();
    }

    function renderHoldList(holds){
        if (!holdListEl) return;
        if (!holds.length) {
            holdListEl.innerHTML = `<div class="text-sm text-gray-500 text-center py-6"><i class="fas fa-archive text-3xl mb-2"></i>No holds yet</div>`;
            if (holdCountBadge) holdCountBadge.textContent = '0';
            return;
        }
        holdListEl.innerHTML = holds.map(hold => `
            <div class="border border-gray-200 rounded-lg p-4 flex justify-between space-x-4 bg-white shadow-sm">
                <div>
                    <p class="text-sm font-semibold text-gray-800">${hold.label}</p>
                    <p class="text-xs text-gray-500">${hold.item_count} item(s) · ${new Date(hold.created_at).toLocaleString()}</p>
                </div>
                <div class="text-right space-y-1">
                    <p class="font-semibold text-blue-600">${currency(hold.total)}</p>
                    <div class="flex justify-end gap-2">
                        <button class="text-xs text-white bg-blue-600 rounded px-3 py-1 hover:bg-blue-700 load-hold" data-id="${hold.id}" type="button">Continue</button>
                        <button class="text-xs text-red-600 border border-red-200 rounded px-3 py-1 hover:bg-red-50 delete-hold" data-id="${hold.id}" type="button">Delete</button>
                    </div>
                </div>
            </div>
        `).join('');
        if (holdCountBadge) holdCountBadge.textContent = String(holds.length);
    }

    async function refreshHoldList(){
        const holds = await fetchHoldList().catch(() => []);
        renderHoldList(holds);
    }

    function closePrintModal(){
        printModal?.classList.add('hidden');
        if (previewIframe) {
            previewIframe.src = 'about:blank';
        }
        currentQuotationUrl = '';
    }

    function openQuotationPreview(url){
        currentQuotationUrl = url;
        if (previewIframe) {
            previewIframe.src = url;
        }
        printModal?.classList.remove('hidden');
    }

    printQuotationBtn?.addEventListener('click', () => {
        const saleId = printQuotationBtn.dataset.quotationId;
        if (!saleId) {
            showToast('warning', 'No quotation available to print');
            return;
        }
        openQuotationPreview(buildQuotationUrl(saleId));
    });

    confirmPrintBtn?.addEventListener('click', () => {
        if (!currentQuotationUrl) return;
        const printWindow = window.open(currentQuotationUrl, '_blank');
        if (printWindow) {
            printWindow.focus();
            printWindow.onload = () => printWindow.print();
        }
        closePrintModal();
    });

    cancelPrintBtn?.addEventListener('click', closePrintModal);
    document.querySelectorAll('[data-close-print-modal]').forEach(btn => btn.addEventListener('click', closePrintModal));

    const openHoldModal = async () => {
        await refreshHoldList();
        holdModal?.classList.remove('hidden');
    };

    const closeHoldModal = () => {
        holdModal?.classList.add('hidden');
    };

    document.querySelectorAll('[data-close-hold]').forEach(btn => btn.addEventListener('click', closeHoldModal));
    document.getElementById('btn-hold')?.addEventListener('click', openHoldModal);
    document.getElementById('btn-open-holds')?.addEventListener('click', openHoldModal);

    const confirmHoldBtn = document.getElementById('confirm-hold');
    confirmHoldBtn?.addEventListener('click', async () => {
        const label = holdLabelInput?.value.trim();
        try {
            const payload = await postJSON(holdEndpoint, { label });
            renderCart(payload.cart);
            if (holdLabelInput) holdLabelInput.value = '';
            showToast('success', 'Bill held successfully');
            await refreshHoldList();
            closeHoldModal();
        } catch(err){
            showToast('error', err.message);
        }
    });

    holdListEl?.addEventListener('click', async (event) => {
        const button = event.target.closest('button');
        if (!button) return;
        const holdId = button.dataset.id;
        if (!holdId) return;
            if (button.classList.contains('load-hold')) {
            try {
                const payload = await postJSON(holdLoadEndpoint, { hold_id: holdId });
                renderCart(payload.cart);
                await refreshHoldList();
                closeHoldModal();
                showToast('success', 'Hold loaded');
            } catch(err){
                showToast('error', err.message);
            }
        } else if (button.classList.contains('delete-hold')) {
            try {
                await postJSON(holdRemoveEndpoint, { hold_id: holdId });
                await refreshHoldList();
                showToast('success', 'Hold deleted');
            } catch(err){
                showToast('error', err.message);
            }
        }
    });

    function bindCartRowEvents(){
        document.querySelectorAll('.qty-inc').forEach(btn => btn.onclick = async (e) => {
            const cartKey = e.currentTarget.dataset.id;
            const input = document.querySelector(`.qty-input[data-id="${cartKey}"]`);
            const qty = parseInt(input.value || '1') + 1;
            const cart = await postJSON('{{ route('pos.cart.update') }}', { cart_key: cartKey, qty });
            renderCart(cart);
        });
        document.querySelectorAll('.qty-dec').forEach(btn => btn.onclick = async (e) => {
            const cartKey = e.currentTarget.dataset.id;
            const input = document.querySelector(`.qty-input[data-id="${cartKey}"]`);
            const qty = Math.max(1, parseInt(input.value || '1') - 1);
            const cart = await postJSON('{{ route('pos.cart.update') }}', { cart_key: cartKey, qty });
            renderCart(cart);
        });
        document.querySelectorAll('.qty-input').forEach(inp => inp.onchange = async (e) => {
            const cartKey = e.currentTarget.dataset.id;
            const qty = Math.max(1, parseInt(e.currentTarget.value || '1'));
            const cart = await postJSON('{{ route('pos.cart.update') }}', { cart_key: cartKey, qty });
            renderCart(cart);
        });
        document.querySelectorAll('.remove-item').forEach(btn => btn.onclick = async (e) => {
            const cartKey = e.currentTarget.dataset.id;
            const cart = await postJSON('{{ route('pos.cart.remove') }}', { cart_key: cartKey });
            renderCart(cart);
        });
        // Unit select handlers
        document.querySelectorAll('.unit-select').forEach(sel => sel.onchange = async (e) => {
            const cartKey = e.currentTarget.dataset.cartKey;
            const unitId = parseInt(e.currentTarget.value || '0');
            if (!unitId) return;
            try {
                const cart = await postJSON('{{ route('pos.cart.unit') }}', { cart_key: cartKey, unit_id: unitId });
                renderCart(cart);
            } catch(err){
                showToast('error','Failed to change unit');
            }
        });
    }

    // Product click -> handled by unit selection modal now
    // See modal script at the bottom of the page

    // Discount change
    const discountInputs = document.querySelectorAll('.discount-type');
    const discountValue = document.getElementById('discount-value');
    async function pushDiscount(){
        const type = document.querySelector('.discount-type:checked').value;
        const value = parseFloat(discountValue.value || '0');
        const cart = await postJSON('{{ route('pos.cart.discount') }}', { type, value });
        renderCart(cart);
    }
    discountInputs.forEach(r => r.addEventListener('change', pushDiscount));
    discountValue.addEventListener('change', pushDiscount);

    // Cash amount change calculation + due calculation
    const cashInput = document.getElementById('cash-amount');
    const changeDisplay = document.getElementById('change-display');
    const changeAmount = document.getElementById('change-amount');
    const dueDisplay = document.getElementById('due-display');
    const dueAmountEl = document.getElementById('due-amount');
    
    cashInput.addEventListener('input', () => {
        const cash = parseFloat(cashInput.value || '0');
    const total = parseFloat(document.getElementById('total').textContent.replace('{{ $currency }} ', ''));
        const change = cash - total;
        const due = Math.max(0, total - cash);
        
        if (cash > 0 && change >= 0) {
            changeDisplay.classList.remove('hidden');
            changeAmount.textContent = currency(change);
        } else {
            changeDisplay.classList.add('hidden');
        }

        if (due > 0) {
            dueDisplay.classList.remove('hidden');
            dueAmountEl.textContent = currency(due);
        } else {
            dueDisplay.classList.add('hidden');
        }
        updateCheckoutState();
    });

    // Customer due fetch
    const customerSelect = document.getElementById('customer-select');
    const customerDueWrap = document.getElementById('customer-due');
    const customerDueAmount = document.getElementById('customer-due-amount');
    if (customerSelect) {
        customerSelect.addEventListener('change', async () => {
            const id = customerSelect.value;
            if (!id) { customerDueWrap.classList.add('hidden'); return; }
            try {
                const res = await fetch(`/api/customer-due/${id}`);
                if (res.ok) {
                    const data = await res.json();
                    customerDueAmount.textContent = currency(data.outstanding_due || 0);
                    customerDueWrap.classList.remove('hidden');
                }
            } catch(_){}
            updateCheckoutState();
        });
        // Initial state
        updateCheckoutState();
    }

    // New Customer Modal
    const newCustomerBtn = document.getElementById('btn-new-customer');
    let customerModal;
    function ensureCustomerModal(){
        if (customerModal) return customerModal;
        customerModal = document.createElement('div');
        customerModal.className = 'fixed inset-0 z-50 flex items-center justify-center bg-black/40';
        customerModal.innerHTML = `
            <div class="bg-white w-full max-w-md rounded-xl shadow-lg p-6 relative">
                <button class="absolute top-2 right-2 text-gray-500 hover:text-gray-700" data-close>&times;</button>
                <h3 class="text-lg font-semibold mb-4">New Customer</h3>
                <form id="new-customer-form" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Name *</label>
                        <input name="name" required class="mt-1 w-full px-3 py-2 border rounded" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Email</label>
                        <input type="email" name="email" class="mt-1 w-full px-3 py-2 border rounded" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Phone</label>
                        <input name="phone" class="mt-1 w-full px-3 py-2 border rounded" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Address</label>
                        <textarea name="address" rows="2" class="mt-1 w-full px-3 py-2 border rounded"></textarea>
                    </div>
                    <div class="flex justify-end space-x-2 pt-2">
                        <button type="button" data-close class="px-4 py-2 bg-gray-100 text-gray-700 rounded">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded">Save</button>
                    </div>
                </form>
            </div>`;
        document.body.appendChild(customerModal);
        customerModal.addEventListener('click', e => {
            if (e.target === customerModal || e.target.hasAttribute('data-close')) {
                customerModal.classList.add('hidden');
            }
        });
        document.getElementById('new-customer-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const form = e.currentTarget;
            const formData = new FormData(form);
            const payload = Object.fromEntries(formData.entries());
            try {
                const res = await fetch('{{ route('customers.store') }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    body: formData
                });
                if(!res.ok){ throw new Error('Failed to create customer'); }
                const data = await res.json();
                const c = data.customer;
                // Append to select
                const opt = document.createElement('option');
                opt.value = c.id; opt.textContent = c.name;
                customerSelect.appendChild(opt);
                customerSelect.value = c.id;
                customerModal.classList.add('hidden');
                showToast('success', 'Customer added');
            } catch(err){ showToast('error', err.message); }
        });
        return customerModal;
    }
    if(newCustomerBtn){
        newCustomerBtn.addEventListener('click', () => {
            ensureCustomerModal();
            customerModal.classList.remove('hidden');
        });
    }

    // Clear
    document.getElementById('btn-clear').addEventListener('click', async () => {
        const cart = await postJSON('{{ route('pos.cart.clear') }}');
        renderCart(cart);
    });

    // Draft
    document.getElementById('btn-draft').addEventListener('click', async () => {
        try {
            const res = await postJSON('{{ route('pos.draft') }}', {});
            if(res && res.sale_id){
                const label = res.sale_no ? res.sale_no : ('#' + res.sale_id);
                showToast('success', 'Quotation saved ' + label);
                if (printQuotationBtn) {
                    printQuotationBtn.dataset.quotationId = res.sale_id;
                    printQuotationBtn.dataset.saleNo = label;
                    printQuotationBtn.title = 'Print ' + label;
                    printQuotationBtn.classList.remove('hidden');
                }
                openQuotationPreview(buildQuotationUrl(res.sale_id));
            }
        } catch(err){ showToast('error', err.message || 'Failed to save draft'); }
    });

    // Checkout
    document.getElementById('btn-checkout').addEventListener('click', async () => {
        const cashAmount = parseFloat(document.getElementById('cash-amount').value || '0');
    const total = parseFloat(document.getElementById('total').textContent.replace('{{ $currency }} ', ''));
        
        const due = Math.max(0, total - cashAmount);
        const customerId = document.getElementById('customer-select') ? document.getElementById('customer-select').value : '';
        if (due > 0 && !customerId) {
            showToast('warning', 'Customer is required when there is a due amount.');
            if (document.getElementById('customer-select')) {
                document.getElementById('customer-select').classList.add('ring-2','ring-amber-500');
                setTimeout(()=>document.getElementById('customer-select').classList.remove('ring-2','ring-amber-500'), 1500);
            }
            return;
        }
        
        let res;
        try {
            res = await postJSON('{{ route('pos.checkout') }}', {
                paid_amount: cashAmount,
                customer_id: customerId ? Number(customerId) : null
            });
        } catch(err){ showToast('error', err.message || 'Checkout failed'); return; }
        
        if(res && res.sale_id){
            // Show print receipt with sale data
            showPrintReceipt(res.sale, cashAmount);
            const cart = await postJSON('{{ route('pos.cart.clear') }}');
            renderCart(cart);
            document.getElementById('cash-amount').value = '';
            changeDisplay.classList.add('hidden');
            dueDisplay.classList.add('hidden');
            showToast('success', 'Sale completed #' + res.sale_id);
        } else if(res && res.message){
            showToast('error', res.message);
        }
    });

    // Print Receipt Function
    async function showPrintReceipt(saleData, cashAmount) {
        const PAPER_SIZE = '{{ $invoicePaperSize ?? "a4" }}';
        const CURRENCY = '{{ $currency }} ';
        const VAT_ENABLED = {{ \App\Models\Setting::get('vat_enabled', false) ? 'true' : 'false' }};
        const VAT_RATE = {{ (float) \App\Models\Setting::get('vat_rate', 0) }};
        const DEV_NAME = '{{ config('services.developer.name') }}';
        const DEV_WEB = '{{ config('services.developer.website') }}';
        const DEV_PHONE = '{{ config('services.developer.phone') }}';
        
        // Paper size configurations
        const paperSizes = {
            'a4': { width: '210mm', maxWidth: '210mm', padding: '16px' },
            'letter': { width: '8.5in', maxWidth: '8.5in', padding: '16px' },
            '80mm': { width: '80mm', maxWidth: '300px', padding: '8px' },
            '58mm': { width: '58mm', maxWidth: '220px', padding: '6px' }
        };
        
        const config = paperSizes[PAPER_SIZE] || paperSizes['a4'];
        
        // Build items HTML
        let itemsHTML = '';
        if (saleData.items && saleData.items.length > 0) {
            itemsHTML = saleData.items.map(item => {
                return '<tr>' +
                    '<td>' + (item.product_name || '') + '</td>' +
                    '<td class="text-right">' + Number(item.quantity || 0) + '</td>' +
                    '<td class="text-right">' + CURRENCY + Number(item.unit_price || 0).toFixed(2) + '</td>' +
                    '<td class="text-right">' + CURRENCY + Number(item.total || 0).toFixed(2) + '</td>' +
                    '</tr>';
            }).join('');
        }
        
        // Customer row if exists
        const customerRow = saleData.customer_name ? 
            '<div><span>Customer:</span><span>' + saleData.customer_name + '</span></div>' : '';
        
        const printWindow = window.open('', '_blank', 'width=800,height=600');
        
        const taxRowHtml = VAT_ENABLED ? (
'            <div class="totals-row">' +
'                <span>VAT' + (VAT_RATE ? ' (' + VAT_RATE + '%)' : '') + ':</span>' +
'                <span>' + CURRENCY + Number(saleData.tax || 0).toFixed(2) + '</span>' +
'            </div>'
        ) : '';

        const phoneDigits = (DEV_PHONE || '').replace(/[^0-9]/g, '');
        const poweredByHtml = DEV_WEB ? (
            'Powered by <a href="https://' + DEV_WEB + '" target="_blank" style="color:inherit; text-decoration:none; font-weight:600;">' + DEV_WEB + '</a>'
        ) : phoneDigits ? (
            'Powered by <a href="https://wa.me/' + phoneDigits + '" target="_blank" style="color:inherit; text-decoration:none; font-weight:600;">' + (DEV_NAME || phoneDigits) + '</a>'
        ) : (
            'Powered by ' + (DEV_NAME || 'Developer')
        );

        const receiptHTML = '<!DOCTYPE html>' +
'<html>' +
'<head>' +
'    <meta charset="UTF-8">' +
'    <title>Receipt #' + (saleData.sale_no || saleData.id) + '</title>' +
'    <style>' +
'        * { margin: 0; padding: 0; box-sizing: border-box; }' +
'        body { font-family: Arial, Helvetica, sans-serif; background: #fff; padding: 16px; color: #000; font-size: 12px; }' +
'        .receipt-container { max-width: 420px; margin: 0 auto; background: #fff; padding: 16px; border: 1px solid #000; }' +
'        .header { text-align: center; border-bottom: 1px dashed #000; padding-bottom: 10px; margin-bottom: 12px; }' +
'        .logo { font-size: 18px; font-weight: 700; }' +
'        .shop-details { font-size: 11px; line-height: 1.4; margin-top: 4px; }' +
'        .receipt-title { font-size: 14px; font-weight: 700; margin: 10px 0; text-align: center; }' +
'        .receipt-info { font-size: 11px; margin-bottom: 10px; }' +
'        .receipt-info div { display: flex; justify-content: space-between; margin-bottom: 4px; }' +
'        table { width: 100%; border-collapse: collapse; margin: 10px 0; }' +
'        th, td { padding: 6px 4px; font-size: 11px; border-bottom: 1px solid #000; }' +
'        th { font-weight: 700; text-align: left; }' +
'        .text-right { text-align: right; }' +
'        .totals { margin-top: 10px; border-top: 1px solid #000; padding-top: 8px; }' +
'        .totals-row { display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 12px; }' +
'        .totals-row.grand-total { font-weight: 700; font-size: 14px; border-top: 1px dashed #000; padding-top: 6px; margin-top: 6px; }' +
'        .payment-info { margin-top: 10px; padding: 8px; border: 1px solid #000; }' +
'        .payment-row { display: flex; justify-content: space-between; margin-bottom: 4px; font-size: 12px; }' +
'        .footer { margin-top: 14px; padding-top: 10px; border-top: 1px dashed #000; text-align: center; font-size: 11px; }' +
'        @media print { body { padding: 0; } }' +
'    </style>' +
'</head>' +
'<body>' +
'    <div class="receipt-container">' +
'        <div class="header">' +
'            <div class="logo">{{ \App\Models\Setting::get("shop_name", "Vehicle POS System") }}</div>' +
'            <div class="shop-details">' +
'                {{ \App\Models\Setting::get("shop_address", "") }}<br>' +
'                Tel: {{ \App\Models\Setting::get("shop_phone", "") }}<br>' +
'                Email: {{ \App\Models\Setting::get("shop_email", "") }}' +
'            </div>' +
'        </div>' +
'        <div class="receipt-title">SALES RECEIPT</div>' +
'        <div class="receipt-info">' +
'            <div><span>Receipt #:</span><span>' + (saleData.sale_no || saleData.id) + '</span></div>' +
'            <div><span>Date:</span><span>' + new Date().toLocaleString() + '</span></div>' +
'            <div><span>Cashier:</span><span>' + (saleData.cashier_name || 'Admin') + '</span></div>' +
            customerRow +
'        </div>' +
'        <table>' +
'            <thead>' +
'                <tr>' +
'                    <th>Item</th>' +
'                    <th class="text-right">Qty</th>' +
'                    <th class="text-right">Price</th>' +
'                    <th class="text-right">Total</th>' +
'                </tr>' +
'            </thead>' +
'            <tbody>' +
                itemsHTML +
'            </tbody>' +
'        </table>' +
'        <div class="totals">' +
'            <div class="totals-row">' +
'                <span>Subtotal:</span>' +
'                <span>' + CURRENCY + Number(saleData.subtotal || 0).toFixed(2) + '</span>' +
'            </div>' +
'            <div class="totals-row">' +
'                <span>Discount:</span>' +
'                <span>' + CURRENCY + Number(saleData.discount || 0).toFixed(2) + '</span>' +
'            </div>' +
            taxRowHtml +
'            <div class="totals-row grand-total">' +
'                <span>TOTAL:</span>' +
'                <span>' + CURRENCY + Number(saleData.total_amount || 0).toFixed(2) + '</span>' +
'            </div>' +
'        </div>' +
'        <div class="payment-info">' +
'            <div class="payment-row">' +
'                <span>Cash Received:</span>' +
'                <span>' + CURRENCY + Number(cashAmount).toFixed(2) + '</span>' +
'            </div>' +
'            <div class="payment-row">' +
'                <span>Change:</span>' +
'                <span>' + CURRENCY + Math.max(0, Number(cashAmount) - Number(saleData.total_amount || 0)).toFixed(2) + '</span>' +
'            </div>' +
'        </div>' +
'        <div class="footer">' +
'            <div>Thank You For Your Purchase!</div>' +
'            <div>Please visit us again</div>' +
            '<div style="margin-top: 10px;">' + poweredByHtml + '</div>' +
'        </div>' +
'    </div>' +
'    <script>' +
'        setTimeout(function() { window.print(); window.close(); }, 500);' +
'    <\/script>' +
'</body>' +
'</html>';
        
        printWindow.document.write(receiptHTML);
        printWindow.document.close();
    }

    // Initialize: fetch current empty cart by clearing then rendering
    (async function init(){
        try {
            const cart = await postJSON('{{ route('pos.cart.clear') }}');
            renderCart(cart);
        } catch(err){ console.error('Init cart failed', err); }
    })();

    })(); // end IIFE
</script>
@endpush

<!-- Quotation Print Confirmation Modal -->
<div id="quotation-print-modal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center px-4" style="z-index: 9999;">
    <div class="bg-white rounded-2xl w-full max-w-3xl shadow-2xl overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800">Print Quotation</h3>
            <button type="button" data-close-print-modal class="text-gray-500 hover:text-gray-800">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-6 space-y-4">
            <p class="text-sm text-gray-600">Would you like to open the quotation in print view? The document will be rendered in a new tab.</p>
            <div class="h-64 border border-gray-200 rounded-lg overflow-hidden">
                <iframe id="quotation-preview-iframe" class="w-full h-full" src="about:blank" loading="lazy"></iframe>
            </div>
            <div class="flex justify-end gap-2">
                <button id="cancel-print-quotation" type="button" class="px-4 py-2 rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-100">Cancel</button>
                <button id="confirm-print-quotation" type="button" class="px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">Open Print View</button>
            </div>
        </div>
    </div>
</div>

<!-- Unit Selection Modal -->
<div id="unit-selection-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4">
        <div class="bg-gradient-to-r from-blue-600 to-blue-700 text-white p-6 rounded-t-xl">
            <div class="flex justify-between items-center">
                <h3 class="text-xl font-bold">
                    <i class="fas fa-box-open mr-2"></i>Select Unit
                </h3>
                <button type="button" id="close-unit-modal" class="text-white hover:text-gray-200 text-2xl">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <p id="modal-product-name" class="text-blue-100 mt-2 text-sm"></p>
        </div>
        
        <div class="p-6">
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-ruler-combined mr-1"></i>Select Unit Type
                </label>
                <div id="unit-options" class="grid grid-cols-2 gap-3"></div>
            </div>
            
            <div class="mb-6">
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-sort-numeric-up mr-1"></i>Quantity
                </label>
                <input 
                    type="number" 
                    id="modal-quantity" 
                    min="1" 
                    value="1" 
                    class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-lg"
                    placeholder="Enter quantity"
                >
            </div>
            
            <div class="flex space-x-3">
                <button 
                    type="button" 
                    id="confirm-add-to-cart" 
                    class="flex-1 bg-gradient-to-r from-green-600 to-green-700 text-white px-6 py-3 rounded-lg hover:from-green-700 hover:to-green-800 transition font-semibold shadow-lg">
                    <i class="fas fa-cart-plus mr-2"></i>Add to Cart
                </button>
                <button 
                    type="button" 
                    id="cancel-unit-modal" 
                    class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition font-semibold">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function() {
    const modal = document.getElementById('unit-selection-modal');
    const closeBtn = document.getElementById('close-unit-modal');
    const cancelBtn = document.getElementById('cancel-unit-modal');
    const confirmBtn = document.getElementById('confirm-add-to-cart');
    const productNameEl = document.getElementById('modal-product-name');
    const unitOptionsEl = document.getElementById('unit-options');
    const quantityInput = document.getElementById('modal-quantity');
    
    let selectedProductId = null;
    let selectedProductPrice = null;
    let selectedUnit = null;
    let availableUnits = @json($allUnits);
    
    // Close modal handlers
    const closeModal = () => {
        modal.classList.add('hidden');
        selectedProductId = null;
        selectedProductPrice = null;
        selectedUnit = null;
        unitOptionsEl.innerHTML = '';
        quantityInput.value = 1;
    };
    
    closeBtn.addEventListener('click', closeModal);
    cancelBtn.addEventListener('click', closeModal);
    
    // Click outside modal to close
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });
    
    // Open modal when product card is clicked
    document.addEventListener('click', (e) => {
        const productCard = e.target.closest('.add-to-cart');
        if (!productCard) return;
        
        e.preventDefault();
        e.stopPropagation();
        
        selectedProductId = productCard.dataset.productId;
        selectedProductPrice = parseFloat(productCard.dataset.productPrice);
        const productName = productCard.dataset.productName;
        const visibleUnits = JSON.parse(productCard.dataset.visibleUnits || '[]');
        
        productNameEl.textContent = productName;
        
        // Filter available units based on visible_units
        const filteredUnits = availableUnits.filter(unit => visibleUnits.includes(unit.id));
        
        if (filteredUnits.length === 0) {
            // No specific units configured, just add to cart directly with quantity 1
            addProductToCart(selectedProductId, 1);
            return;
        }
        
        // Render unit options
        unitOptionsEl.innerHTML = '';
        filteredUnits.forEach(unit => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'unit-option-btn px-4 py-3 border-2 border-gray-300 rounded-lg hover:border-blue-500 hover:bg-blue-50 transition text-center';
            btn.dataset.unitId = unit.id;
            btn.dataset.unitName = unit.short_name;
            btn.innerHTML = `
                <div class="font-semibold text-gray-800">${unit.short_name}</div>
                <div class="text-xs text-gray-500">${unit.name}</div>
            `;
            
            btn.addEventListener('click', () => {
                // Remove selection from all buttons
                document.querySelectorAll('.unit-option-btn').forEach(b => {
                    b.classList.remove('border-blue-500', 'bg-blue-50', 'ring-2', 'ring-blue-300');
                });
                // Add selection to clicked button
                btn.classList.add('border-blue-500', 'bg-blue-50', 'ring-2', 'ring-blue-300');
                selectedUnit = {
                    id: unit.id,
                    name: unit.short_name
                };
            });
            
            unitOptionsEl.appendChild(btn);
        });
        
        // Auto-select first unit
        if (filteredUnits.length > 0) {
            const firstBtn = unitOptionsEl.querySelector('.unit-option-btn');
            firstBtn.click();
        }
        
        modal.classList.remove('hidden');
        quantityInput.focus();
    });
    
    // Confirm add to cart
    confirmBtn.addEventListener('click', async () => {
        if (!selectedProductId || !selectedUnit) {
            alert('Please select a unit type');
            return;
        }
        
        const quantity = parseInt(quantityInput.value) || 1;
        if (quantity < 1) {
            alert('Quantity must be at least 1');
            return;
        }
        
        await addProductToCart(selectedProductId, quantity, selectedUnit);
        closeModal();
    });
    
    // Add product to cart function
    async function addProductToCart(productId, quantity = 1, unit = null) {
        try {
            const response = await fetch('{{ route('pos.cart.add') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ 
                    product_id: productId,
                    quantity: quantity,
                    unit: unit
                })
            });
            
            if (!response.ok) throw new Error('Failed to add to cart');
            
            const cart = await response.json();
            // Trigger cart render (assuming renderCart is available in parent scope)
            if (window.renderCart) {
                window.renderCart(cart);
            } else {
                location.reload();
            }
        } catch (error) {
            console.error('Error adding to cart:', error);
            alert('Failed to add product to cart');
        }
    }
    
    // Enter key to confirm
    quantityInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            confirmBtn.click();
        }
    });
})();
</script>
@endpush
