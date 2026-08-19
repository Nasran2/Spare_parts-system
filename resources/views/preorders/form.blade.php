@php
    $editing = isset($preOrder);
    $initialItems = old('items');
    if ($initialItems === null && $editing) {
        $initialItems = $preOrder->items->map(fn ($item) => [
            'product_id' => $item->product_id,
            'product_price_id' => $item->product_price_id,
            'original_product_name' => $item->original_product_name,
            'description' => $item->description,
            'quantity' => $item->quantity,
            'unit_price' => (float) $item->final_price,
            'quoted_price' => (float) $item->quoted_price,
            'discount_type' => $item->discount_type,
            'discount_value' => (float) $item->discount_value,
            'notes' => $item->notes,
            'stock' => $item->current_stock,
            'sync_status' => $item->product_id ? 'linked' : 'unlinked',
        ])->values()->all();
    }
    $initialItems ??= [];
    $selectedStore = old('store_id', $editing ? $preOrder->store_id : optional($stores->firstWhere('is_default', true))->id);
@endphp

<form action="{{ $formAction }}" method="POST" enctype="multipart/form-data" id="preorder-form" class="space-y-6">
    @csrf
    @if($formMethod !== 'POST') @method($formMethod) @endif

    @if($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-red-800">
            <div class="font-semibold mb-2"><i class="fas fa-circle-exclamation mr-2"></i>Please correct the following:</div>
            <ul class="list-disc ml-6 text-sm space-y-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-5 text-white flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div>
                <h2 class="text-xl font-bold"><i class="fas fa-clipboard-list mr-2"></i>{{ $editing ? 'Update Pre-Order' : 'New Pre-Order' }}</h2>
                <p class="text-blue-100 text-sm mt-1">Stock is not reserved or deducted until completion.</p>
            </div>
            <span class="px-4 py-2 bg-white/15 rounded-lg font-mono font-bold">{{ $editing ? $preOrder->pre_order_number : $nextNumber }}</span>
        </div>
        <div class="p-6 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Date *</label>
                <input type="date" name="pre_order_date" required value="{{ old('pre_order_date', $editing ? $preOrder->pre_order_date->format('Y-m-d') : now()->format('Y-m-d')) }}" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Document Type *</label>
                <select name="document_type" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg">
                    <option value="invoice" @selected(old('document_type', $editing ? $preOrder->document_type : 'invoice') === 'invoice')>Invoice / Pre-Order Invoice</option>
                    <option value="quotation" @selected(old('document_type', $editing ? $preOrder->document_type : '') === 'quotation')>Quotation</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Store</label>
                <select name="store_id" id="store_id" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg">
                    <option value="">General stock</option>
                    @foreach($stores as $store)<option value="{{ $store->id }}" @selected((string)$selectedStore === (string)$store->id)>{{ $store->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Expected Delivery</label>
                <input type="date" name="expected_delivery_date" value="{{ old('expected_delivery_date', $editing && $preOrder->expected_delivery_date ? $preOrder->expected_delivery_date->format('Y-m-d') : '') }}" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg">
                <p class="text-xs text-gray-500 mt-1">Blank dates are omitted from PDFs.</p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-5">
            <h3 class="text-lg font-semibold text-gray-800"><i class="fas fa-user text-blue-600 mr-2"></i>Customer Information</h3>
            @if(auth()->user()->hasPermission('customers.create'))
                <button type="button" onclick="openCustomerModal()" class="px-4 py-2 bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100"><i class="fas fa-user-plus mr-2"></i>Quick Create Customer</button>
            @endif
        </div>
        <div class="max-w-2xl">
            <label class="block text-sm font-medium text-gray-700 mb-2">Customer *</label>
            <div class="relative" id="customer-dropdown-wrapper">
                <input type="text" id="customer-search-input" placeholder="Search and select customer..." class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" autocomplete="off" required>
                <input type="hidden" name="customer_id" id="customer_id" required value="{{ old('customer_id', $editing ? $preOrder->customer_id : '') }}">
                <div id="customer-options" class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg hidden max-h-60 overflow-y-auto">
                    @foreach($customers as $customer)
                        <div class="px-4 py-2 cursor-pointer hover:bg-blue-50 customer-option" data-id="{{ $customer->id }}" data-search="{{ strtolower($customer->name.' '.$customer->phone) }}" data-name="{{ $customer->name }}{{ $customer->phone ? ' — '.$customer->phone : '' }}">
                            {{ $customer->name }}{{ $customer->phone ? ' — '.$customer->phone : '' }}
                        </div>
                    @endforeach
                    <div id="customer-no-results" class="px-4 py-3 text-sm text-gray-500 hidden text-center">No customer found. <a href="#" onclick="openCustomerModal(); return false;" class="text-blue-600 hover:underline">Quick Create</a></div>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-5"><i class="fas fa-car-side text-blue-600 mr-2"></i>Vehicle Information</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
            <div><label class="block text-sm font-medium text-gray-700 mb-2">Vehicle Name / Model *</label><input name="vehicle_name" required value="{{ old('vehicle_name', $editing ? $preOrder->vehicle_name : '') }}" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg"></div>
            <div class="md:col-span-2"><label class="block text-sm font-medium text-gray-700 mb-2">Vehicle Description</label><textarea name="vehicle_description" rows="3" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg">{{ old('vehicle_description', $editing ? $preOrder->vehicle_description : '') }}</textarea></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-2">Vehicle Image</label><input type="file" name="vehicle_image" accept="image/jpeg,image/png,image/webp" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50"><p class="text-xs text-gray-500 mt-1">JPG, PNG or WebP; maximum 5 MB.</p>@if($editing && $preOrder->vehicle_image_url)<img src="{{ $preOrder->vehicle_image_url }}" alt="Vehicle" class="mt-3 h-24 rounded-lg object-cover border">@endif</div>
            <div class="md:col-span-2"><label class="block text-sm font-medium text-gray-700 mb-2">Boss / Customer Instructions</label><textarea name="instructions" rows="3" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg">{{ old('instructions', $editing ? $preOrder->instructions : '') }}</textarea></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-2">Internal / PDF Notes</label><textarea name="notes" rows="3" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg">{{ old('notes', $editing ? $preOrder->notes : '') }}</textarea></div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="px-6 py-5 border-b flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
            <div><h3 class="text-lg font-semibold text-gray-800"><i class="fas fa-gears text-blue-600 mr-2"></i>Products / Parts</h3><p class="text-sm text-gray-500 mt-1">Products with zero stock remain selectable. Add temporary items for parts not yet in the catalogue.</p></div>
            <div class="flex flex-col sm:flex-row gap-2 relative">
                <div class="relative"><input type="search" id="product-search" autocomplete="off" placeholder="Search name or SKU..." class="w-full sm:w-72 px-3 py-2.5 border border-gray-300 rounded-lg"><div id="product-results" class="hidden absolute right-0 left-0 mt-1 bg-white border rounded-lg shadow-xl z-30 max-h-72 overflow-y-auto"></div></div>
                <button type="button" onclick="addTemporaryItem()" class="px-4 py-2.5 bg-amber-100 text-amber-800 rounded-lg hover:bg-amber-200 whitespace-nowrap"><i class="fas fa-link-slash mr-2"></i>Unlinked Product</button>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1150px]">
                <thead class="bg-gray-50 text-xs uppercase text-gray-600"><tr><th class="px-3 py-3 text-left">Product / Part</th><th class="px-3 py-3">Stock</th><th class="px-3 py-3">Qty</th><th class="px-3 py-3">Unit Price</th><th class="px-3 py-3">Discount</th><th class="px-3 py-3">Line Total</th><th class="px-3 py-3">Sync</th><th class="px-3 py-3"></th></tr></thead>
                <tbody id="items-body" class="divide-y"></tbody>
            </table>
        </div>
        <div id="empty-items" class="p-10 text-center text-gray-500"><i class="fas fa-box-open text-3xl mb-3"></i><p>Search a product or add an unlinked part.</p></div>
        <div class="p-6 bg-gray-50 grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="space-y-4">
                <div class="flex gap-3 items-end">
                    <div><label class="block text-sm font-medium text-gray-700 mb-2">Order Discount</label><select name="bill_discount_type" id="bill_discount_type" class="px-3 py-2.5 border rounded-lg"><option value="fixed" @selected(old('bill_discount_type', $editing ? $preOrder->bill_discount_type : 'fixed') === 'fixed')>Fixed</option><option value="percentage" @selected(old('bill_discount_type', $editing ? $preOrder->bill_discount_type : '') === 'percentage')>Percentage</option></select></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-2">Value</label><input type="number" min="0" step="0.01" name="bill_discount_value" id="bill_discount_value" value="{{ old('bill_discount_value', $editing ? $preOrder->bill_discount_value : 0) }}" class="w-40 px-3 py-2.5 border rounded-lg"></div>
                </div>
                <div class="flex gap-3 items-end border-t pt-4">
                    <div class="flex-1"><label class="block text-sm font-medium text-gray-700 mb-2">Tax Percentage (%)</label><input type="number" min="0" step="0.01" name="custom_tax_rate" id="custom_tax_rate" value="{{ old('custom_tax_rate', $editing && $preOrder->custom_tax_rate !== null ? (float)$preOrder->custom_tax_rate : (float)$taxSettings->default_vat_rate) }}" class="w-full px-3 py-2.5 border rounded-lg"></div>
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 mb-2">PDF Tax Display</label>
                        <select name="pdf_tax_display" id="pdf_tax_display" class="w-full px-3 py-2.5 border rounded-lg">
                            <option value="separate" @selected(old('pdf_tax_display', $editing ? $preOrder->pdf_tax_display : 'separate') === 'separate')>Show Tax Separately</option>
                            <option value="exclusive_hidden" @selected(old('pdf_tax_display', $editing ? $preOrder->pdf_tax_display : '') === 'exclusive_hidden')>Hide Tax / Add to Price</option>
                            <option value="inclusive" @selected(old('pdf_tax_display', $editing ? $preOrder->pdf_tax_display : '') === 'inclusive')>Hide Tax / Inclusive Price</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl border p-4 space-y-2 text-sm">
                <div class="flex justify-between"><span>Subtotal</span><strong id="summary-subtotal">{{ $currency }}0.00</strong></div>
                <div class="flex justify-between text-red-600"><span>Discount</span><strong id="summary-discount">{{ $currency }}0.00</strong></div>
                <div class="flex justify-between"><span>Tax</span><strong id="summary-tax">{{ $currency }}0.00</strong></div>
                <div class="flex justify-between text-lg border-t pt-2 text-blue-700"><span class="font-bold">Grand Total</span><strong id="summary-total">{{ $currency }}0.00</strong></div>
            </div>
        </div>
    </div>

    <div class="flex flex-col sm:flex-row gap-3 justify-end">
        <a href="{{ $editing ? route('preorders.show', $preOrder) : route('preorders.index') }}" class="px-6 py-3 bg-gray-200 text-gray-800 rounded-lg text-center hover:bg-gray-300">Cancel</a>
        <button type="submit" class="px-8 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg shadow-lg hover:from-blue-700 hover:to-blue-800 font-semibold"><i class="fas fa-save mr-2"></i>Save Draft (Pending)</button>
    </div>
</form>

<div id="customer-modal" class="fixed inset-0 bg-black/50 z-[70] hidden items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg">
        <div class="px-6 py-4 border-b flex justify-between"><h3 class="font-bold text-lg">Quick Create Customer</h3><button type="button" onclick="closeCustomerModal()" class="text-gray-500"><i class="fas fa-times"></i></button></div>
        <form id="quick-customer-form" class="p-6 space-y-4">
            <div><label class="text-sm font-medium">Name *</label><input name="name" required class="mt-1 w-full px-3 py-2.5 border rounded-lg"></div>
            <div><label class="text-sm font-medium">Phone *</label><input name="phone" required class="mt-1 w-full px-3 py-2.5 border rounded-lg"></div>
            <div><label class="text-sm font-medium">Email</label><input type="email" name="email" class="mt-1 w-full px-3 py-2.5 border rounded-lg"></div>
            <div><label class="text-sm font-medium">Address</label><textarea name="address" class="mt-1 w-full px-3 py-2.5 border rounded-lg"></textarea></div>
            <p id="customer-modal-error" class="text-sm text-red-600 hidden"></p>
            <div class="flex justify-end gap-2"><button type="button" onclick="closeCustomerModal()" class="px-4 py-2 bg-gray-200 rounded-lg">Cancel</button><button class="px-5 py-2 bg-blue-600 text-white rounded-lg">Create & Select</button></div>
        </form>
    </div>
</div>

<script>
const initialItems = @json($initialItems);
const currency = @json($currency);
const defaultTax = {
    vat_enabled: @json((bool)$taxSettings->vat_enabled),
    vat_rate: Number(@json((string)$taxSettings->default_vat_rate)),
    price_mode: @json($taxSettings->default_sale_price_mode),
    vat_allowed: true,
    tax_status: 'standard'
};
let rowCounter = 0;
const body = document.getElementById('items-body');

function esc(value) { return String(value ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c])); }
function money(value) { return currency + Number(value || 0).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}); }

function addRow(item = {}) {
    const i = rowCounter++;
    const linked = !!item.product_id;
    const tax = item.tax || defaultTax;
    const row = document.createElement('tr');
    row.dataset.tax = JSON.stringify(tax);
    row.innerHTML = `
        <td class="px-3 py-3 align-top min-w-[280px]">
            <input type="hidden" name="items[${i}][product_id]" value="${esc(item.product_id || '')}">
            <input type="hidden" name="items[${i}][product_price_id]" value="${esc(item.product_price_id || '')}">
            <input type="hidden" name="items[${i}][quoted_price]" value="${esc(item.quoted_price ?? item.unit_price ?? '')}">
            <input name="items[${i}][original_product_name]" value="${esc(item.original_product_name || item.name || '')}" required placeholder="Product / part name" class="w-full px-3 py-2 border rounded-lg font-medium">
            <input name="items[${i}][description]" value="${esc(item.description || '')}" placeholder="Description / notes" class="mt-2 w-full px-3 py-2 border rounded-lg text-sm">
            <input type="hidden" name="items[${i}][notes]" value="${esc(item.notes || '')}">
        </td>
        <td class="px-3 py-3 text-center align-top"><span class="inline-flex px-2 py-1 rounded-full text-xs font-semibold ${Number(item.stock || 0) > 0 ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-800'}">${linked ? 'Stock: '+Number(item.stock || 0) : '—'}</span></td>
        <td class="px-3 py-3 align-top"><input type="number" min="1" step="1" name="items[${i}][quantity]" value="${esc(item.quantity || 1)}" required class="calc-input w-20 px-2 py-2 border rounded-lg text-right"></td>
        <td class="px-3 py-3 align-top"><input type="number" min="0" step="0.01" name="items[${i}][unit_price]" value="${esc(item.unit_price ?? item.selling_price ?? 0)}" required class="calc-input w-32 px-2 py-2 border rounded-lg text-right"></td>
        <td class="px-3 py-3 align-top"><div class="flex gap-1"><select name="items[${i}][discount_type]" class="calc-input px-2 py-2 border rounded-lg text-sm"><option value="fixed" ${item.discount_type === 'percentage' ? '' : 'selected'}>Fixed</option><option value="percentage" ${item.discount_type === 'percentage' ? 'selected' : ''}>%</option></select><input type="number" min="0" step="0.01" name="items[${i}][discount_value]" value="${esc(item.discount_value || 0)}" class="calc-input w-24 px-2 py-2 border rounded-lg text-right"></div></td>
        <td class="px-3 py-3 text-right align-top font-semibold line-total">${money(0)}</td>
        <td class="px-3 py-3 text-center align-top"><span class="inline-flex px-2 py-1 rounded-full text-xs font-semibold ${linked ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-800'}">${linked ? 'Linked' : 'Not Synced'}</span></td>
        <td class="px-3 py-3 text-center align-top"><button type="button" class="text-red-600 hover:bg-red-50 rounded-lg p-2" onclick="this.closest('tr').remove(); recalculate();"><i class="fas fa-trash"></i></button></td>`;
    body.appendChild(row);
    row.querySelectorAll('.calc-input').forEach(el => el.addEventListener('input', recalculate));
    recalculate();
}

function addTemporaryItem() { addRow({sync_status:'unlinked', stock:0, tax:defaultTax}); }

function recalculate() {
    const rows = [...body.querySelectorAll('tr')];
    document.getElementById('empty-items').classList.toggle('hidden', rows.length > 0);
    let grossTotal = 0, lineDiscountTotal = 0;
    const lines = rows.map(row => {
        const qty = Number(row.querySelector('[name$="[quantity]"]').value || 0);
        const price = Number(row.querySelector('[name$="[unit_price]"]').value || 0);
        const type = row.querySelector('[name$="[discount_type]"]').value;
        const value = Number(row.querySelector('[name$="[discount_value]"]').value || 0);
        const gross = qty * price;
        const discount = Math.min(gross, type === 'percentage' ? gross * value / 100 : value);
        grossTotal += gross; lineDiscountTotal += discount;
        return {row, gross, after: gross - discount};
    });
    const afterLines = lines.reduce((s,l) => s + l.after, 0);
    const billType = document.getElementById('bill_discount_type').value;
    const billValue = Number(document.getElementById('bill_discount_value').value || 0);
    const billDiscount = Math.min(afterLines, billType === 'percentage' ? afterLines * billValue / 100 : billValue);
    
    const taxRate = Number(document.getElementById('custom_tax_rate').value || 0) / 100;
    const isInclusive = document.getElementById('pdf_tax_display').value === 'inclusive';
    const isExclusiveHidden = document.getElementById('pdf_tax_display').value === 'exclusive_hidden';

    let taxTotal = 0, grand = 0;
    lines.forEach(line => {
        const allocated = afterLines > 0 ? billDiscount * line.after / afterLines : 0;
        const discounted = Math.max(0, line.after - allocated);
        let tax = 0, total = discounted;
        if (taxRate > 0) {
            if (isInclusive) {
                tax = discounted - (discounted / (1 + taxRate));
            } else {
                tax = discounted * taxRate;
                total += tax;
            }
        }
        taxTotal += tax; grand += total;
        
        let displayTotal = total;
        if (!isInclusive && !isExclusiveHidden) {
            displayTotal = discounted;
        }
        line.row.querySelector('.line-total').textContent = money(displayTotal);
    });
    
    // Adjust summary
    document.getElementById('summary-subtotal').textContent = money(isExclusiveHidden ? grossTotal * (1 + taxRate) : grossTotal);
    document.getElementById('summary-discount').textContent = money(isExclusiveHidden ? (lineDiscountTotal + billDiscount) * (1 + taxRate) : lineDiscountTotal + billDiscount);
    if (isInclusive) {
        document.getElementById('summary-tax').innerHTML = `Included <span>(${money(taxTotal)})</span>`;
        document.getElementById('summary-tax').parentElement.classList.remove('hidden');
    } else if (isExclusiveHidden) {
        document.getElementById('summary-tax').parentElement.classList.add('hidden');
    } else {
        document.getElementById('summary-tax').textContent = money(taxTotal);
        document.getElementById('summary-tax').parentElement.classList.remove('hidden');
    }
    document.getElementById('summary-total').textContent = money(grand);
}

document.getElementById('bill_discount_type').addEventListener('change', recalculate);
document.getElementById('bill_discount_value').addEventListener('input', recalculate);
document.getElementById('custom_tax_rate').addEventListener('input', recalculate);
document.getElementById('pdf_tax_display').addEventListener('change', recalculate);
initialItems.forEach(item => addRow(item));

let searchTimer;
const searchInput = document.getElementById('product-search');
const results = document.getElementById('product-results');
searchInput.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(async () => {
        const q = searchInput.value.trim();
        if (!q) { results.classList.add('hidden'); return; }
        const url = new URL(@json(route('preorders.search-products')), window.location.origin);
        url.searchParams.set('q', q); url.searchParams.set('store_id', document.getElementById('store_id').value);
        const response = await fetch(url, {headers:{'Accept':'application/json'}});
        if (!response.ok) return;
        const products = await response.json();
        results.innerHTML = '';
        products.forEach(product => {
            const button = document.createElement('button'); button.type = 'button';
            button.className = 'w-full p-3 text-left hover:bg-blue-50 border-b last:border-0';
            button.innerHTML = `<div class="font-medium">${esc(product.name)}</div><div class="text-xs text-gray-500">SKU: ${esc(product.sku || '—')} · <span class="${product.stock > 0 ? 'text-green-600' : 'text-amber-700'}">Stock: ${product.stock}${product.stock <= 0 ? ' — Pre-Order' : ''}</span> · ${money(product.selling_price)}</div>`;
            button.onclick = () => { addRow({...product, original_product_name:product.name, quantity:1, unit_price:product.selling_price, quoted_price:product.selling_price, discount_type:'fixed', discount_value:0}); results.classList.add('hidden'); searchInput.value=''; };
            results.appendChild(button);
        });
        if (!products.length) results.innerHTML = '<div class="p-3 text-sm text-gray-500">No product found. Use “Unlinked Product”.</div>';
        results.classList.remove('hidden');
    }, 250);
});

const customerInput = document.getElementById('customer-search-input');
const customerId = document.getElementById('customer_id');
const customerOptions = document.getElementById('customer-options');

function setupCustomerDropdown() {
    let customerOptionElements = document.querySelectorAll('.customer-option');

    if (customerId.value) {
        const selectedOption = Array.from(customerOptionElements).find(opt => opt.dataset.id === customerId.value);
        if (selectedOption) customerInput.value = selectedOption.dataset.name;
    }

    customerInput.addEventListener('focus', () => customerOptions.classList.remove('hidden'));
    
    customerInput.addEventListener('input', function() {
        customerOptions.classList.remove('hidden');
        const term = this.value.toLowerCase();
        let hasResults = false;
        customerId.value = ''; // clear hidden input if they type
        customerOptionElements = document.querySelectorAll('.customer-option');
        customerOptionElements.forEach(opt => {
            if (opt.dataset.search.includes(term)) {
                opt.classList.remove('hidden');
                hasResults = true;
            } else {
                opt.classList.add('hidden');
            }
        });
        document.getElementById('customer-no-results').classList.toggle('hidden', !hasResults);
    });

    customerOptionElements.forEach(opt => {
        // Prevent multiple listeners if re-setup
        opt.removeEventListener('mousedown', selectCustomerOption);
        opt.addEventListener('mousedown', selectCustomerOption);
    });

    customerInput.addEventListener('blur', function() {
        setTimeout(() => {
            customerOptions.classList.add('hidden');
            if (!customerId.value) {
                this.value = '';
            } else {
                const selectedOption = Array.from(document.querySelectorAll('.customer-option')).find(opt => opt.dataset.id === customerId.value);
                if (selectedOption) this.value = selectedOption.dataset.name;
            }
        }, 150); // small delay to allow mousedown on options to fire
    });
}

function selectCustomerOption(e) {
    e.preventDefault();
    customerId.value = this.dataset.id;
    customerInput.value = this.dataset.name;
    customerOptions.classList.add('hidden');
}

setupCustomerDropdown();

function openCustomerModal(){ const m=document.getElementById('customer-modal'); m.classList.remove('hidden'); m.classList.add('flex'); }
function closeCustomerModal(){ const m=document.getElementById('customer-modal'); m.classList.add('hidden'); m.classList.remove('flex'); }
document.getElementById('quick-customer-form')?.addEventListener('submit', async function(e){
    e.preventDefault(); const error=document.getElementById('customer-modal-error'); error.classList.add('hidden');
    const response=await fetch(@json(route('preorders.quick-customer')), {method:'POST', headers:{'X-CSRF-TOKEN':@json(csrf_token()),'Accept':'application/json'}, body:new FormData(this)});
    const data=await response.json(); if(!response.ok){ error.textContent=Object.values(data.errors||{}).flat()[0]||data.message; error.classList.remove('hidden'); return; }
    
    const newName = `${data.name} ${data.phone ? ' — '+data.phone : ''}`;
    const newDiv = document.createElement('div');
    newDiv.className = 'px-4 py-2 cursor-pointer hover:bg-blue-50 customer-option';
    newDiv.dataset.id = data.id;
    newDiv.dataset.search = (data.name+' '+(data.phone||'')).toLowerCase();
    newDiv.dataset.name = newName;
    newDiv.textContent = newName;
    
    newDiv.addEventListener('mousedown', selectCustomerOption);
    document.getElementById('customer-no-results').before(newDiv);
    
    customerId.value = data.id;
    customerInput.value = newName;
    customerOptions.classList.add('hidden');
    
    closeCustomerModal(); this.reset();
});
</script>
