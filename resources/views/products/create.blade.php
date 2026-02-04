@extends('layouts.app')

@section('title', 'Add New Product')
@section('page-title', 'Add New Product')

@section('content')
<div class="max-w-4xl">
    
    <!-- Breadcrumb -->
    <div class="mb-6">
        <nav class="flex text-sm text-gray-600">
            <a href="{{ route('dashboard') }}" class="hover:text-blue-600">
                <i class="fas fa-home mr-1"></i> Dashboard
            </a>
            <span class="mx-2">/</span>
            <a href="{{ route('products.index') }}" class="hover:text-blue-600">Products</a>
            <span class="mx-2">/</span>
            <span class="text-gray-800">Add New</span>
        </nav>
    </div>

    <!-- Form Card -->
    <div class="bg-white rounded-xl shadow-md p-6 md:p-8">
        
        <form action="{{ route('products.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                
                <!-- Product Name -->
                <div class="md:col-span-2">
                    <label for="name" class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-tag text-blue-600 mr-2"></i>Product Name *
                    </label>
                    <input 
                        type="text" 
                        id="name" 
                        name="name" 
                        value="{{ old('name') }}"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('name') border-red-500 @enderror"
                        placeholder="e.g., Brake Pad Set"
                        required
                    >
                    @error('name')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- SKU / Barcode (same field) -->
                <div class="md:col-span-2">
                    <label for="sku" class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-barcode text-blue-600 mr-2"></i>SKU / Barcode
                    </label>
                    <input 
                        type="text" 
                        id="sku" 
                        name="sku" 
                        value="{{ old('sku') }}"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('sku') border-red-500 @enderror"
                        placeholder="Auto-generated if blank (e.g., PRD241109-001)"
                    >
                    <p class="text-xs text-gray-500 mt-1">
                        <i class="fas fa-info-circle mr-1"></i>This will be used as both SKU and Barcode
                    </p>
                    @error('sku')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Category -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-folder text-blue-600 mr-2"></i>Categories
                    </label>
                    <div id="categories-wrapper" class="space-y-2">
                        @php
                            $oldCategories = old('categories', []);
                            if(empty($oldCategories)) $oldCategories = [null];
                        @endphp
                        @foreach($oldCategories as $oldCat)
                        <div class="flex gap-2 category-row">
                            <select name="categories[]" class="category-select flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="">Select Category</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" {{ $oldCat == $category->id ? 'selected' : '' }}>
                                        {{ $category->name }}
                                    </option>
                                @endforeach
                            </select>
                            <button type="button" onclick="removeRow(this)" class="px-3 py-2 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 transition remove-btn {{ count($oldCategories) > 1 ? '' : 'hidden' }}">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                        @endforeach
                    </div>
                    <div class="flex flex-wrap items-center gap-3 mt-2">
                        <button type="button" onclick="addCategoryRow()" class="text-xs text-blue-600 hover:text-blue-800 font-medium">
                            <i class="fas fa-plus-circle mr-1"></i>Add Another
                        </button>
                        <button type="button" onclick="openCategoryModal()" class="text-xs text-green-600 hover:text-green-800 font-medium">
                            <i class="fas fa-plus mr-1"></i>New Category
                        </button>
                    </div>
                    @error('categories') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- Brand -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-copyright text-blue-600 mr-2"></i>Brands
                    </label>
                    <div id="brands-wrapper" class="space-y-2">
                        @php
                            $oldBrands = old('brands', []);
                            if(empty($oldBrands)) $oldBrands = [null];
                        @endphp
                        @foreach($oldBrands as $oldBrand)
                        <div class="flex gap-2 brand-row">
                            <select name="brands[]" class="brand-select flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="">Select Brand</option>
                                @foreach($brands as $brand)
                                    <option value="{{ $brand->id }}" {{ $oldBrand == $brand->id ? 'selected' : '' }}>
                                        {{ $brand->name }}
                                    </option>
                                @endforeach
                            </select>
                            <button type="button" onclick="removeRow(this)" class="px-3 py-2 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 transition remove-btn {{ count($oldBrands) > 1 ? '' : 'hidden' }}">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                        @endforeach
                    </div>
                    <div class="flex flex-wrap items-center gap-3 mt-2">
                        <button type="button" onclick="addBrandRow()" class="text-xs text-blue-600 hover:text-blue-800 font-medium">
                            <i class="fas fa-plus-circle mr-1"></i>Add Another
                        </button>
                        <button type="button" onclick="openBrandModal()" class="text-xs text-green-600 hover:text-green-800 font-medium">
                            <i class="fas fa-plus mr-1"></i>New Brand
                        </button>
                    </div>
                    @error('brands') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- Unit -->
                <div>
                    <label for="unit_id" class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-balance-scale text-blue-600 mr-2"></i>Unit *
                    </label>
                    <div class="flex gap-2">
                        <select 
                            id="unit_id" 
                            name="unit_id" 
                            class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('unit_id') border-red-500 @enderror"
                            required
                        >
                            <option value="">Select Unit</option>
                            @foreach($units as $unit)
                                <option value="{{ $unit->id }}" {{ old('unit_id') == $unit->id ? 'selected' : '' }}>
                                    {{ $unit->name }} ({{ $unit->short_name }})
                                </option>
                            @endforeach
                        </select>
                        <button type="button" onclick="openUnitModal()" class="px-4 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition" title="Add New Unit">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    @error('unit_id')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Visible Units (Price Display) -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-eye text-blue-600 mr-2"></i>Show Unit Prices For
                    </label>
                    <p class="text-xs text-gray-500 mb-2">Uncheck units you do not want to display in the product list. Leave all checked to show prices for every unit.</p>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                        @foreach($units as $u)
                            <label class="flex items-center space-x-2 px-2 py-1 border rounded">
                                <input type="checkbox" name="visible_units[]" value="{{ $u->id }}" class="text-blue-600 rounded" checked>
                                @php
                                    $m = rtrim(rtrim(number_format((float)$u->base_unit_multiplier, 3, '.', ''), '0'), '.');
                                @endphp
                                <span class="text-xs text-gray-700">{{ $u->short_name }} (x{{ $m }})</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <!-- Cost Price -->
                <div>
                    <label for="cost_price" class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-dollar-sign text-blue-600 mr-2"></i>Cost Price *
                    </label>
                    <input 
                        type="number" 
                        id="cost_price" 
                        name="cost_price" 
                        value="{{ old('cost_price', '0') }}"
                        step="0.01"
                        min="0"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('cost_price') border-red-500 @enderror"
                        placeholder="0.00"
                        required
                    >
                    @error('cost_price')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Profit Margin -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-chart-line text-blue-600 mr-2"></i>Profit Margin
                    </label>
                    <p class="text-xs text-gray-500 mb-3">Enter either percentage or fixed amount; both fields are editable and will auto-sync.</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <input 
                            type="number"
                            id="profit_margin_percent"
                            step="0.01"
                            min="0"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="Enter profit margin %"
                            oninput="onPercentageChange()"
                        >
                        <input 
                            type="number"
                            id="profit_margin_fixed"
                            step="0.01"
                            min="0"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="Enter fixed amount"
                            oninput="onFixedAmountChange()"
                        >
                    </div>
                </div>

                <!-- VAT -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-receipt text-green-600 mr-2"></i>VAT
                    </label>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <input type="text" disabled value="{{ ($vatEnabled ?? false) ? 'Enabled' : 'Disabled' }}" class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-50">
                            <p class="text-xs text-gray-500 mt-1">Configured in Settings</p>
                        </div>
                        <div>
                            <input type="text" disabled value="Rate: {{ number_format($vatRate ?? 0, 2) }}%" class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-50">
                            <p class="text-xs text-gray-500 mt-1">Used for price calculation</p>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="block text-xs font-semibold text-gray-600 mb-2">Selling Price VAT Type</label>
                        <select id="vat_type" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="exclusive" selected>Exclusive (VAT added on top)</option>
                            <option value="inclusive">Inclusive (Price includes VAT)</option>
                        </select>
                    </div>
                </div>

                <!-- Selling Price -->
                <div>
                    <label for="selling_price" class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-tags text-blue-600 mr-2"></i>Selling Price *
                    </label>
                    <input 
                        type="number" 
                        id="selling_price" 
                        name="selling_price" 
                        value="{{ old('selling_price', '0') }}"
                        step="0.01"
                        min="0"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('selling_price') border-red-500 @enderror"
                        placeholder="0.00"
                        required
                    >
                    @error('selling_price')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Stock Quantity -->
                <div>
                    <label for="stock_quantity" class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-boxes text-blue-600 mr-2"></i>Stock Quantity *
                    </label>
                    <input 
                        type="number" 
                        id="stock_quantity" 
                        name="stock_quantity" 
                        value="{{ old('stock_quantity', '0') }}"
                        min="0"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('stock_quantity') border-red-500 @enderror"
                        placeholder="0"
                        required
                    >
                    @error('stock_quantity')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Per-brand Pricing (creates one product per brand) -->
                <div id="per-brand-pricing-section" class="md:col-span-2 hidden">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-layer-group text-blue-600 mr-2"></i>Per Brand Prices
                    </label>
                    <p class="text-xs text-gray-500 mb-2">When you select one or more brands above, the system will create one product per brand with name: <span class="font-semibold">Product Name (Brand)</span>. If you leave any brand price blank, it will use the main Cost/Selling Price.</p>
                    <div id="per-brand-pricing-rows" class="space-y-2"></div>
                    @php
                        $perBrandCostError = $errors->first('brand_cost_price.*');
                        $perBrandSellError = $errors->first('brand_selling_price.*');
                    @endphp
                    @if($perBrandCostError)
                        <p class="text-red-500 text-xs mt-1">{{ $perBrandCostError }}</p>
                    @endif
                    @if($perBrandSellError)
                        <p class="text-red-500 text-xs mt-1">{{ $perBrandSellError }}</p>
                    @endif
                </div>

                <!-- Alert Quantity -->
                <div>
                    <label for="alert_quantity" class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-exclamation-triangle text-blue-600 mr-2"></i>Alert Quantity *
                    </label>
                    <input 
                        type="number" 
                        id="alert_quantity" 
                        name="alert_quantity" 
                        value="{{ old('alert_quantity', '10') }}"
                        min="0"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('alert_quantity') border-red-500 @enderror"
                        placeholder="10"
                        required
                    >
                    @error('alert_quantity')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Description -->
                <div class="md:col-span-2">
                    <label for="description" class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-align-left text-blue-600 mr-2"></i>Description
                    </label>
                    <textarea 
                        id="description" 
                        name="description" 
                        rows="4"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('description') border-red-500 @enderror"
                        placeholder="Product description..."
                    >{{ old('description') }}</textarea>
                    @error('description')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Product Image -->
                <div class="md:col-span-2">
                    <label for="image" class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-image text-blue-600 mr-2"></i>Product Image
                    </label>
                    <input 
                        type="file" 
                        id="image" 
                        name="image" 
                        accept="image/*"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('image') border-red-500 @enderror"
                    >
                    <p class="text-xs text-gray-500 mt-1">Max size: 2MB. Supported formats: JPG, PNG, GIF</p>
                    @error('image')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

            </div>

            <!-- Action Buttons -->
            <div class="flex items-center justify-end space-x-4 mt-8 pt-6 border-t border-gray-200">
                <a 
                    href="{{ route('products.index') }}" 
                    class="px-6 py-3 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition font-medium"
                >
                    <i class="fas fa-times mr-2"></i>Cancel
                </a>
                <button 
                    type="submit" 
                    class="px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg hover:from-blue-700 hover:to-blue-800 transition font-medium shadow-lg"
                >
                    <i class="fas fa-save mr-2"></i>Save Product
                </button>
            </div>

        </form>

    </div>
</div>

<!-- Category Modal -->
<div id="categoryModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-md w-full">
        <div class="bg-gradient-to-r from-blue-600 to-blue-700 p-4 text-white rounded-t-xl flex justify-between items-center">
            <h3 class="text-lg font-bold"><i class="fas fa-folder mr-2"></i>Add New Category</h3>
            <button onclick="closeCategoryModal()" class="text-white hover:text-gray-200">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <form id="categoryForm" class="p-6 space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Category Name *</label>
                <input type="text" id="category_name" name="name" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500" required>
                <p id="category_error" class="text-red-500 text-xs mt-1 hidden"></p>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                <textarea id="category_description" name="description" rows="3" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
            </div>
            <div class="flex gap-3 pt-4">
                <button type="submit" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold">
                    <i class="fas fa-save mr-2"></i>Save
                </button>
                <button type="button" onclick="closeCategoryModal()" class="flex-1 px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition font-semibold">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Brand Modal -->
<div id="brandModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-md w-full">
        <div class="bg-gradient-to-r from-blue-600 to-blue-700 p-4 text-white rounded-t-xl flex justify-between items-center">
            <h3 class="text-lg font-bold"><i class="fas fa-copyright mr-2"></i>Add New Brand</h3>
            <button onclick="closeBrandModal()" class="text-white hover:text-gray-200">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <form id="brandForm" class="p-6 space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Brand Name *</label>
                <input type="text" id="brand_name" name="name" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500" required>
                <p id="brand_error" class="text-red-500 text-xs mt-1 hidden"></p>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                <textarea id="brand_description" name="description" rows="3" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
            </div>
            <div class="flex gap-3 pt-4">
                <button type="submit" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold">
                    <i class="fas fa-save mr-2"></i>Save
                </button>
                <button type="button" onclick="closeBrandModal()" class="flex-1 px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition font-semibold">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Unit Modal -->
<div id="unitModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-md w-full">
        <div class="bg-gradient-to-r from-blue-600 to-blue-700 p-4 text-white rounded-t-xl flex justify-between items-center">
            <h3 class="text-lg font-bold"><i class="fas fa-balance-scale mr-2"></i>Add New Unit</h3>
            <button onclick="closeUnitModal()" class="text-white hover:text-gray-200">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <form id="unitForm" class="p-6 space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Unit Name *</label>
                <input type="text" id="unit_name" name="name" placeholder="e.g., Piece, Box, Set" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500" required>
                <p id="unit_name_error" class="text-red-500 text-xs mt-1 hidden"></p>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Short Name *</label>
                <input type="text" id="unit_short_name" name="short_name" placeholder="e.g., pc, box, set" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500" required>
                <p id="unit_short_error" class="text-red-500 text-xs mt-1 hidden"></p>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Base Unit Multiplier</label>
                <input type="number" id="unit_multiplier" name="base_unit_multiplier" value="1" step="0.01" min="0" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="flex gap-3 pt-4">
                <button type="submit" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold">
                    <i class="fas fa-save mr-2"></i>Save
                </button>
                <button type="button" onclick="closeUnitModal()" class="flex-1 px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition font-semibold">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Simple Toast Function
function showToast(type, message) {
    const toast = document.createElement('div');
    toast.className = `fixed top-4 right-4 z-50 px-4 py-3 rounded shadow-lg text-white transition-opacity duration-500 ${type === 'success' ? 'bg-green-600' : 'bg-red-600'}`;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 500);
    }, 3000);
}

// Dynamic Rows Functions
function addCategoryRow() {
    const wrapper = document.getElementById('categories-wrapper');
    const firstRow = wrapper.querySelector('.category-row');
    const newRow = firstRow.cloneNode(true);
    
    // Reset selection
    newRow.querySelector('select').value = "";
    
    // Show remove button
    newRow.querySelector('.remove-btn').classList.remove('hidden');
    
    wrapper.appendChild(newRow);
    updateRemoveButtons('categories-wrapper');
}

function addBrandRow() {
    const wrapper = document.getElementById('brands-wrapper');
    const firstRow = wrapper.querySelector('.brand-row');
    const newRow = firstRow.cloneNode(true);
    
    newRow.querySelector('select').value = "";
    newRow.querySelector('.remove-btn').classList.remove('hidden');
    
    wrapper.appendChild(newRow);
    updateRemoveButtons('brands-wrapper');
    updatePerBrandPricing();
}

function parseNumber(value) {
    if (value === undefined || value === null || value === '') {
        return NaN;
    }
    const parsed = parseFloat(value);
    return Number.isFinite(parsed) ? parsed : NaN;
}

function formatNumber(value) {
    return Number.isFinite(value) ? value.toFixed(2) : '';
}

function computeMarginFixed(cost, sell) {
    const costNum = parseNumber(cost);
    const sellNum = parseNumber(sell);
    if (Number.isFinite(costNum) && Number.isFinite(sellNum)) {
        return formatNumber(sellNum - costNum);
    }
    return '';
}

function computeMarginPercent(cost, sell) {
    const costNum = parseNumber(cost);
    const sellNum = parseNumber(sell);
    if (Number.isFinite(costNum) && costNum !== 0 && Number.isFinite(sellNum)) {
        const fixed = sellNum - costNum;
        return formatNumber((fixed / costNum) * 100);
    }
    return '';
}

function handleBrandPercentChange(costInput, percentInput, fixedInput, sellInput) {
    const costVal = parseNumber(costInput?.value ?? '');
    const percentVal = parseNumber(percentInput?.value ?? '');
    if (!Number.isFinite(costVal) || !Number.isFinite(percentVal)) {
        return;
    }

    const fixed = costVal * (percentVal / 100);
    const selling = costVal + fixed;
    fixedInput.value = formatNumber(fixed);
    sellInput.value = formatNumber(selling);
}

function handleBrandFixedChange(costInput, percentInput, fixedInput, sellInput) {
    const costVal = parseNumber(costInput?.value ?? '');
    const fixedVal = parseNumber(fixedInput?.value ?? '');
    if (!Number.isFinite(costVal) || !Number.isFinite(fixedVal)) {
        return;
    }

    const selling = costVal + fixedVal;
    sellInput.value = formatNumber(selling);
    if (costVal !== 0) {
        percentInput.value = formatNumber((fixedVal / costVal) * 100);
    } else {
        percentInput.value = '';
    }
}

function handleBrandSellingChange(costInput, percentInput, fixedInput, sellInput) {
    const costVal = parseNumber(costInput?.value ?? '');
    const sellingVal = parseNumber(sellInput?.value ?? '');
    if (!Number.isFinite(costVal) || !Number.isFinite(sellingVal)) {
        return;
    }

    const fixed = sellingVal - costVal;
    fixedInput.value = formatNumber(fixed);
    if (costVal !== 0) {
        percentInput.value = formatNumber((fixed / costVal) * 100);
    } else {
        percentInput.value = '';
    }
}

function handleBrandCostChange(costInput, percentInput, fixedInput, sellInput) {
    if (!costInput) {
        return;
    }
    if (percentInput && percentInput.value !== '') {
        handleBrandPercentChange(costInput, percentInput, fixedInput, sellInput);
        return;
    }
    if (fixedInput && fixedInput.value !== '') {
        handleBrandFixedChange(costInput, percentInput, fixedInput, sellInput);
        return;
    }
    if (sellInput) {
        handleBrandSellingChange(costInput, percentInput, fixedInput, sellInput);
    }
}

function removeRow(btn) {
    const row = btn.closest('.flex'); // .category-row or .brand-row
    const wrapper = row.parentElement;
    if (wrapper.children.length > 1) {
        row.remove();
        updateRemoveButtons(wrapper.id);
        if (wrapper.id === 'brands-wrapper') {
            updatePerBrandPricing();
        }
    }
}

function getSelectedBrandIds() {
    const selects = document.querySelectorAll('#brands-wrapper .brand-select');
    const ids = [];
    const seen = new Set();
    selects.forEach(select => {
        const val = (select.value || '').trim();
        if (val && !seen.has(val)) {
            ids.push(val);
            seen.add(val);
        }
    });
    return ids;
}

function getBrandNameById(brandId) {
    const option = document.querySelector(`#brands-wrapper .brand-select option[value="${CSS.escape(brandId)}"]`);
    return option ? option.textContent.trim() : `Brand #${brandId}`;
}

function updatePerBrandPricing() {
    const section = document.getElementById('per-brand-pricing-section');
    const rowsWrapper = document.getElementById('per-brand-pricing-rows');
    if (!section || !rowsWrapper) return;

    const brandIds = getSelectedBrandIds();
    if (brandIds.length === 0) {
        section.classList.add('hidden');
        rowsWrapper.innerHTML = '';
        return;
    }

    const currentValues = new Map();
    rowsWrapper.querySelectorAll('[data-brand-id]').forEach(row => {
        const bid = row.getAttribute('data-brand-id');
        const costInput = row.querySelector('input[data-role="brand-cost"]');
        const sellInput = row.querySelector('input[data-role="brand-sell"]');
        const stockInput = row.querySelector('input[data-role="brand-stock"]');
        const percentInput = row.querySelector('input[data-role="brand-margin-percent"]');
        const fixedInput = row.querySelector('input[data-role="brand-margin-fixed"]');
        currentValues.set(bid, {
            costValue: costInput ? costInput.value : '',
            sellValue: sellInput ? sellInput.value : '',
            stockValue: stockInput ? stockInput.value : '',
            percentValue: percentInput ? percentInput.value : '',
            fixedValue: fixedInput ? fixedInput.value : '',
            costTouched: costInput?.dataset.touchedCost === 'true',
            sellTouched: sellInput?.dataset.touchedSell === 'true',
            stockTouched: stockInput?.dataset.touchedStock === 'true',
            percentTouched: percentInput?.dataset.touchedPercent === 'true',
            fixedTouched: fixedInput?.dataset.touchedFixed === 'true',
        });
    });

    const baseCost = (document.getElementById('cost_price')?.value ?? '').trim();
    const baseSell = (document.getElementById('selling_price')?.value ?? '').trim();
    const baseStock = (document.getElementById('stock_quantity')?.value ?? '').trim();

    const oldBrandCost = @json(old('brand_cost_price', []));
    const oldBrandSell = @json(old('brand_selling_price', []));
    const oldBrandStock = @json(old('brand_stock_quantity', []));
    const oldBrandPercent = @json(old('brand_profit_margin_percent', []));
    const oldBrandFixed = @json(old('brand_profit_margin_fixed', []));

    rowsWrapper.innerHTML = '';
    section.classList.remove('hidden');

    brandIds.forEach(brandId => {
        const brandName = getBrandNameById(brandId);
        const existing = currentValues.get(brandId);
        const costTouched = existing?.costTouched;
        const sellTouched = existing?.sellTouched;
        const stockTouched = existing?.stockTouched;
        const percentTouched = existing?.percentTouched;
        const fixedTouched = existing?.fixedTouched;

        const defaultCost = oldBrandCost?.[brandId] ?? baseCost;
        const defaultSell = oldBrandSell?.[brandId] ?? baseSell;
        const defaultStock = oldBrandStock?.[brandId] ?? baseStock;
        const defaultPercent = oldBrandPercent?.[brandId] ?? computeMarginPercent(defaultCost, defaultSell);
        const defaultFixed = oldBrandFixed?.[brandId] ?? computeMarginFixed(defaultCost, defaultSell);

        const costVal = costTouched ? (existing?.costValue ?? '') : (defaultCost ?? '');
        const sellVal = sellTouched ? (existing?.sellValue ?? '') : (defaultSell ?? '');
        const stockVal = stockTouched ? (existing?.stockValue ?? '') : (defaultStock ?? '');
        const percentVal = percentTouched ? (existing?.percentValue ?? '') : (defaultPercent ?? '');
        const fixedVal = fixedTouched ? (existing?.fixedValue ?? '') : (defaultFixed ?? '');

        const row = document.createElement('div');
        row.className = 'grid grid-cols-1 md:grid-cols-6 gap-3 p-3 border border-gray-200 rounded-lg bg-gray-50';
        row.setAttribute('data-brand-id', brandId);
        row.innerHTML = `
            <div class="flex items-center text-sm font-semibold text-gray-700">${brandName}</div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Cost Price</label>
                <input
                    type="number"
                    step="0.01"
                    min="0"
                    data-role="brand-cost"
                    name="brand_cost_price[${brandId}]"
                    value="${String(costVal).replace(/"/g, '&quot;')}"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="(uses main cost if blank)"
                />
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Profit Margin %</label>
                <input
                    type="number"
                    step="0.01"
                    min="-999999"
                    data-role="brand-margin-percent"
                    name="brand_profit_margin_percent[${brandId}]"
                    value="${String(percentVal).replace(/"/g, '&quot;')}"
                    class="w-full px-3 py-2 border border-blue-300 rounded-lg bg-blue-50 focus:ring-2 focus:ring-blue-500 focus:border-transparent text-blue-700"
                    placeholder="e.g., 10"
                />
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Profit Margin Fixed</label>
                <input
                    type="number"
                    step="0.01"
                    min="-999999"
                    data-role="brand-margin-fixed"
                    name="brand_profit_margin_fixed[${brandId}]"
                    value="${String(fixedVal).replace(/"/g, '&quot;')}"
                    class="w-full px-3 py-2 border border-blue-300 rounded-lg bg-blue-50 focus:ring-2 focus:ring-blue-500 focus:border-transparent text-blue-700"
                    placeholder="e.g., 50"
                />
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Stock Quantity</label>
                <input
                    type="number"
                    step="1"
                    min="0"
                    data-role="brand-stock"
                    name="brand_stock_quantity[${brandId}]"
                    value="${String(stockVal).replace(/"/g, '&quot;')}"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="(uses main stock if blank)"
                />
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Selling Price</label>
                <input
                    type="number"
                    step="0.01"
                    min="0"
                    data-role="brand-sell"
                    name="brand_selling_price[${brandId}]"
                    value="${String(sellVal).replace(/"/g, '&quot;')}"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="(uses main selling if blank)"
                />
            </div>
        `;

        rowsWrapper.appendChild(row);

        const costInput = row.querySelector('input[data-role="brand-cost"]');
        const sellInput = row.querySelector('input[data-role="brand-sell"]');
        const stockInput = row.querySelector('input[data-role="brand-stock"]');
        const percentInput = row.querySelector('input[data-role="brand-margin-percent"]');
        const fixedInput = row.querySelector('input[data-role="brand-margin-fixed"]');
        if (costInput) {
            costInput.dataset.touchedCost = costTouched ? 'true' : 'false';
            costInput.addEventListener('input', () => {
                costInput.dataset.touchedCost = 'true';
                handleBrandCostChange(costInput, percentInput, fixedInput, sellInput);
            });
        }
        if (percentInput) {
            percentInput.dataset.touchedPercent = percentTouched ? 'true' : 'false';
            percentInput.addEventListener('input', () => {
                percentInput.dataset.touchedPercent = 'true';
                handleBrandPercentChange(costInput, percentInput, fixedInput, sellInput);
            });
        }
        if (fixedInput) {
            fixedInput.dataset.touchedFixed = fixedTouched ? 'true' : 'false';
            fixedInput.addEventListener('input', () => {
                fixedInput.dataset.touchedFixed = 'true';
                handleBrandFixedChange(costInput, percentInput, fixedInput, sellInput);
            });
        }
        if (sellInput) {
            sellInput.dataset.touchedSell = sellTouched ? 'true' : 'false';
            sellInput.addEventListener('input', () => {
                sellInput.dataset.touchedSell = 'true';
                handleBrandSellingChange(costInput, percentInput, fixedInput, sellInput);
            });
        }
    });
}

function updateRemoveButtons(wrapperId) {
    const wrapper = document.getElementById(wrapperId);
    const rows = wrapper.children;
    const showRemove = rows.length > 1;
    
    Array.from(rows).forEach(row => {
        const btn = row.querySelector('.remove-btn');
        if (showRemove) {
            btn.classList.remove('hidden');
        } else {
            btn.classList.add('hidden');
        }
    });
}

// Category Modal Functions
function openCategoryModal() {
    document.getElementById('categoryModal').classList.remove('hidden');
    document.getElementById('category_name').value = '';
    document.getElementById('category_description').value = '';
    document.getElementById('category_error').classList.add('hidden');
}

function closeCategoryModal() {
    document.getElementById('categoryModal').classList.add('hidden');
}

document.getElementById('categoryForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    try {
        const response = await fetch('{{ route("categories.store") }}', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || formData.get('_token')
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Add to all dropdowns
            const selects = document.querySelectorAll('.category-select');
            selects.forEach(select => {
                const option = new Option(data.category.name, data.category.id, false, false);
                select.add(option);
            });
            
            // Select in the last dropdown (or create new row if last is occupied?)
            // For simplicity, let's just select it in the last dropdown if it's empty, or add a new row
            const wrapper = document.getElementById('categories-wrapper');
            const lastRow = wrapper.lastElementChild;
            const lastSelect = lastRow.querySelector('select');
            
            if (lastSelect.value) {
                addCategoryRow();
                const newLastSelect = wrapper.lastElementChild.querySelector('select');
                newLastSelect.value = data.category.id;
            } else {
                lastSelect.value = data.category.id;
            }
            
            closeCategoryModal();
            if(typeof showToast === 'function') {
                showToast('success', 'Category created successfully!');
            }
        } else {
            document.getElementById('category_error').textContent = data.message || 'Error creating category';
            document.getElementById('category_error').classList.remove('hidden');
        }
    } catch (error) {
        document.getElementById('category_error').textContent = 'Error creating category';
        document.getElementById('category_error').classList.remove('hidden');
    }
});

// Brand Modal Functions
function openBrandModal() {
    document.getElementById('brandModal').classList.remove('hidden');
    document.getElementById('brand_name').value = '';
    document.getElementById('brand_description').value = '';
    document.getElementById('brand_error').classList.add('hidden');
}

function closeBrandModal() {
    document.getElementById('brandModal').classList.add('hidden');
}

document.getElementById('brandForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    try {
        const response = await fetch('{{ route("brands.store") }}', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || formData.get('_token')
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            const selects = document.querySelectorAll('.brand-select');
            selects.forEach(select => {
                const option = new Option(data.brand.name, data.brand.id, false, false);
                select.add(option);
            });

            const wrapper = document.getElementById('brands-wrapper');
            const lastRow = wrapper.lastElementChild;
            const lastSelect = lastRow.querySelector('select');
            
            if (lastSelect.value) {
                addBrandRow();
                const newLastSelect = wrapper.lastElementChild.querySelector('select');
                newLastSelect.value = data.brand.id;
            } else {
                lastSelect.value = data.brand.id;
            }

            updatePerBrandPricing();
            
            closeBrandModal();
            if(typeof showToast === 'function') {
                showToast('success', 'Brand created successfully!');
            }
        } else {
            document.getElementById('brand_error').textContent = data.message || 'Error creating brand';
            document.getElementById('brand_error').classList.remove('hidden');
        }
    } catch (error) {
        document.getElementById('brand_error').textContent = 'Error creating brand';
        document.getElementById('brand_error').classList.remove('hidden');
    }
});

// Unit Modal Functions
function openUnitModal() {
    document.getElementById('unitModal').classList.remove('hidden');
    document.getElementById('unit_name').value = '';
    document.getElementById('unit_short_name').value = '';
    document.getElementById('unit_multiplier').value = '1';
    document.getElementById('unit_name_error').classList.add('hidden');
    document.getElementById('unit_short_error').classList.add('hidden');
}

function closeUnitModal() {
    document.getElementById('unitModal').classList.add('hidden');
}

document.getElementById('unitForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    try {
        const response = await fetch('{{ route("units.store") }}', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || formData.get('_token')
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            const select = document.getElementById('unit_id');
            const option = new Option(data.unit.name + ' (' + data.unit.short_name + ')', data.unit.id, true, true);
            select.add(option);
            closeUnitModal();
            alert('Unit created successfully!');
        } else {
            document.getElementById('unit_name_error').textContent = data.message || 'Error creating unit';
            document.getElementById('unit_name_error').classList.remove('hidden');
        }
    } catch (error) {
        document.getElementById('unit_name_error').textContent = 'Error creating unit';
        document.getElementById('unit_name_error').classList.remove('hidden');
    }
});

function onPercentageChange() {
    const costPrice = parseFloat(document.getElementById('cost_price').value) || 0;
    const percentage = parseFloat(document.getElementById('profit_margin_percent').value) || 0;
    
    if (costPrice > 0 && percentage >= 0) {
        // Calculate fixed amount from percentage
        const fixedAmount = costPrice * (percentage / 100);
        document.getElementById('profit_margin_fixed').value = fixedAmount.toFixed(2);
        
        // Calculate selling price with VAT option
        const baseSelling = costPrice + fixedAmount;
        const vatRate = parseFloat({{ json_encode($vatRate ?? 0) }});
        const vatEnabled = Boolean({{ json_encode($vatEnabled ?? false) }});
        const vatType = document.getElementById('vat_type') ? document.getElementById('vat_type').value : 'exclusive';
        let finalSelling = baseSelling;
        if (vatEnabled) {
            if (vatType === 'exclusive') {
                finalSelling = baseSelling * (1 + (vatRate / 100));
            } else {
                // Inclusive: base price is treated as including VAT already
                finalSelling = baseSelling;
            }
        }
        document.getElementById('selling_price').value = finalSelling.toFixed(2);
        updatePerBrandPricing();
    } else {
        document.getElementById('profit_margin_fixed').value = '';
        document.getElementById('selling_price').value = costPrice.toFixed(2);
        updatePerBrandPricing();
    }
}

function onFixedAmountChange() {
    const costPrice = parseFloat(document.getElementById('cost_price').value) || 0;
    const fixedAmount = parseFloat(document.getElementById('profit_margin_fixed').value) || 0;
    
    if (costPrice > 0 && fixedAmount >= 0) {
        // Calculate percentage from fixed amount
        const percentage = (fixedAmount / costPrice) * 100;
        document.getElementById('profit_margin_percent').value = percentage.toFixed(2);
        
        // Calculate selling price with VAT option
        const baseSelling = costPrice + fixedAmount;
        const vatRate = parseFloat({{ json_encode($vatRate ?? 0) }});
        const vatEnabled = Boolean({{ json_encode($vatEnabled ?? false) }});
        const vatType = document.getElementById('vat_type') ? document.getElementById('vat_type').value : 'exclusive';
        let finalSelling = baseSelling;
        if (vatEnabled) {
            if (vatType === 'exclusive') {
                finalSelling = baseSelling * (1 + (vatRate / 100));
            } else {
                finalSelling = baseSelling;
            }
        }
        document.getElementById('selling_price').value = finalSelling.toFixed(2);
        updatePerBrandPricing();
    } else {
        document.getElementById('profit_margin_percent').value = '';
        document.getElementById('selling_price').value = costPrice.toFixed(2);
        updatePerBrandPricing();
    }
}

function onCostPriceChange() {
    // When cost price changes, prefer recalculating from whichever field has a value
    const fixedAmount = document.getElementById('profit_margin_fixed').value;
    const percentage = document.getElementById('profit_margin_percent').value;

    if (fixedAmount !== '') {
        onFixedAmountChange();
        return;
    }
    if (percentage !== '') {
        onPercentageChange();
        return;
    }
    // No margin provided; keep selling price equal to cost price
    const costPrice = parseFloat(document.getElementById('cost_price').value) || 0;
    document.getElementById('selling_price').value = costPrice.toFixed(2);
}

document.getElementById('cost_price').addEventListener('input', onCostPriceChange);
document.getElementById('cost_price').addEventListener('input', updatePerBrandPricing);
document.getElementById('selling_price').addEventListener('input', updatePerBrandPricing);
document.getElementById('stock_quantity').addEventListener('input', updatePerBrandPricing);

document.getElementById('brands-wrapper').addEventListener('change', (e) => {
    if (e.target && e.target.classList && e.target.classList.contains('brand-select')) {
        updatePerBrandPricing();
    }
});
const vatTypeEl = document.getElementById('vat_type');
if (vatTypeEl) {
    vatTypeEl.addEventListener('change', () => {
        // If selling price entered, recompute margin from it; else recompute from margin
        const spVal = document.getElementById('selling_price').value;
        if (spVal !== '') {
            onSellingPriceChange();
        } else {
            onCostPriceChange();
        }
    });
}

function onSellingPriceChange() {
    const costPrice = parseFloat(document.getElementById('cost_price').value) || 0;
    const sellingFinal = parseFloat(document.getElementById('selling_price').value) || 0;
    const vatRate = parseFloat({{ json_encode($vatRate ?? 0) }});
    const vatEnabled = Boolean({{ json_encode($vatEnabled ?? false) }});
    const vatType = document.getElementById('vat_type') ? document.getElementById('vat_type').value : 'exclusive';

    let baseSelling = sellingFinal;
    if (vatEnabled && vatType === 'exclusive') {
        baseSelling = sellingFinal / (1 + (vatRate / 100));
    }

    const fixedAmount = baseSelling - costPrice;
    const percentage = costPrice > 0 ? (fixedAmount / costPrice) * 100 : 0;

    if (!isNaN(fixedAmount)) {
        document.getElementById('profit_margin_fixed').value = fixedAmount.toFixed(2);
    }
    if (!isNaN(percentage)) {
        document.getElementById('profit_margin_percent').value = percentage.toFixed(2);
    }
}

document.getElementById('selling_price').addEventListener('input', onSellingPriceChange);

// Initial render
updatePerBrandPricing();
</script>
@endsection
