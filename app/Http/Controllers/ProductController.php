<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Setting;
use App\Models\Unit;
use App\Exports\ProductImportTemplateExport;
use App\Imports\ProductsImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\ActivityLog;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use App\Support\PublicStorageSync;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with(['categories', 'brands', 'unit']);

        // Search
        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $normalized = preg_replace('/[^\pL\pN]+/u', ' ', $search);
            $tokens = array_values(array_filter(preg_split('/\s+/', (string) $normalized), fn ($token) => mb_strlen($token) >= 2));

            $query->where(function ($q) use ($search, $tokens) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");

                if (!empty($tokens)) {
                    $q->orWhere(function ($allTokensQuery) use ($tokens) {
                        foreach ($tokens as $token) {
                            $allTokensQuery->where(function ($tokenFieldQuery) use ($token) {
                                $tokenFieldQuery->where('name', 'like', "%{$token}%")
                                    ->orWhere('sku', 'like', "%{$token}%")
                                    ->orWhere('barcode', 'like', "%{$token}%");
                            });
                        }
                    });
                }
            });
        }

        // Filter by category
        if ($request->has('category_id') && $request->category_id) {
            $query->where(function ($outer) use ($request) {
                $outer->where('category_id', $request->category_id)
                    ->orWhereHas('categories', function($q) use ($request) {
                        $q->where('categories.id', $request->category_id);
                    });
            });
        }

        // Filter by brand
        if ($request->has('brand_id') && $request->brand_id) {
            $query->where(function ($outer) use ($request) {
                $outer->where('brand_id', $request->brand_id)
                    ->orWhereHas('brands', function($q) use ($request) {
                        $q->where('brands.id', $request->brand_id);
                    });
            });
        }

        // Filter by stock status
        if ($request->has('stock_status')) {
            if ($request->stock_status === 'low') {
                $query->whereColumn('stock_quantity', '<=', 'alert_quantity');
            } elseif ($request->stock_status === 'out') {
                $query->where('stock_quantity', 0);
            }
        }

        $products = $query->latest()->paginate(20);
        $categories = Category::where('is_active', true)->get();
        $brands = Brand::where('is_active', true)->get();
        $units = Unit::where('is_active', true)->orderBy('base_unit_multiplier')->get(['id','name','short_name','base_unit_multiplier']);

        return view('products.index', compact('products', 'categories', 'brands', 'units'));
    }

    public function barcodePrint()
    {
        $settings = $this->getBarcodeSettings();

        return view('products.barcode-print', compact('settings'));
    }

    public function barcodeSearch(Request $request)
    {
        $term = trim((string) $request->input('term', ''));
        if ($term === '') {
            return response()->json([]);
        }

        $normalized = preg_replace('/[^\pL\pN]+/u', ' ', $term);
        $tokens = array_values(array_filter(preg_split('/\s+/', (string) $normalized), fn ($token) => mb_strlen($token) >= 2));

        $products = Product::query()
            ->where('is_active', true)
            ->where(function ($q) use ($term, $tokens) {
                $q->where('name', 'LIKE', "%{$term}%")
                    ->orWhere('sku', 'LIKE', "%{$term}%")
                    ->orWhere('barcode', 'LIKE', "%{$term}%");

                if (!empty($tokens)) {
                    $q->orWhere(function ($allTokensQuery) use ($tokens) {
                        foreach ($tokens as $token) {
                            $allTokensQuery->where(function ($tokenFieldQuery) use ($token) {
                                $tokenFieldQuery->where('name', 'LIKE', "%{$token}%")
                                    ->orWhere('sku', 'LIKE', "%{$token}%")
                                    ->orWhere('barcode', 'LIKE', "%{$token}%");
                            });
                        }
                    });
                }
            })
            ->orderBy('name')
            ->take(20)
            ->get(['id', 'name', 'sku', 'barcode', 'selling_price']);

        $payload = $products->map(fn($product) => [
            'id' => $product->id,
            'name' => $product->name,
            'barcode' => $product->barcode ?: $product->sku,
            'selling_price' => (float) $product->selling_price,
        ]);

        return response()->json($payload);
    }

    public function barcodePrintPreview(Request $request)
    {
        $validated = $request->validate([
            'products' => 'required|array',
            'products.*' => 'integer|exists:products,id',
            'qty' => 'nullable|array',
            'qty.*' => 'nullable|integer|min:1|max:1000',
            'show_secret_price' => 'nullable|array',
            'show_secret_price.*' => 'nullable|boolean',
        ]);

        $productIds = $validated['products'] ?? [];
        $qtyMap = $validated['qty'] ?? [];
        $showSecretPriceMap = $validated['show_secret_price'] ?? [];

        $products = Product::whereIn('id', $productIds)
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'barcode', 'selling_price', 'cost_price']);

        $items = [];
        foreach ($products as $product) {
            $qty = (int) ($qtyMap[$product->id] ?? 1);
            $qty = max(1, $qty);
            $items[] = [
                'product' => $product,
                'qty' => $qty,
                'show_secret_price' => (bool) ($showSecretPriceMap[$product->id] ?? false),
            ];
        }

        $settings = $this->getBarcodeSettings();
        $currency = Setting::get('currency', 'Rs');
        $currencyPosition = Setting::get('currency_position', 'before');
        $barcodeHeightPx = (float) $settings['barcode_height'] * 37.795;

        return view('products.barcode-print-preview', compact('items', 'settings', 'currency', 'currencyPosition', 'barcodeHeightPx'));
    }

    private function getBarcodeSettings(): array
    {
        $defaultMap = [
            '0' => 'E',
            '1' => 'M',
            '2' => 'O',
            '3' => 'D',
            '4' => 'T',
            '5' => 'W',
            '6' => 'I',
            '7' => 'N',
            '8' => 'K',
            '9' => 'L',
        ];

        $settings = [
            'barcode_sticker_width' => (float) Setting::get('barcode_sticker_width', 3),
            'barcode_sticker_height' => (float) Setting::get('barcode_sticker_height', 2),
            'barcode_paper_width' => (float) Setting::get('barcode_paper_width', 4),
            'barcode_labels_per_row' => (int) Setting::get('barcode_labels_per_row', 1),
            'barcode_row_gap' => (float) Setting::get('barcode_row_gap', 0.3),
            'barcode_alignment' => (string) Setting::get('barcode_alignment', 'left'),
            'barcode_top_margin' => (float) Setting::get('barcode_top_margin', 0),
            'barcode_left_margin' => (float) Setting::get('barcode_left_margin', 0.5),
            'barcode_col_gap' => (float) Setting::get('barcode_col_gap', 0),
            'barcode_shop_name_size' => (int) Setting::get('barcode_shop_name_size', 6),
            'barcode_product_name_size' => (int) Setting::get('barcode_product_name_size', 7),
            'barcode_price_tag_size' => (int) Setting::get('barcode_price_tag_size', 9),
            'barcode_secret_code_size' => (int) Setting::get('barcode_secret_code_size', 8),
            'barcode_number_size' => (int) Setting::get('barcode_number_size', 8),
            'barcode_height' => (float) Setting::get('barcode_height', 0.7),
            'barcode_sticker_top_padding' => (float) Setting::get('barcode_sticker_top_padding', 0.1),
            'barcode_sticker_bottom_padding' => (float) Setting::get('barcode_sticker_bottom_padding', 0.1),
            'barcode_show_cost_code' => (bool) Setting::get('barcode_show_cost_code', false),
            'barcode_enable_selling_secret_code' => (bool) Setting::get('barcode_enable_selling_secret_code', false),
            'barcode_cost_code_map' => (array) Setting::get('barcode_cost_code_map', $defaultMap),
            'barcode_selling_code_map' => (array) Setting::get('barcode_selling_code_map', $defaultMap),
        ];

        $presets = (array) Setting::get('barcode_presets', []);
        $defaultPreset = (string) Setting::get('barcode_default_preset', '');
        if ($defaultPreset !== '') {
            foreach ($presets as $preset) {
                if (($preset['name'] ?? '') === $defaultPreset && isset($preset['settings'])) {
                    $settings = array_replace((array) $preset['settings'], $settings);
                    break;
                }
            }
        }

        // Always respect current toggle value from settings screen for secret-code visibility.
        // This prevents old preset values from forcing the secret code to appear when turned off.
        $settings['barcode_show_cost_code'] = (bool) Setting::get('barcode_show_cost_code', false);
        $settings['barcode_enable_selling_secret_code'] = (bool) Setting::get('barcode_enable_selling_secret_code', false);

        if (!isset($settings['barcode_cost_code_map']) || empty($settings['barcode_cost_code_map'])) {
            $settings['barcode_cost_code_map'] = $defaultMap;
        }

        if (!isset($settings['barcode_selling_code_map']) || empty($settings['barcode_selling_code_map'])) {
            $settings['barcode_selling_code_map'] = $defaultMap;
        }

        return $settings;
    }
    public function create()
    {
        $categories = Category::where('is_active', true)->get();
        $brands = Brand::where('is_active', true)->get();
        $units = Unit::where('is_active', true)->get();
        // VAT settings
        $vatEnabled = \App\Models\Setting::get('vat_enabled', false);
        $vatRate = (float) \App\Models\Setting::get('vat_rate', 0);

        $defaultMap = [
            '0' => 'E',
            '1' => 'M',
            '2' => 'O',
            '3' => 'D',
            '4' => 'T',
            '5' => 'W',
            '6' => 'I',
            '7' => 'N',
            '8' => 'K',
            '9' => 'L',
        ];
        $costCodeMap = (array) \App\Models\Setting::get('barcode_cost_code_map', $defaultMap);
        $sellingCodeMap = (array) \App\Models\Setting::get('barcode_selling_code_map', $defaultMap);
        $sellingSecretEnabled = (bool) \App\Models\Setting::get('barcode_enable_selling_secret_code', false);

        return view('products.create', compact('categories', 'brands', 'units', 'vatEnabled', 'vatRate', 'costCodeMap', 'sellingCodeMap', 'sellingSecretEnabled'));
    }

    public function store(Request $request)
    {
        // Filter empty values from arrays
        if ($request->has('categories')) {
            $request->merge(['categories' => array_values(array_filter($request->categories, fn($value) => !is_null($value) && $value !== ''))]);
        }
        if ($request->has('brands')) {
            $request->merge(['brands' => array_values(array_unique(array_filter($request->brands, fn($value) => !is_null($value) && $value !== '')))]);
        }

        $hasMultipleBrands = is_array($request->brands ?? null) && count($request->brands) > 1;

        $skuRule = ['nullable', 'string'];
        if (!$hasMultipleBrands) {
            $skuRule[] = Rule::unique('products', 'sku');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => $skuRule,
            'categories' => 'nullable|array',
            'categories.*' => 'exists:categories,id',
            'brands' => 'nullable|array',
            'brands.*' => 'exists:brands,id',
            'unit_id' => 'required|exists:units,id',
            'visible_units' => 'nullable|array',
            'visible_units.*' => 'exists:units,id',
            'description' => 'nullable|string',
            'cost_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'brand_cost_price' => 'nullable|array',
            'brand_cost_price.*' => 'nullable|numeric|min:0',
            'brand_selling_price' => 'nullable|array',
            'brand_selling_price.*' => 'nullable|numeric|min:0',
            'brand_stock_quantity' => 'nullable|array',
            'brand_stock_quantity.*' => 'nullable|integer|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'alert_quantity' => 'required|integer|min:0',
            'image' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('products', 'public');
            PublicStorageSync::syncFile($validated['image']);
        }

        // If no units selected treat as null (means show all)
        if (empty($validated['visible_units'])) {
            $validated['visible_units'] = null;
        }

        $categories = $validated['categories'] ?? [];
        $brandIds = $validated['brands'] ?? [];
        $brandIds = array_values(array_unique($brandIds));

        $brandCostPrice = $request->input('brand_cost_price', []);
        $brandSellingPrice = $request->input('brand_selling_price', []);
        $brandStockQuantity = $request->input('brand_stock_quantity', []);
        
        // Remove array fields before create
        unset($validated['categories'], $validated['brands'], $validated['brand_cost_price'], $validated['brand_selling_price'], $validated['brand_stock_quantity']);

        // If no brands selected, create a single product (existing behavior)
        if (empty($brandIds)) {
            // Auto-generate SKU if not provided
            if (empty($validated['sku'])) {
                $validated['sku'] = $this->generateSKU();
            }

            // Barcode is always the same as SKU
            $validated['barcode'] = $validated['sku'];

            // Set legacy columns for backward compatibility
            $validated['category_id'] = $categories[0] ?? null;
            $validated['brand_id'] = null;

            $product = Product::create($validated);

            if (!empty($categories)) {
                $product->categories()->sync($categories);
            }

            ActivityLog::log('create', "Created product: {$product->name}", $product);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'product' => $product,
                    'message' => 'Product created successfully!',
                ]);
            }

            return redirect()->route('products.index')->with('success', 'Product created successfully!');
        }

        // Brands selected: create one product per brand
        $brandsById = Brand::whereIn('id', $brandIds)->get()->keyBy('id');
        $createdProducts = [];

        $baseSku = $validated['sku'] ?? null;

        foreach ($brandIds as $index => $brandId) {
            $brand = $brandsById->get($brandId);
            if (!$brand) {
                continue;
            }

            $productData = $validated;
            $productData['name'] = $validated['name'];
            $productData['brand_id'] = $brandId;
            $productData['category_id'] = $categories[0] ?? null;

            $productData['cost_price'] = isset($brandCostPrice[$brandId]) && $brandCostPrice[$brandId] !== ''
                ? (float) $brandCostPrice[$brandId]
                : (float) $validated['cost_price'];
            $productData['selling_price'] = isset($brandSellingPrice[$brandId]) && $brandSellingPrice[$brandId] !== ''
                ? (float) $brandSellingPrice[$brandId]
                : (float) $validated['selling_price'];
            $productData['stock_quantity'] = isset($brandStockQuantity[$brandId]) && $brandStockQuantity[$brandId] !== ''
                ? (int) $brandStockQuantity[$brandId]
                : (int) $validated['stock_quantity'];

            // SKU handling
            if (empty($baseSku)) {
                $productData['sku'] = $this->generateSKU();
            } else {
                $candidate = $baseSku;
                if (count($brandIds) > 1) {
                    $candidate = $baseSku . '-' . str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT);
                }
                $productData['sku'] = $this->makeUniqueSku($candidate);
            }

            $productData['barcode'] = $productData['sku'];

            $product = Product::create($productData);
            if (!empty($categories)) {
                $product->categories()->sync($categories);
            }
            $product->brands()->sync([$brandId]);

            ActivityLog::log('create', "Created product: {$product->name}", $product);
            $createdProducts[] = $product;
        }

        $message = 'Created ' . count($createdProducts) . ' product(s) successfully!';

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'product' => $createdProducts[0] ?? null,
                'products' => $createdProducts,
                'message' => $message,
            ]);
        }

        return redirect()->route('products.index')->with('success', $message);
    }

    private function makeUniqueSku(string $sku): string
    {
        $candidate = $sku;
        $counter = 1;

        while (Product::where('sku', $candidate)->exists()) {
            $candidate = $sku . '-' . str_pad((string)$counter, 2, '0', STR_PAD_LEFT);
            $counter++;
        }

        return $candidate;
    }

    public function edit(Product $product)
    {
        $categories = Category::where('is_active', true)->get();
        $brands = Brand::where('is_active', true)->get();
        $units = Unit::where('is_active', true)->get();
        // VAT settings
        $vatEnabled = \App\Models\Setting::get('vat_enabled', false);
        $vatRate = (float) \App\Models\Setting::get('vat_rate', 0);

        $defaultMap = [
            '0' => 'E',
            '1' => 'M',
            '2' => 'O',
            '3' => 'D',
            '4' => 'T',
            '5' => 'W',
            '6' => 'I',
            '7' => 'N',
            '8' => 'K',
            '9' => 'L',
        ];
        $costCodeMap = (array) \App\Models\Setting::get('barcode_cost_code_map', $defaultMap);
        $sellingCodeMap = (array) \App\Models\Setting::get('barcode_selling_code_map', $defaultMap);
        $sellingSecretEnabled = (bool) \App\Models\Setting::get('barcode_enable_selling_secret_code', false);

        return view('products.edit', compact('product', 'categories', 'brands', 'units', 'vatEnabled', 'vatRate', 'costCodeMap', 'sellingCodeMap', 'sellingSecretEnabled'));
    }

    public function update(Request $request, Product $product)
    {
        // Filter empty values from arrays
        if ($request->has('categories')) {
            $request->merge(['categories' => array_filter($request->categories, fn($value) => !is_null($value) && $value !== '')]);
        }
        if ($request->has('brands')) {
            $request->merge(['brands' => array_filter($request->brands, fn($value) => !is_null($value) && $value !== '')]);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|unique:products,sku,' . $product->id,
            'categories' => 'nullable|array',
            'categories.*' => 'exists:categories,id',
            'brands' => 'nullable|array',
            'brands.*' => 'exists:brands,id',
            'unit_id' => 'required|exists:units,id',
            'visible_units' => 'nullable|array',
            'visible_units.*' => 'exists:units,id',
            'description' => 'nullable|string',
            'cost_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'alert_quantity' => 'required|integer|min:0',
            'image' => 'nullable|image|max:2048',
        ]);

        // Auto-generate SKU if not provided
        if (empty($validated['sku'])) {
            $validated['sku'] = $this->generateSKU();
        }

        // Barcode is always the same as SKU
        $validated['barcode'] = $validated['sku'];

        if ($request->hasFile('image')) {
            // Delete old image
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
                PublicStorageSync::removeFile($product->image);
            }
            $validated['image'] = $request->file('image')->store('products', 'public');
            PublicStorageSync::syncFile($validated['image']);
        }

        $oldData = $product->toArray();
        if (empty($validated['visible_units'])) {
            $validated['visible_units'] = null;
        }

        $categories = $validated['categories'] ?? [];
        $brands = $validated['brands'] ?? [];
        unset($validated['categories'], $validated['brands']);

        // Set legacy columns
        $validated['category_id'] = $categories[0] ?? null;
        $validated['brand_id'] = $brands[0] ?? null;

        $product->update($validated);
        
        $product->categories()->sync($categories);
        $product->brands()->sync($brands);

        ActivityLog::log('update', "Updated product: {$product->name}", $product, [
            'old' => $oldData,
            'new' => $product->toArray()
        ]);

        return redirect()->route('products.index')->with('success', 'Product updated successfully!');
    }

    public function destroy(Product $product)
    {
        try {
            ActivityLog::log('delete', "Deleted product: {$product->name}", $product);

            if ($product->image) {
                Storage::disk('public')->delete($product->image);
                PublicStorageSync::removeFile($product->image);
            }

            $product->delete();

            return redirect()->route('products.index')->with('success', 'Product deleted successfully!');
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23000') {
                return redirect()->route('products.index')->with('error', 'This product cannot be deleted because it is already used in sales or purchase records.');
            }

            throw $exception;
        }
    }

    // Product import helpers
    public function importForm()
    {
        return view('products.import');
    }

    public function downloadTemplate()
    {
        $categories = Category::where('is_active', true)->pluck('name')->filter()->values()->toArray();
        $brands = Brand::where('is_active', true)->pluck('name')->filter()->values()->toArray();
        $units = Unit::where('is_active', true)->pluck('short_name')->filter()->values()->toArray();

        return Excel::download(new ProductImportTemplateExport($categories, $brands, $units), 'products-import-template.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        $sheet = Excel::toCollection(new ProductsImport(), $request->file('file'))->first();

        if (!$sheet || $sheet->isEmpty()) {
            return redirect()->route('products.import')
                ->with('error', 'The uploaded file is empty or missing the header row.');
        }

        $categoryMap = Category::where('is_active', true)->get()->keyBy(fn ($category) => strtolower($category->name));
        $brandMap = Brand::where('is_active', true)->get()->keyBy(fn ($brand) => strtolower($brand->name));
        $units = Unit::where('is_active', true)->get();
        $unitsByShortName = $units->filter(fn ($unit) => filled($unit->short_name))->keyBy(fn ($unit) => strtolower($unit->short_name));
        $unitsByName = $units->filter(fn ($unit) => filled($unit->name))->keyBy(fn ($unit) => strtolower($unit->name));

        $defaultMap = [
            '0' => 'E',
            '1' => 'M',
            '2' => 'O',
            '3' => 'D',
            '4' => 'T',
            '5' => 'W',
            '6' => 'I',
            '7' => 'N',
            '8' => 'K',
            '9' => 'L',
        ];
        $costCodeMap = (array) \App\Models\Setting::get('barcode_cost_code_map', $defaultMap);
        $reverseCostCodeMap = [];
        foreach ($costCodeMap as $digit => $code) {
            if ($code !== null && $code !== '') {
                $reverseCostCodeMap[strtoupper((string) $code)] = (string) $digit;
            }
        }
        $zeroFallback = (bool) config('app.secret_cost_zero_fallback', false);

        $imported = 0;
        $issues = [];

        foreach ($sheet as $index => $row) {
            $rowNumber = $index + 2;
            $name = trim($row['name'] ?? '');

            if ($name === '') {
                $issues[] = "Row {$rowNumber}: product name is required.";
                continue;
            }

            $unitValue = trim($row['unit_short_name'] ?? $row['unit_name'] ?? '');

            if ($unitValue === '') {
                $issues[] = "Row {$rowNumber}: unit short name is required.";
                continue;
            }

            $unitKey = strtolower($unitValue);
            $unit = $unitsByShortName[$unitKey] ?? $unitsByName[$unitKey] ?? null;

            if (!$unit) {
                $issues[] = "Row {$rowNumber}: unit \"{$unitValue}\" not found.";
                continue;
            }

            $sku = trim($row['sku'] ?? '');
            if ($sku !== '' && Product::where('sku', $sku)->exists()) {
                $issues[] = "Row {$rowNumber}: SKU \"{$sku}\" already exists.";
                continue;
            }

            if ($sku === '') {
                $sku = $this->generateSKU();
            }

            $brandName = trim($row['brand_name'] ?? '');
            $brand = null;
            if ($brandName !== '') {
                $brandKey = strtolower($brandName);
                $brand = $brandMap[$brandKey] ?? null;
                if (!$brand) {
                    $issues[] = "Row {$rowNumber}: brand \"{$brandName}\" not found; product created without a brand.";
                }
            }

            $categoryNames = collect(preg_split('/[|,]/', $row['category_names'] ?? '', -1, PREG_SPLIT_NO_EMPTY))
                ->map(fn ($item) => trim($item))
                ->reject(fn ($item) => $item === '');
            $categoryIds = [];
            $missingCategories = [];

            foreach ($categoryNames as $categoryName) {
                $categoryKey = strtolower($categoryName);
                if (isset($categoryMap[$categoryKey])) {
                    $categoryIds[] = $categoryMap[$categoryKey]->id;
                } else {
                    $missingCategories[] = $categoryName;
                }
            }

            $categoryIds = array_values(array_unique($categoryIds));

            if (!empty($missingCategories)) {
                $issues[] = "Row {$rowNumber}: categories not found (" . implode(', ', $missingCategories) . "); only mapped the existing ones.";
            }

            $costCode = trim((string) ($row['cost_code'] ?? $row['secret_cost_code'] ?? ''));
            $costPrice = is_numeric($row['cost_price'] ?? null) ? (float) $row['cost_price'] : null;
            $sellingPrice = is_numeric($row['selling_price'] ?? null) ? (float) $row['selling_price'] : 0;
            $stockQuantity = is_numeric($row['stock_quantity'] ?? null) ? (int) $row['stock_quantity'] : 0;
            $alertQuantity = is_numeric($row['alert_quantity'] ?? null) ? (int) $row['alert_quantity'] : 0;
            $description = trim($row['description'] ?? '') ?: null;

            if ($costCode !== '') {
                $decodedCost = $this->decodeCostCode($costCode, $reverseCostCodeMap, $zeroFallback);
                if ($decodedCost !== null) {
                    if ($costPrice !== null && abs($decodedCost - $costPrice) > 0.01) {
                        $issues[] = "Row {$rowNumber}: cost code overrides cost price (" . number_format($decodedCost, 2) . ").";
                    }
                    $costPrice = $decodedCost;
                } else {
                    $issues[] = "Row {$rowNumber}: cost code could not be decoded; using cost price value.";
                }
            }

            if ($costPrice === null) {
                $costPrice = 0;
            }

            $product = Product::create([
                'name' => $name,
                'sku' => $sku,
                'barcode' => $sku,
                'unit_id' => $unit->id,
                'cost_price' => $costPrice,
                'selling_price' => $sellingPrice,
                'stock_quantity' => $stockQuantity,
                'alert_quantity' => $alertQuantity,
                'description' => $description,
                'category_id' => $categoryIds[0] ?? null,
                'brand_id' => $brand->id ?? null,
                'is_active' => true,
            ]);

            if (!empty($categoryIds)) {
                $product->categories()->sync($categoryIds);
            }

            if ($brand) {
                $product->brands()->sync([$brand->id]);
            }

            ActivityLog::log('import', "Imported product via Excel: {$product->name}", $product);
            $imported++;
        }

        $redirect = redirect()->route('products.import');

        if ($imported > 0) {
            $plural = $imported === 1 ? '' : 's';
            $redirect = $redirect->with('success', "{$imported} product{$plural} imported successfully.");
        } else {
            $redirect = $redirect->with('error', 'No products were imported. Please verify the template and try again.');
        }

        if (!empty($issues)) {
            $redirect = $redirect->with('import_errors', $issues);
        }

        return $redirect;
    }

    public function updatePrice(Request $request, Product $product)
    {
        $validated = $request->validate([
            'cost_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
        ]);

        $oldPrice = [
            'cost_price' => $product->cost_price,
            'selling_price' => $product->selling_price
        ];

        $product->update($validated);

        ActivityLog::log('update', "Updated price for product: {$product->name}", $product, [
            'old' => $oldPrice,
            'new' => $validated
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Price updated successfully!'
        ]);
    }

    private function decodeCostCode(string $input, array $reverseMap, bool $zeroFallback): ?float
    {
        $raw = strtoupper($input);
        $parts = explode('.', $raw);
        $leftRaw = $parts[0] ?? '';
        $rightRaw = count($parts) > 1 ? implode('', array_slice($parts, 1)) : '';

        $leftDigits = '';
        foreach (str_split($leftRaw) as $ch) {
            if (isset($reverseMap[$ch])) {
                $leftDigits .= $reverseMap[$ch];
            } elseif ($zeroFallback && $ch !== '') {
                $leftDigits .= '0';
            } elseif (ctype_digit($ch)) {
                $leftDigits .= $ch;
            }
        }

        $rightDigits = '';
        foreach (str_split($rightRaw) as $ch) {
            if (isset($reverseMap[$ch])) {
                $rightDigits .= $reverseMap[$ch];
            } elseif ($zeroFallback && $ch !== '') {
                $rightDigits .= '0';
            } elseif (ctype_digit($ch)) {
                $rightDigits .= $ch;
            }
        }

        if ($leftDigits === '' && $rightDigits === '') {
            return null;
        }

        if ($leftDigits === '') {
            $leftDigits = '0';
        }

        if ($rightDigits === '') {
            $rightDigits = '00';
        } elseif (strlen($rightDigits) === 1) {
            $rightDigits .= '0';
        } elseif (strlen($rightDigits) > 2) {
            $rightDigits = substr($rightDigits, 0, 2);
        }

        return (float) ($leftDigits . '.' . $rightDigits);
    }

    /**
     * Generate unique SKU for products
     */
    private function generateSKU()
    {
        $prefix = 'PRD';
        $timestamp = now()->format('ymd'); // YYMMDD format
        
        // Get the last product created today
        $lastProduct = Product::whereDate('created_at', today())
            ->orderBy('id', 'desc')
            ->first();
        
        if ($lastProduct && preg_match('/PRD\d{6}-(\d+)/', $lastProduct->sku, $matches)) {
            $number = intval($matches[1]) + 1;
        } else {
            $number = 1;
        }
        
        // Generate SKU in format: PRD241109-001
        return $prefix . $timestamp . '-' . str_pad($number, 3, '0', STR_PAD_LEFT);
    }
}
