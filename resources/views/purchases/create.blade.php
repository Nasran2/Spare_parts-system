@extends('layouts.app')

@section('title', 'Create Purchase')
@section('page-title', 'Add Purchase')

@section('content')
<div class="bg-white rounded-xl shadow-md p-6">
    <form action="{{ route('purchases.store') }}" method="POST" enctype="multipart/form-data" onsubmit="return submitPurchase(event)">
        @csrf

        <!-- Header Section -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <!-- Supplier -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Supplier: <span class="text-red-500">*</span></label>
                <div class="flex gap-2">
                    <select name="supplier_id" id="supplier_id" class="flex-1 border border-gray-300 rounded px-3 py-2 focus:ring-2 focus:ring-blue-500" required onchange="loadSupplierAddress()">
                        <option value="">Please Select</option>
                        @foreach($suppliers as $s)
                        <option value="{{ $s->id }}" data-address="{{ $s->address }}, {{ $s->city }}">{{ $s->name }}</option>
                        @endforeach
                    </select>
                    <button type="button" onclick="openSupplierModal()" class="px-3 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
            </div>

            <!-- Reference No -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Reference No: <i class="fas fa-info-circle text-blue-500"></i></label>
                <input type="text" name="reference_no" class="w-full border border-gray-300 rounded px-3 py-2 focus:ring-2 focus:ring-blue-500" placeholder="Optional">
            </div>

            <!-- Purchase Date -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Purchase Date: <span class="text-red-500">*</span></label>
                <input type="datetime-local" name="purchase_date" value="{{ now()->format('Y-m-d\TH:i') }}" class="w-full border border-gray-300 rounded px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
            </div>

            <!-- Purchase Status -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Purchase Status: <span class="text-red-500">*</span> <i class="fas fa-info-circle text-blue-500"></i></label>
                <select name="status" class="w-full border border-gray-300 rounded px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
                    <option value="">Please Select</option>
                    <option value="received" selected>Received</option>
                    <option value="pending">Pending</option>
                    <option value="ordered">Ordered</option>
                </select>
            </div>
        </div>

        <!-- Second Row -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <!-- Address -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Address:</label>
                <input type="text" id="supplier_address" readonly class="w-full border border-gray-300 rounded px-3 py-2 bg-gray-50" placeholder="Auto-filled">
            </div>

            <!-- Attach Document -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Attach Document:</label>
                <input type="file" name="document" accept=".pdf,.csv,.zip,.doc,.docx,.jpeg,.jpg,.png" class="w-full border border-gray-300 rounded px-3 py-2 focus:ring-2 focus:ring-blue-500">
                <p class="text-xs text-gray-500 mt-1">Max File size: 5MB<br>Allowed File: .pdf, .csv, .zip, .doc, .docx, .jpeg, .jpg, .png</p>
            </div>
        </div>

        <!-- Product Search and Add -->
        <div class="mb-4 flex gap-2">
            <div class="flex-1 relative">
                <input type="text" id="product_search" placeholder="Enter Product name / SKU / Scan bar code" class="w-full border border-gray-300 rounded px-3 py-2 pl-10 focus:ring-2 focus:ring-blue-500">
                <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                <div id="product_suggestions" class="hidden absolute left-0 right-0 mt-1 bg-white border border-gray-200 rounded shadow-lg z-30 max-h-64 overflow-y-auto"></div>
            </div>
            <button type="button" onclick="openProductModal()" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                <i class="fas fa-plus mr-2"></i>Add new product
            </button>
        </div>

        <!-- Products Table -->
        <div class="overflow-x-auto mb-6">
            <table class="w-full border-collapse">
                <thead class="bg-green-600 text-white">
                    <tr>
                        <th class="border border-gray-300 px-3 py-2 text-left text-sm">#</th>
                        <th class="border border-gray-300 px-3 py-2 text-left text-sm">Product Name</th>
                        <th class="border border-gray-300 px-3 py-2 text-right text-sm">Purchase Quantity</th>
                        <th class="border border-gray-300 px-3 py-2 text-right text-sm">Unit Cost (Before Discount)</th>
                        <th class="border border-gray-300 px-3 py-2 text-right text-sm">Discount Percent</th>
                        <th class="border border-gray-300 px-3 py-2 text-right text-sm" id="unitCostHeader">Unit Cost (Before Tax)</th>
                        <th class="border border-gray-300 px-3 py-2 text-right text-sm">Line Total</th>
                        <th class="border border-gray-300 px-3 py-2 text-right text-sm">Profit Margin</th>
                        <th class="border border-gray-300 px-3 py-2 text-right text-sm">Unit Selling Price (Inc. tax)</th>
                        <th class="border border-gray-300 px-3 py-2 text-center text-sm"><i class="fas fa-trash text-white"></i></th>
                    </tr>
                </thead>
                <tbody id="itemsBody" class="bg-white">
                    <!-- Rows will be added here by JS -->
                </tbody>
            </table>
        </div>

        <!-- Totals Section -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div></div>
            <div class="bg-gray-50 rounded p-4">
                <div class="flex justify-between mb-2">
                    <span class="font-semibold">Total Items:</span>
                    <span id="totalItems">0.00</span>
                </div>
                <div class="flex justify-between mb-4">
                    <span class="font-semibold">Net Total Amount:</span>
                    <span id="netTotal">0.00</span>
                </div>
            </div>
        </div>

        <!-- Discount and Tax Section -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Discount Type:</label>
                <select name="discount_type" id="discount_type" onchange="recalcGrandTotal()" class="w-full border border-gray-300 rounded px-3 py-2">
                    <option value="none">None</option>
                    <option value="fixed">Fixed</option>
                    <option value="percentage">Percentage</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Discount Amount:</label>
                <input type="number" name="discount_amount" id="discount_amount" value="0" step="0.01" onchange="recalcGrandTotal()" class="w-full border border-gray-300 rounded px-3 py-2">
            </div>
            <div class="flex items-end">
                <div class="text-right w-full">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Discount:</label>
                    <span id="discountDisplay" class="text-lg font-semibold">(-) 0.00</span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Purchase Tax:</label>
                <select name="tax_id" id="tax_id" onchange="recalcGrandTotal()" class="w-full border border-gray-300 rounded px-3 py-2">
                    <option value="">None</option>
                    <option value="vat_10">VAT 10%</option>
                    <option value="vat_5">VAT 5%</option>
                </select>
            </div>
            <div></div>
            <div class="flex items-end">
                <div class="text-right w-full">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Purchase Tax:</label>
                    <span id="taxDisplay" class="text-lg font-semibold">(+) 0.00</span>
                </div>
            </div>
        </div>

        <!-- Shipping Cost Section -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Shipping Cost:</label>
                <input type="number" name="shipping_cost" id="shipping_cost" value="0" step="0.01" oninput="applyShippingToAll(); recalcGrandTotal();" class="w-full border border-gray-300 rounded px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Shipping Handling:</label>
                <select name="shipping_type" id="shipping_type" onchange="applyShippingToAll(); recalcGrandTotal();" class="w-full border border-gray-300 rounded px-3 py-2">
                    <option value="divided" selected>Divide to Product Cost</option>
                    <option value="expense">Add as Expense</option>
                </select>
            </div>
            <div class="flex items-end">
                <div class="text-right w-full">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Shipping Cost:</label>
                    <span id="shippingDisplay" class="text-lg font-semibold">(+) 0.00</span>
                </div>
            </div>
        </div>

        <!-- Payment Section -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6 bg-blue-50 p-4 rounded-lg">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Payment Method: <span class="text-red-500">*</span></label>
                <select name="payment_method" id="payment_method" class="w-full border border-gray-300 rounded px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
                    <option value="">Please Select</option>
                    <option value="cash">Cash</option>
                    <option value="card">Card</option>
                    <option value="bank_transfer">Bank Transfer</option>
                    <option value="cheque">Cheque</option>
                    <option value="credit">Credit</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Payment Amount:</label>
                <input type="number" name="payment_amount" id="payment_amount" value="0" step="0.01" oninput="recalcPayment()" class="w-full border border-gray-300 rounded px-3 py-2 focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="flex items-end">
                <div class="text-right w-full">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Grand Total:</label>
                    <span id="grandTotal" class="text-xl font-bold text-blue-600">0.00</span>
                    <br>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Due Amount:</label>
                    <span id="dueAmount" class="text-xl font-bold text-red-600">0.00</span>
                </div>
            </div>
        </div>

        <!-- Additional Notes -->
        <div class="mb-6">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Additional Notes</label>
            <textarea name="notes" rows="3" class="w-full border border-gray-300 rounded px-3 py-2 focus:ring-2 focus:ring-blue-500"></textarea>
        </div>

        <!-- Submit Button -->
        <div class="flex items-center gap-2">
            <button type="submit" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-semibold">
                <i class="fas fa-save mr-2"></i>Save Purchase
            </button>
            <a href="{{ route('purchases.index') }}" class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                Cancel
            </a>
        </div>

        <!-- hidden container for items -->
        <div id="hiddenItems"></div>
    </form>
</div>

<!-- Product Create Modal -->
<div id="productModal" class="fixed inset-0 hidden items-center justify-center z-50">
    <div class="absolute inset-0 bg-black opacity-50" onclick="closeProductModal()"></div>
    <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl relative z-10 p-6 max-h-screen overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold">Add New Product</h3>
            <button onclick="closeProductModal()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
        </div>
        <form id="quickProductForm">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Product Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" required class="w-full border rounded px-3 py-2" />
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">SKU / Barcode</label>
                    <input type="text" name="sku" class="w-full border rounded px-3 py-2" placeholder="Auto-generated if left blank" />
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Unit <span class="text-red-500">*</span></label>
                    <select name="unit_id" class="w-full border rounded px-3 py-2" required>
                        <option value="">-- Select --</option>
                        @foreach(\App\Models\Unit::where('is_active', true)->get() as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}{{ $u->short_name ? ' (' . $u->short_name . ')' : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Category</label>
                    <select name="categories[]" class="w-full border rounded px-3 py-2">
                        <option value="">-- Select --</option>
                        @foreach(\App\Models\Category::where('is_active', true)->get() as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Brand</label>
                    <select name="brands[]" class="w-full border rounded px-3 py-2">
                        <option value="">-- Select --</option>
                        @foreach(\App\Models\Brand::where('is_active', true)->get() as $b)
                        <option value="{{ $b->id }}">{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Show Unit Prices For</label>
                    <p class="text-xs text-gray-500 mb-2">Uncheck units you do not want to display. Leave all checked to show prices for every unit.</p>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2 border rounded px-3 py-2 bg-gray-50">
                        @foreach(\App\Models\Unit::where('is_active', true)->get() as $u)
                            @php
                                $m = rtrim(rtrim(number_format((float)$u->base_unit_multiplier, 3, '.', ''), '0'), '.');
                            @endphp
                            <label class="flex items-center space-x-2 px-2 py-1 border rounded bg-white">
                                <input type="checkbox" name="visible_units[]" value="{{ $u->id }}" class="text-blue-600 rounded" checked>
                                <span class="text-xs text-gray-700">{{ $u->short_name ?: $u->name }} (x{{ $m }})</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Cost Price <span class="text-red-500">*</span></label>
                    <input type="number" step="0.01" name="cost_price" required class="w-full border rounded px-3 py-2" />
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Selling Price <span class="text-red-500">*</span></label>
                    <input type="number" step="0.01" name="selling_price" required class="w-full border rounded px-3 py-2" />
                </div>
                @if((bool) \App\Models\Setting::get('barcode_enable_selling_secret_code', false))
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Secret Selling Code</label>
                    <input type="text" id="quick_secret_selling_code" class="w-full border rounded px-3 py-2" placeholder="Type secret code to fill selling price" />
                </div>
                @endif
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Secret Cost Code</label>
                    <input type="text" id="quick_secret_cost_code" class="w-full border rounded px-3 py-2" placeholder="Type secret code to fill cost price" />
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Profit Margin</label>
                    <div class="flex gap-2">
                        <input type="number" step="0.01" min="0" id="quick_profit_margin_percent" class="w-full border rounded px-3 py-2" placeholder="Margin %" />
                        <input type="number" step="0.01" min="0" id="quick_profit_margin_fixed" class="w-full border rounded px-3 py-2" placeholder="Fixed" />
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Stock Quantity <span class="text-red-500">*</span></label>
                    <input type="number" name="stock_quantity" value="0" min="0" required class="w-full border rounded px-3 py-2" />
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Alert Quantity <span class="text-red-500">*</span></label>
                    <input type="number" name="alert_quantity" value="1" min="0" required class="w-full border rounded px-3 py-2" />
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="2" class="w-full border rounded px-3 py-2" placeholder="Optional"></textarea>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Product Image</label>
                    <input type="file" name="image" accept="image/*" class="w-full border rounded px-3 py-2" />
                </div>
            </div>
            <div class="mt-6 flex items-center gap-2">
                <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                    <i class="fas fa-plus mr-2"></i>Create Product
                </button>
                <button type="button" onclick="closeProductModal()" class="px-6 py-2 bg-gray-200 rounded hover:bg-gray-300">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Supplier Modal -->
<div id="supplierModal" class="fixed inset-0 hidden items-center justify-center z-50">
    <div class="absolute inset-0 bg-black opacity-50" onclick="closeSupplierModal()"></div>
    <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl relative z-10 p-6 max-h-screen overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold">Add New Supplier</h3>
            <button onclick="closeSupplierModal()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
        </div>
        <form id="quickSupplierForm">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" required class="w-full border rounded px-3 py-2" />
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Company Name</label>
                    <input type="text" name="company_name" class="w-full border rounded px-3 py-2" />
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" class="w-full border rounded px-3 py-2" />
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Phone <span class="text-red-500">*</span></label>
                    <input type="text" name="phone" required class="w-full border rounded px-3 py-2" />
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Address</label>
                    <input type="text" name="address" class="w-full border rounded px-3 py-2" />
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">City</label>
                    <input type="text" name="city" class="w-full border rounded px-3 py-2" />
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Country</label>
                    <input type="text" name="country" class="w-full border rounded px-3 py-2" />
                </div>
            </div>
            <div class="mt-6 flex items-center gap-2">
                <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                    <i class="fas fa-plus mr-2"></i>Create Supplier
                </button>
                <button type="button" onclick="closeSupplierModal()" class="px-6 py-2 bg-gray-200 rounded hover:bg-gray-300">Cancel</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
const PRODUCTS = @json($productsData);
const QUICK_COST_CODE_MAP = @json((array) \App\Models\Setting::get('barcode_cost_code_map'));
const QUICK_SELLING_CODE_MAP = @json((array) \App\Models\Setting::get('barcode_selling_code_map'));
if (!Object.keys(QUICK_COST_CODE_MAP || {}).length) {
    Object.assign(QUICK_COST_CODE_MAP, {
        '0': 'E',
        '1': 'M',
        '2': 'O',
        '3': 'D',
        '4': 'T',
        '5': 'W',
        '6': 'I',
        '7': 'N',
        '8': 'K',
        '9': 'L'
    });
}
if (!Object.keys(QUICK_SELLING_CODE_MAP || {}).length) {
    Object.assign(QUICK_SELLING_CODE_MAP, QUICK_COST_CODE_MAP);
}
let rowIndex = 0;
let paymentAmountUserEdited = false;

function formatMoney(v) {
    return Number(v || 0).toFixed(2);
}

function loadSupplierAddress() {
    const sel = document.getElementById('supplier_id');
    const opt = sel.selectedOptions[0];
    document.getElementById('supplier_address').value = opt ? (opt.dataset.address || '') : '';
}

function addItemRow(productData = null) {
    const body = document.getElementById('itemsBody');
    rowIndex++;
    const tr = document.createElement('tr');
    tr.className = 'border-b hover:bg-gray-50';
    tr.dataset.index = rowIndex;

    const productOptions = PRODUCTS.map(p => 
        `<option value="${p.id}" data-cost="${p.cost_price}" data-selling="${p.selling_price}">${p.name}</option>`
    ).join('');
    
    const selectedProduct = productData || {};
    const selectedId = selectedProduct.id || '';
    const selectedName = selectedProduct.name || '';
    
    tr.innerHTML = `
        <td class="border px-2 py-2 text-center">${rowIndex}</td>
        <td class="border px-2 py-2">
            <select class="product-select w-full border-0 focus:ring-0 text-sm" onchange="onProductChange(this)" data-product-id="${selectedId}">
                <option value="">-- Select --</option>
                ${productOptions}
            </select>
        </td>
        <td class="border px-2 py-2">
            <input type="number" class="qty-input w-full text-right border-0 focus:ring-0 text-sm" value="1" min="1" oninput="recalcRow(this); applyShippingToAll();" />
        </td>
        <td class="border px-2 py-2">
            <input type="number" step="0.01" class="cost-before-discount-input w-full text-right border-0 focus:ring-0 text-sm" value="${selectedProduct.cost_price || 0}" data-original-cost="${selectedProduct.cost_price || 0}" oninput="recalcRow(this)" />
        </td>
        <td class="border px-2 py-2">
            <input type="number" step="0.01" class="discount-percent-input w-full text-right border-0 focus:ring-0 text-sm" value="0" oninput="recalcRow(this)" />
        </td>
        <td class="border px-2 py-2">
            <input type="number" step="0.01" class="cost-input w-full text-right border-0 focus:ring-0 text-sm" value="${selectedProduct.cost_price || 0}" oninput="recalcRow(this)" data-editable="true" />
        </td>
        <td class="border px-2 py-2 text-right">
            <span class="row-total text-sm font-semibold">0.00</span>
        </td>
        <td class="border px-2 py-2">
            <div class="flex items-center">
                <input type="number" step="0.01" class="profit-margin-input w-full text-right border-0 focus:ring-0 text-sm" value="20" oninput="recalcSellingPrice(this, true)" />
                <select class="profit-type-select text-xs border-0 focus:ring-0 bg-transparent" onchange="recalcSellingPrice(this, true)">
                    <option value="percent">%</option>
                    <option value="fixed">Fixed</option>
                </select>
            </div>
        </td>
        <td class="border px-2 py-2">
            <input type="number" step="0.01" class="sell-input w-full text-right border-0 focus:ring-0 text-sm" value="${selectedProduct.selling_price || 0}" />
        </td>
        <td class="border px-2 py-2 text-center">
            <button type="button" class="text-red-600 hover:text-red-800" onclick="removeRow(this)">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    `;
    body.appendChild(tr);
    
    // If product was passed, select it in the dropdown
    if (selectedId) {
        const select = tr.querySelector('.product-select');
        select.value = selectedId;
        select.dataset.productId = selectedId;
    }
    
    recalcRow(tr.querySelector('.qty-input'));
    applyShippingToAll();
}

function onProductChange(sel) {
    const opt = sel.selectedOptions[0];
    const tr = sel.closest('tr');
    if (opt && opt.dataset.cost) {
        const cost = parseFloat(opt.dataset.cost || 0);
        const selling = parseFloat(opt.dataset.selling || 0);
        tr.querySelector('.cost-before-discount-input').value = cost;
        tr.querySelector('.cost-before-discount-input').dataset.originalCost = cost;
        tr.querySelector('.cost-input').value = cost;
        const sellInput = tr.querySelector('.sell-input');
        sellInput.value = selling;
        sellInput.dataset.userEdited = '';
    }
    recalcRow(sel);
    applyShippingToAll();
}

function recalcRow(el) {
    const tr = el.closest('tr');
    const costBeforeDiscount = parseFloat(tr.querySelector('.cost-before-discount-input').value || 0);
    const discountPercent = parseFloat(tr.querySelector('.discount-percent-input').value || 0);
    const qty = parseInt(tr.querySelector('.qty-input').value || 0);
    
    // Calculate cost after discount (but before shipping)
    const discountAmount = costBeforeDiscount * (discountPercent / 100);
    const costAfterDiscount = costBeforeDiscount - discountAmount;
    
    // Don't overwrite if user is manually editing
    const costInput = tr.querySelector('.cost-input');
    if (document.activeElement !== costInput || !costInput.dataset.userEdited) {
        costInput.value = costAfterDiscount.toFixed(2);
    }
    
    // Calculate line total
    const finalCost = parseFloat(costInput.value || 0);
    const total = finalCost * qty;
    tr.querySelector('.row-total').textContent = formatMoney(total);
    
    recalcSellingPrice(tr.querySelector('.profit-margin-input'));
    recalcGrandTotal();
}

function applyShippingToAll() {
    const shippingCost = parseFloat(document.getElementById('shipping_cost').value || 0);
    const shippingType = document.getElementById('shipping_type').value;
    const headerElement = document.getElementById('unitCostHeader');
    
    if (shippingType === 'expense') {
        // Reset all costs to original (after discount)
        document.querySelectorAll('#itemsBody tr').forEach(tr => {
            const costBeforeDiscount = parseFloat(tr.querySelector('.cost-before-discount-input').value || 0);
            const discountPercent = parseFloat(tr.querySelector('.discount-percent-input').value || 0);
            
            // Calculate cost with only discount (no shipping)
            const discountAmount = costBeforeDiscount * (discountPercent / 100);
            const costAfterDiscount = costBeforeDiscount - discountAmount;
            
            const costInput = tr.querySelector('.cost-input');
            costInput.value = costAfterDiscount.toFixed(2);
            costInput.style.backgroundColor = ''; // Remove yellow highlight
            costInput.title = ''; // Remove tooltip
            costInput.dataset.userEdited = ''; // Reset user-edited flag
            
            // Recalculate line total
            const qty = parseInt(tr.querySelector('.qty-input').value || 0);
            const total = costAfterDiscount * qty;
            tr.querySelector('.row-total').textContent = formatMoney(total);
            
            recalcSellingPrice(tr.querySelector('.profit-margin-input'));
        });
        
        // Update header text to remove "(Before Tax)"
        headerElement.textContent = 'Unit Cost';
        
        recalcGrandTotal();
        return;
    }
    
    // Update header to show "(Before Tax)" when dividing
    headerElement.textContent = 'Unit Cost (Before Tax)';
    
    if (shippingCost === 0) {
        // If shipping is 0, just reset to original costs
        document.querySelectorAll('#itemsBody tr').forEach(tr => {
            const costBeforeDiscount = parseFloat(tr.querySelector('.cost-before-discount-input').value || 0);
            const discountPercent = parseFloat(tr.querySelector('.discount-percent-input').value || 0);
            
            const discountAmount = costBeforeDiscount * (discountPercent / 100);
            const costAfterDiscount = costBeforeDiscount - discountAmount;
            
            const costInput = tr.querySelector('.cost-input');
            costInput.value = costAfterDiscount.toFixed(2);
            costInput.style.backgroundColor = '';
            costInput.title = '';
            
            const qty = parseInt(tr.querySelector('.qty-input').value || 0);
            const total = costAfterDiscount * qty;
            tr.querySelector('.row-total').textContent = formatMoney(total);
            
            recalcSellingPrice(tr.querySelector('.profit-margin-input'));
        });
        recalcGrandTotal();
        return;
    }
    
    // Calculate total quantity
    let totalQty = 0;
    document.querySelectorAll('#itemsBody tr').forEach(tr => {
        totalQty += parseInt(tr.querySelector('.qty-input').value || 0);
    });
    
    if (totalQty === 0) return;
    
    const shippingPerItem = shippingCost / totalQty;
    
    // Apply shipping to each row
    document.querySelectorAll('#itemsBody tr').forEach(tr => {
        const costBeforeDiscount = parseFloat(tr.querySelector('.cost-before-discount-input').value || 0);
        const discountPercent = parseFloat(tr.querySelector('.discount-percent-input').value || 0);
        
        // Calculate cost with discount and shipping
        const discountAmount = costBeforeDiscount * (discountPercent / 100);
        const costAfterDiscount = costBeforeDiscount - discountAmount;
        const costWithShipping = costAfterDiscount + shippingPerItem;
        
        // Update the cost input to show shipping included
        const costInput = tr.querySelector('.cost-input');
        if (!costInput.dataset.userEdited) {
            costInput.value = costWithShipping.toFixed(2);
            costInput.style.backgroundColor = '#fef3c7'; // Light yellow to indicate shipping added
            costInput.title = `Original: ${costAfterDiscount.toFixed(2)} + Shipping: ${shippingPerItem.toFixed(2)}`;
        }
        
        // Recalculate line total
        const qty = parseInt(tr.querySelector('.qty-input').value || 0);
        const total = parseFloat(costInput.value) * qty;
        tr.querySelector('.row-total').textContent = formatMoney(total);
        
        recalcSellingPrice(tr.querySelector('.profit-margin-input'));
    });
    
    recalcGrandTotal();
}

// Mark cost input as user-edited when manually changed
document.addEventListener('DOMContentLoaded', function() {
    document.addEventListener('input', function(e) {
        if (e.target.classList.contains('cost-input')) {
            e.target.dataset.userEdited = 'true';
        }

        if (e.target.classList.contains('sell-input')) {
            e.target.dataset.userEdited = 'true';

            const tr = e.target.closest('tr');
            if (!tr) return;

            const cost = parseFloat(tr.querySelector('.cost-input').value || 0);
            const sell = parseFloat(e.target.value || 0);
            const type = tr.querySelector('.profit-type-select').value;
            const marginInput = tr.querySelector('.profit-margin-input');

            if (!Number.isFinite(cost) || !Number.isFinite(sell)) return;

            const fixedMargin = sell - cost;
            const percentMargin = sell > 0 ? (fixedMargin / sell) * 100 : 0;
            marginInput.value = (type === 'percent' ? percentMargin : fixedMargin).toFixed(2);
        }
    });

    const paymentInput = document.getElementById('payment_amount');
    if (paymentInput) {
        paymentInput.addEventListener('input', function () {
            const val = String(paymentInput.value ?? '').trim();
            const num = parseFloat(val || '0');
            paymentAmountUserEdited = Number.isFinite(num) && num !== 0;
        });
    }

    const paymentMethod = document.getElementById('payment_method');
    if (paymentMethod) {
        paymentMethod.addEventListener('change', function () {
            if (paymentAmountUserEdited) return;
            const gt = parseFloat(document.getElementById('grandTotal')?.textContent || '0');
            if (paymentMethod.value === 'credit') {
                if (paymentInput) paymentInput.value = '0.00';
            } else {
                if (paymentInput) paymentInput.value = (Number.isFinite(gt) ? gt : 0).toFixed(2);
            }
            recalcPayment();
        });
    }
});

function recalcSellingPrice(el, force = false) {
    const tr = el.closest('tr');
    const cost = parseFloat(tr.querySelector('.cost-input').value || 0);
    const marginInput = tr.querySelector('.profit-margin-input');
    const margin = parseFloat(marginInput.value || 0);
    const type = tr.querySelector('.profit-type-select').value;
    const sellInput = tr.querySelector('.sell-input');

    if (!force && sellInput.dataset.userEdited) {
        return;
    }
    
    let selling = 0;
    if (type === 'percent') {
        selling = cost * (1 + margin / 100);
    } else {
        selling = cost + margin;
    }
    
    sellInput.value = selling.toFixed(2);
    if (force) {
        sellInput.dataset.userEdited = '';
    }
}

function removeRow(btn) {
    btn.closest('tr').remove();
    applyShippingToAll();
    recalcGrandTotal();
}

function recalcGrandTotal() {
    let itemCount = 0;
    let netTotal = 0;
    let totalQty = 0;
    
    const rows = document.querySelectorAll('#itemsBody tr');
    rows.forEach(tr => {
        const cost = parseFloat(tr.querySelector('.cost-input').value || 0);
        const qty = parseInt(tr.querySelector('.qty-input').value || 0);
        netTotal += cost * qty;
        itemCount += qty;
        totalQty += qty;
    });
    
    document.getElementById('totalItems').textContent = formatMoney(itemCount);
    document.getElementById('netTotal').textContent = formatMoney(netTotal);
    
    // Apply discount
    const discountType = document.getElementById('discount_type').value;
    const discountAmount = parseFloat(document.getElementById('discount_amount').value || 0);
    let discount = 0;
    
    if (discountType === 'fixed') {
        discount = discountAmount;
    } else if (discountType === 'percentage') {
        discount = netTotal * (discountAmount / 100);
    }
    
    document.getElementById('discountDisplay').textContent = '(-) ' + formatMoney(discount);
    
    // Apply tax
    const taxId = document.getElementById('tax_id').value;
    let tax = 0;
    if (taxId === 'vat_10') {
        tax = (netTotal - discount) * 0.10;
    } else if (taxId === 'vat_5') {
        tax = (netTotal - discount) * 0.05;
    }
    
    document.getElementById('taxDisplay').textContent = '(+) ' + formatMoney(tax);
    
    // Apply shipping cost
    const shippingCost = parseFloat(document.getElementById('shipping_cost').value || 0);
    const shippingType = document.getElementById('shipping_type').value;
    
    let shippingToAdd = 0;
    if (shippingType === 'divided' && totalQty > 0) {
        // When divided, shipping is already included in product costs
        // So we don't add it again to grand total
        shippingToAdd = 0;
    } else if (shippingType === 'expense') {
        // Add as expense to grand total
        shippingToAdd = shippingCost;
    }
    
    document.getElementById('shippingDisplay').textContent = '(+) ' + formatMoney(shippingCost);
    
    // Calculate grand total
    const grandTotal = netTotal - discount + tax + shippingToAdd;
    document.getElementById('grandTotal').textContent = formatMoney(grandTotal);
    
    // Auto-fill payment amount
    const paymentInput = document.getElementById('payment_amount');
    const paymentMethod = document.getElementById('payment_method');
    const method = paymentMethod ? String(paymentMethod.value || '') : '';
    if (paymentInput && !paymentAmountUserEdited) {
        if (method === 'credit') {
            paymentInput.value = '0.00';
        } else {
            paymentInput.value = grandTotal.toFixed(2);
        }
    }
    // Calculate and show due amount
    const paymentAmount = parseFloat(paymentInput?.value || 0);
    const dueAmount = grandTotal - paymentAmount;
    document.getElementById('dueAmount').textContent = formatMoney(dueAmount);
}

function recalcPayment() {
    // Show due amount in real time
    const grandTotal = parseFloat(document.getElementById('grandTotal').textContent || 0);
    const paymentAmount = parseFloat(document.getElementById('payment_amount').value || 0);
    const dueAmount = grandTotal - paymentAmount;
    document.getElementById('dueAmount').textContent = formatMoney(dueAmount);
}

function submitPurchase(e) {
    const container = document.getElementById('hiddenItems');
    container.innerHTML = '';
    const rows = document.querySelectorAll('#itemsBody tr');
    
    if (rows.length === 0) {
        alert('Please add at least one product');
        e.preventDefault();
        return false;
    }
    
    rows.forEach((tr, i) => {
        const sel = tr.querySelector('.product-select');
        const pid = sel.value;
        if (!pid) return;
        
        const qty = tr.querySelector('.qty-input').value;
        const cost = tr.querySelector('.cost-input').value;
        const sell = tr.querySelector('.sell-input').value;
        
        ['product_id','quantity','unit_cost','selling_price'].forEach((field, idx) => {
            const val = [pid, qty, cost, sell][idx];
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = `items[${i}][${field}]`;
            input.value = val;
            container.appendChild(input);
        });
    });
    
    return true;
}

// Product Modal
function openProductModal() {
    document.getElementById('productModal').classList.remove('hidden');
    document.getElementById('productModal').classList.add('flex');
}
function closeProductModal() {
    document.getElementById('productModal').classList.add('hidden');
}

function setupQuickProductPricingTools() {
    const form = document.getElementById('quickProductForm');
    if (!form) return;

    const costInput = form.querySelector('input[name="cost_price"]');
    const sellingInput = form.querySelector('input[name="selling_price"]');
    const percentInput = document.getElementById('quick_profit_margin_percent');
    const fixedInput = document.getElementById('quick_profit_margin_fixed');
    const secretCodeInput = document.getElementById('quick_secret_cost_code');
    const sellingSecretCodeInput = document.getElementById('quick_secret_selling_code');

    if (!costInput || !sellingInput || !percentInput || !fixedInput || !secretCodeInput) return;

    const reverseCostMap = Object.fromEntries(
        Object.entries(QUICK_COST_CODE_MAP).map(([digit, code]) => [String(code || '').toUpperCase(), String(digit)])
    );
    const reverseSellingMap = Object.fromEntries(
        Object.entries(QUICK_SELLING_CODE_MAP).map(([digit, code]) => [String(code || '').toUpperCase(), String(digit)])
    );

    const decodeSecretToNumber = (value, reverseMap) => {
        const raw = (value || '').toUpperCase().trim();
        if (!raw) return null;

        let decoded = '';
        for (const ch of raw) {
            if (ch === '.') {
                decoded += '.';
                continue;
            }
            if (!(ch in reverseMap)) {
                return null;
            }
            decoded += reverseMap[ch];
        }

        const numeric = Number(decoded);
        return Number.isFinite(numeric) ? numeric : null;
    };

    const encodeNumberToSecret = (value, map) => {
        const numeric = Number(value);
        if (!Number.isFinite(numeric)) {
            return '';
        }
        const fixed = numeric.toFixed(2);
        let encoded = '';
        for (const ch of fixed) {
            if (ch === '.') {
                encoded += '.';
                continue;
            }
            encoded += map[ch] ?? ch;
        }
        return encoded;
    };

    const onPercentageChange = () => {
        const cost = parseFloat(costInput.value || 0);
        const percentage = parseFloat(percentInput.value || 0);
        const denominator = 1 - (percentage / 100);

        if (cost > 0 && percentage >= 0 && denominator > 0) {
            const selling = cost / denominator;
            const fixedAmount = selling - cost;
            fixedInput.value = fixedAmount.toFixed(2);
            sellingInput.value = selling.toFixed(2);
        } else {
            fixedInput.value = '';
            sellingInput.value = cost.toFixed(2);
        }
    };

    const onFixedAmountChange = () => {
        const cost = parseFloat(costInput.value || 0);
        const fixedAmount = parseFloat(fixedInput.value || 0);

        if (cost > 0 && fixedAmount >= 0) {
            const selling = cost + fixedAmount;
            const percentage = selling > 0 ? (fixedAmount / selling) * 100 : 0;
            percentInput.value = percentage.toFixed(2);
            sellingInput.value = selling.toFixed(2);
        } else {
            percentInput.value = '';
            sellingInput.value = cost.toFixed(2);
        }
    };

    const onSellingPriceChange = () => {
        const cost = parseFloat(costInput.value || 0);
        const selling = parseFloat(sellingInput.value || 0);
        const fixedAmount = selling - cost;
        const percentage = selling > 0 ? (fixedAmount / selling) * 100 : 0;

        if (Number.isFinite(fixedAmount)) {
            fixedInput.value = fixedAmount.toFixed(2);
        }
        if (Number.isFinite(percentage)) {
            percentInput.value = percentage.toFixed(2);
        }
    };

    const onCostPriceChange = () => {
        if ((fixedInput.value || '') !== '') {
            onFixedAmountChange();
            return;
        }
        if ((percentInput.value || '') !== '') {
            onPercentageChange();
            return;
        }
        const cost = parseFloat(costInput.value || 0);
        sellingInput.value = cost.toFixed(2);
        if (secretCodeInput) {
            secretCodeInput.value = encodeNumberToSecret(costInput.value || 0, QUICK_COST_CODE_MAP);
        }
        if (sellingSecretCodeInput) {
            sellingSecretCodeInput.value = encodeNumberToSecret(sellingInput.value || 0, QUICK_SELLING_CODE_MAP);
        }
    };

    const onSecretCodeInput = () => {
        const numeric = decodeSecretToNumber(secretCodeInput.value || '', reverseCostMap);
        if (!Number.isFinite(numeric)) return;

        costInput.value = numeric.toFixed(2);
        onCostPriceChange();
    };

    const onSellingSecretCodeInput = () => {
        if (!sellingSecretCodeInput) return;
        const numeric = decodeSecretToNumber(sellingSecretCodeInput.value || '', reverseSellingMap);
        if (!Number.isFinite(numeric)) return;

        sellingInput.value = numeric.toFixed(2);
        onSellingPriceChange();
    };

    percentInput.addEventListener('input', onPercentageChange);
    fixedInput.addEventListener('input', onFixedAmountChange);
    sellingInput.addEventListener('input', onSellingPriceChange);
    costInput.addEventListener('input', onCostPriceChange);
    secretCodeInput.addEventListener('input', onSecretCodeInput);

    sellingInput.addEventListener('input', () => {
        if (!sellingSecretCodeInput || document.activeElement === sellingSecretCodeInput) return;
        sellingSecretCodeInput.value = encodeNumberToSecret(sellingInput.value || 0, QUICK_SELLING_CODE_MAP);
    });

    if (sellingSecretCodeInput) {
        sellingSecretCodeInput.addEventListener('input', onSellingSecretCodeInput);
    }

    secretCodeInput.value = encodeNumberToSecret(costInput.value || 0, QUICK_COST_CODE_MAP);
    if (sellingSecretCodeInput) {
        sellingSecretCodeInput.value = encodeNumberToSecret(sellingInput.value || 0, QUICK_SELLING_CODE_MAP);
    }
}

setupQuickProductPricingTools();

document.getElementById('quickProductForm').addEventListener('submit', async function(e){
    e.preventDefault();
    const data = new FormData(e.target);
    
    try {
        const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const res = await fetch("{{ route('products.store') }}", {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
            body: data
        });
        const json = await res.json();
        if (!json.success) {
            alert(json.message || 'Failed to create product');
            return;
        }
        const p = json.product;
        PRODUCTS.push({
            id: p.id,
            name: p.name,
            cost_price: parseFloat(p.cost_price),
            selling_price: parseFloat(p.selling_price),
            sku: p.sku || '',
            barcode: p.barcode || ''
        });
        
        // Update all selects
        document.querySelectorAll('.product-select').forEach(sel => {
            const opt = document.createElement('option');
            opt.value = p.id;
            opt.dataset.cost = p.cost_price;
            opt.dataset.selling = p.selling_price;
            opt.textContent = p.name;
            sel.appendChild(opt);
        });
        
        // Check if product already exists in table
        let existingRow = null;
        document.querySelectorAll('#itemsBody tr').forEach(tr => {
            const select = tr.querySelector('.product-select');
            if (select && select.value == p.id) {
                existingRow = tr;
            }
        });
        
        if (existingRow) {
            // Increase quantity of existing row
            const qtyInput = existingRow.querySelector('.qty-input');
            qtyInput.value = parseInt(qtyInput.value || 0) + 1;
            recalcRow(qtyInput);
        } else {
            // Add a new row with this product selected
            addItemRow(p);
        }
        
        closeProductModal();
        e.target.reset();
    } catch (err) {
        console.error(err);
        alert('Error creating product');
    }
});

// Supplier Modal
function openSupplierModal() {
    document.getElementById('supplierModal').classList.remove('hidden');
    document.getElementById('supplierModal').classList.add('flex');
}
function closeSupplierModal() {
    document.getElementById('supplierModal').classList.add('hidden');
}

document.getElementById('quickSupplierForm').addEventListener('submit', async function(e){
    e.preventDefault();
    const data = new FormData(e.target);
    
    try {
        const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const res = await fetch("{{ route('suppliers.store') }}", {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
            body: data
        });
        const json = await res.json();
        if (!json.success) {
            alert(json.message || 'Failed to create supplier');
            return;
        }
        const s = json.supplier;
        const sel = document.getElementById('supplier_id');
        const opt = document.createElement('option');
        opt.value = s.id;
        opt.dataset.address = (s.address || '') + ', ' + (s.city || '');
        opt.textContent = s.name;
        sel.appendChild(opt);
        sel.value = s.id;
        loadSupplierAddress();
        
        closeSupplierModal();
        e.target.reset();
    } catch (err) {
        console.error(err);
        alert('Error creating supplier');
    }
});

// Product search / barcode scan handling
const productSearchInput = document.getElementById('product_search');
const productSuggestions = document.getElementById('product_suggestions');
let productSearchTimer = null;
let lastAutoAddSignature = '';
let lastAutoAddAt = 0;
const AUTO_ADD_DEDUPE_MS = 300;

function normalizeTerm(value) {
    return String(value || '').trim().toLowerCase();
}

function addOrIncrementProduct(found) {
    let existingRow = null;
    document.querySelectorAll('#itemsBody tr').forEach(tr => {
        const select = tr.querySelector('.product-select');
        if (select && select.value == found.id) {
            existingRow = tr;
        }
    });

    if (existingRow) {
        const qtyInput = existingRow.querySelector('.qty-input');
        qtyInput.value = parseInt(qtyInput.value || 0) + 1;
        recalcRow(qtyInput);
    } else {
        addItemRow(found);
    }
}

function hideProductSuggestions() {
    productSuggestions.innerHTML = '';
    productSuggestions.classList.add('hidden');
}

function findExactProduct(term) {
    return PRODUCTS.find(p => {
        const name = normalizeTerm(p.name);
        const sku = normalizeTerm(p.sku);
        const barcode = normalizeTerm(p.barcode);
        return name === term || sku === term || barcode === term;
    }) || null;
}

function getPartialMatches(term, limit = 8) {
    return PRODUCTS
        .filter(p => {
            const name = normalizeTerm(p.name);
            const sku = normalizeTerm(p.sku);
            const barcode = normalizeTerm(p.barcode);
            return name.includes(term) || sku.includes(term) || barcode.includes(term);
        })
        .slice(0, limit);
}

function tryAutoAddExact(term, source = 'input') {
    const found = findExactProduct(term);
    if (!found) return false;

    const now = Date.now();
    const signature = `${source}:${term}:${found.id}`;
    if (signature === lastAutoAddSignature && (now - lastAutoAddAt) < AUTO_ADD_DEDUPE_MS) {
        return true;
    }

    lastAutoAddSignature = signature;
    lastAutoAddAt = now;
    addOrIncrementProduct(found);
    productSearchInput.value = '';
    hideProductSuggestions();
    return true;
}

function renderProductSuggestions(matches) {
    if (!matches.length) {
        hideProductSuggestions();
        return;
    }

    productSuggestions.innerHTML = matches.map(p => {
        const name = p.name || '';
        const sku = p.sku || '-';
        const barcode = p.barcode || '-';
        return `
            <button
                type="button"
                class="w-full text-left px-3 py-2 hover:bg-gray-50 border-b border-gray-100 last:border-b-0"
                data-product-id="${p.id}"
            >
                <div class="text-sm font-medium text-gray-800">${name}</div>
                <div class="text-xs text-gray-500">SKU: ${sku} | Barcode: ${barcode}</div>
            </button>
        `;
    }).join('');

    productSuggestions.classList.remove('hidden');
}

function handleProductSearchInput(rawTerm) {
    const term = normalizeTerm(rawTerm);
    if (!term) {
        hideProductSuggestions();
        return;
    }

    if (tryAutoAddExact(term, 'input')) {
        return;
    }

    if (term.length < 2) {
        hideProductSuggestions();
        return;
    }

    const matches = getPartialMatches(term);
    renderProductSuggestions(matches);
}

productSearchInput.addEventListener('keydown', function(e) {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    clearTimeout(productSearchTimer);
    const term = normalizeTerm(e.target.value);
    if (!term) return;
    tryAutoAddExact(term, 'enter');
});

productSearchInput.addEventListener('input', function(e) {
    clearTimeout(productSearchTimer);
    const term = e.target.value;
    productSearchTimer = setTimeout(() => {
        handleProductSearchInput(term);
    }, 180);
});

productSuggestions.addEventListener('click', function(e) {
    const btn = e.target.closest('[data-product-id]');
    if (!btn) return;

    const productId = btn.getAttribute('data-product-id');
    const found = PRODUCTS.find(p => String(p.id) === String(productId));
    if (!found) return;

    addOrIncrementProduct(found);
    productSearchInput.value = '';
    hideProductSuggestions();
    productSearchInput.focus();
});

document.addEventListener('click', function(e) {
    if (!productSuggestions.contains(e.target) && e.target !== productSearchInput) {
        hideProductSuggestions();
    }
});

// Don't start with a default row - user will add products via search or button
</script>
@endpush
@endsection
