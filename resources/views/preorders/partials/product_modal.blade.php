<!-- Product Create Modal -->
<div id="createProductSyncModal" class="fixed inset-0 hidden items-center justify-center z-50">
    <div class="absolute inset-0 bg-black opacity-50" onclick="closeModal('createProductSyncModal')"></div>
    <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl relative z-10 p-6 max-h-screen overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold">Add New Product</h3>
            <button onclick="closeModal('createProductSyncModal')" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
        </div>
        <form id="createProductSyncForm">
<input type="hidden" name="pre_order_item_id" id="cps_item_id">
            @if(isset($isPreOrder) && $isPreOrder)
                <input type="hidden" name="is_pre_order" value="1">
            @endif
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
                @if(($canUseSellingSecretCode ?? false) && (bool) \App\Models\Setting::get('barcode_enable_selling_secret_code', false))
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
                
                <!-- Mark as Purchase -->
                <div class="md:col-span-2 mt-2 p-4 border rounded-lg bg-blue-50">
                    <label class="flex items-center space-x-2 font-semibold text-blue-900 cursor-pointer">
                        <input type="checkbox" id="mark_as_purchase" class="w-5 h-5 rounded text-blue-600">
                        <span>Mark this as a Purchase</span>
                    </label>
                    
                    <div id="purchase_details_container" class="hidden mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Supplier <span class="text-red-500">*</span></label>
                            <div class="flex gap-2">
                                <select id="purchase_supplier_id" name="purchase_supplier_id" class="w-full border rounded px-3 py-2">
                                    <option value="">Please Select</option>
                                    @foreach(\App\Models\Supplier::where('is_active', true)->get() as $sup)
                                        <option value="{{ $sup->id }}">{{ $sup->name }}</option>
                                    @endforeach
                                </select>
                                <button type="button" onclick="openSupplierModal()" class="px-3 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 whitespace-nowrap">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                                 <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Payment Method</label>
                            <select id="purchase_payment_method" name="purchase_payment_method" class="w-full border rounded px-3 py-2" onchange="togglePurchaseChequeFields()">
                                <option value="cash">Cash</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="card">Card</option>
                                <option value="mobile_payment">Mobile Payment</option>
                                <option value="cheque">Cheque</option>
                                <option value="due_payment">Due Payment</option>
                                <option value="customer_cheque">Party Cheque (From Customer)</option>
                                <option value="own_cheque">Own Cheque (To Supplier)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Amount Paid (Due: <span id="purchase_due_amount">0.00</span>)</label>
                            <input type="number" id="purchase_amount_paid" name="purchase_amount_paid" class="w-full border rounded px-3 py-2" step="0.01" min="0" value="0.00">
                        </div>
                        <div id="purchase_cheque_details" class="md:col-span-2 hidden bg-gray-50 p-4 rounded-lg mt-2 space-y-4">
                            <div id="purchase_party_cheque_fields" class="hidden">
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Select Customer Cheque <span class="text-red-500">*</span></label>
                                <select id="purchase_cheque_id" name="purchase_cheque_id" class="w-full border rounded px-3 py-2" onchange="partyChequeSelected(this)">
                                    <option value="">Select Party Cheque...</option>
                                    @foreach($pendingCustomerCheques ?? [] as $c)
                                        <option value="{{ $c->id }}" data-amount="{{ $c->amount }}">{{ $c->bank_name }} - {{ $c->cheque_number }} - Rs {{ number_format($c->amount, 2) }} ({{ $c->customer ? $c->customer->name : 'Unknown' }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div id="purchase_own_cheque_fields" class="hidden grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-1">Bank Name <span class="text-red-500">*</span></label>
                                    <input type="text" id="purchase_bank_name" name="purchase_bank_name" class="w-full border rounded px-3 py-2" placeholder="e.g. BOC">
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-1">Cheque Date <span class="text-red-500">*</span></label>
                                    <input type="date" id="purchase_cheque_date" name="purchase_cheque_date" class="w-full border rounded px-3 py-2">
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-1">Cheque Number <span class="text-red-500">*</span></label>
                                    <input type="text" id="purchase_cheque_number" name="purchase_cheque_number" class="w-full border rounded px-3 py-2" placeholder="Cheque No">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mt-6 flex items-center gap-2">
                <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                    <i class="fas fa-plus mr-2"></i>Create Product
                </button>
                <button type="button" onclick="closeModal('createProductSyncModal')" class="px-6 py-2 bg-gray-200 rounded hover:bg-gray-300">Cancel</button>
            </div>
        </form>
    </div>
</div>



<!-- Supplier Modal -->
<div id="supplierModal" style="z-index: 1000;" class="fixed inset-0 hidden items-center justify-center">
    <div class="absolute inset-0 bg-black opacity-50" onclick="closeSupplierModal()"></div>
    <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl relative z-[1001] p-6 max-h-screen overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold">Add New Supplier</h3>
            <button onclick="closeSupplierModal()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
        </div>
        <form id="quickSupplierForm">
            <input type="hidden" name="is_active" value="1">
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
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Supplier TIN</label>
                    <input type="text" name="tin" inputmode="numeric" pattern="[0-9]{9,12}" maxlength="12" class="w-full border rounded px-3 py-2" />
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

<script>
// Supplier Modal Logic
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
    const btn = e.target.querySelector('button[type="submit"]');
    const originalText = btn.innerHTML;
    
    try {
        btn.disabled = true;
        btn.innerHTML = 'Creating...';
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
        const sel = document.getElementById('purchase_supplier_id');
        const opt = document.createElement('option');
        opt.value = s.id;
        opt.textContent = s.name;
        sel.appendChild(opt);
        sel.value = s.id;
        
        closeSupplierModal();
        e.target.reset();
    } catch (err) {
        console.error(err);
        alert('Error creating supplier');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
});

document.getElementById('mark_as_purchase')?.addEventListener('change', function() {
    const container = document.getElementById('purchase_details_container');
    if (this.checked) {
        container.classList.remove('hidden');
    } else {
        container.classList.add('hidden');
    }
});


function togglePurchaseChequeFields() {
    const method = document.getElementById('purchase_payment_method').value;
    const container = document.getElementById('purchase_cheque_details');
    const partyFields = document.getElementById('purchase_party_cheque_fields');
    const ownFields = document.getElementById('purchase_own_cheque_fields');
    
    if (method === 'customer_cheque' || method === 'own_cheque') {
        container.classList.remove('hidden');
        if (method === 'customer_cheque') {
            partyFields.classList.remove('hidden');
            ownFields.classList.add('hidden');
        } else {
            partyFields.classList.add('hidden');
            ownFields.classList.remove('hidden');
        }
    } else {
        container.classList.add('hidden');
        partyFields.classList.add('hidden');
        ownFields.classList.add('hidden');
    }
}

function partyChequeSelected(select) {
    const selectedOption = select.options[select.selectedIndex];
    if (selectedOption && selectedOption.dataset.amount) {
        document.getElementById('purchase_amount_paid').value = selectedOption.dataset.amount;
    }
</script>

<script>
const CAN_USE_SELLING_SECRET_CODE = @json((bool)\App\Models\Setting::get('barcode_enable_selling_secret_code', false));
const QUICK_COST_CODE_MAP = @json((array)\App\Models\Setting::get('barcode_cost_code_map'));
const QUICK_SELLING_CODE_MAP = CAN_USE_SELLING_SECRET_CODE ? @json((array)\App\Models\Setting::get('barcode_selling_code_map')) : {};

if (!Object.keys(QUICK_COST_CODE_MAP || {}).length) {
    Object.assign(QUICK_COST_CODE_MAP, {
        '0': 'E', '1': 'M', '2': 'O', '3': 'D', '4': 'T',
        '5': 'P', '6': 'C', '7': 'S', '8': 'K', '9': 'L'
    });
}
if (CAN_USE_SELLING_SECRET_CODE && !Object.keys(QUICK_SELLING_CODE_MAP || {}).length) {
    Object.assign(QUICK_SELLING_CODE_MAP, QUICK_COST_CODE_MAP);
}

function encodeNumberToSecret(numStr, map) {
    let str = String(numStr).trim();
    if (!str) return '';
    if (str.includes('.')) {
        str = parseFloat(str).toFixed(2);
    }
    let encoded = '';
    for (let char of str) {
        if (char === '.') {
            encoded += '.';
        } else if (map[char]) {
            encoded += map[char];
        } else {
            encoded += char;
        }
    }
    return encoded;
}

function attachQuickProductListeners() {
    const form = document.getElementById('createProductSyncForm');
    if (!form) return;

    const costInput = form.querySelector('input[name="cost_price"]');
    const sellingInput = form.querySelector('input[name="selling_price"]');
    const percentInput = document.getElementById('quick_profit_margin_percent');
    const fixedInput = document.getElementById('quick_profit_margin_fixed');
    const secretCodeInput = document.getElementById('quick_secret_cost_code');
    const sellingSecretCodeInput = document.getElementById('quick_secret_selling_code');
    const qtyInput = form.querySelector('input[name="stock_quantity"]');
    const dueDisplay = document.getElementById('purchase_due_amount');

    if (!costInput || !sellingInput || !percentInput || !fixedInput || !secretCodeInput) return;

    const updateDueAmount = () => {
        if (!dueDisplay) return;
        const c = parseFloat(costInput.value || 0);
        const q = parseFloat(qtyInput?.value || 0);
        dueDisplay.textContent = (c * q).toFixed(2);
    };

    const onCostInput = () => {
        const cost = parseFloat(costInput.value || 0);
        let selling = 0;
        
        if (percentInput.value) {
            selling = cost * (1 + parseFloat(percentInput.value) / 100);
        } else if (fixedInput.value) {
            selling = cost + parseFloat(fixedInput.value);
        }
        
        if (selling > 0) {
            sellingInput.value = selling.toFixed(2);
        }
        
        secretCodeInput.value = encodeNumberToSecret(costInput.value, QUICK_COST_CODE_MAP);
        if (sellingSecretCodeInput && sellingInput.value) {
            sellingSecretCodeInput.value = encodeNumberToSecret(sellingInput.value, QUICK_SELLING_CODE_MAP);
        }
        updateDueAmount();
    };

    const onSellingInput = () => {
        const cost = parseFloat(costInput.value || 0);
        const selling = parseFloat(sellingInput.value || 0);
        
        if (cost > 0 && selling > 0) {
            percentInput.value = (((selling - cost) / cost) * 100).toFixed(2);
            fixedInput.value = (selling - cost).toFixed(2);
        }
        
        if (sellingSecretCodeInput) {
            sellingSecretCodeInput.value = encodeNumberToSecret(sellingInput.value, QUICK_SELLING_CODE_MAP);
        }
    };

    const onMarginInput = (isPercent) => {
        return () => {
            const cost = parseFloat(costInput.value || 0);
            if (isPercent) {
                const percent = parseFloat(percentInput.value || 0);
                sellingInput.value = (cost * (1 + percent / 100)).toFixed(2);
                fixedInput.value = '';
            } else {
                const fixed = parseFloat(fixedInput.value || 0);
                sellingInput.value = (cost + fixed).toFixed(2);
                percentInput.value = '';
            }
            if (sellingSecretCodeInput) {
                sellingSecretCodeInput.value = encodeNumberToSecret(sellingInput.value, QUICK_SELLING_CODE_MAP);
            }
        };
    };

    costInput.addEventListener('input', onCostInput);
    sellingInput.addEventListener('input', onSellingInput);
    percentInput.addEventListener('input', onMarginInput(true));
    fixedInput.addEventListener('input', onMarginInput(false));
    if(qtyInput) qtyInput.addEventListener('input', updateDueAmount);

    secretCodeInput.value = encodeNumberToSecret(costInput.value || 0, QUICK_COST_CODE_MAP);
}



document.getElementById('mark_as_purchase')?.addEventListener('change', function() {
    const container = document.getElementById('purchase_details_container');
    if (this.checked) {
        container.classList.remove('hidden');
    } else {
        container.classList.add('hidden');
    }
});


function togglePurchaseChequeFields() {
    const method = document.getElementById('purchase_payment_method').value;
    const container = document.getElementById('purchase_cheque_details');
    const partyFields = document.getElementById('purchase_party_cheque_fields');
    const ownFields = document.getElementById('purchase_own_cheque_fields');
    
    if (method === 'customer_cheque' || method === 'own_cheque') {
        container.classList.remove('hidden');
        if (method === 'customer_cheque') {
            partyFields.classList.remove('hidden');
            ownFields.classList.add('hidden');
        } else {
            partyFields.classList.add('hidden');
            ownFields.classList.remove('hidden');
        }
    } else {
        container.classList.add('hidden');
        partyFields.classList.add('hidden');
        ownFields.classList.add('hidden');
    }
}

function partyChequeSelected(select) {
    const selectedOption = select.options[select.selectedIndex];
    if (selectedOption && selectedOption.dataset.amount) {
        document.getElementById('purchase_amount_paid').value = selectedOption.dataset.amount;
    }
}
</script>
