<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\ActivityLog;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with(['categories', 'brands', 'unit']);

        // Search
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        // Filter by category
        if ($request->has('category_id') && $request->category_id) {
            $query->whereHas('categories', function($q) use ($request) {
                $q->where('categories.id', $request->category_id);
            });
        }

        // Filter by brand
        if ($request->has('brand_id') && $request->brand_id) {
            $query->whereHas('brands', function($q) use ($request) {
                $q->where('brands.id', $request->brand_id);
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

    public function create()
    {
        $categories = Category::where('is_active', true)->get();
        $brands = Brand::where('is_active', true)->get();
        $units = Unit::where('is_active', true)->get();
        // VAT settings
        $vatEnabled = \App\Models\Setting::get('vat_enabled', false);
        $vatRate = (float) \App\Models\Setting::get('vat_rate', 0);

        return view('products.create', compact('categories', 'brands', 'units', 'vatEnabled', 'vatRate'));
    }

    public function store(Request $request)
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
            'sku' => 'nullable|string|unique:products,sku',
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
            $validated['image'] = $request->file('image')->store('products', 'public');
        }

        // If no units selected treat as null (means show all)
        if (empty($validated['visible_units'])) {
            $validated['visible_units'] = null;
        }

        $categories = $validated['categories'] ?? [];
        $brands = $validated['brands'] ?? [];
        
        // Remove array fields before create
        unset($validated['categories'], $validated['brands']);

        // Set legacy columns for backward compatibility
        $validated['category_id'] = $categories[0] ?? null;
        $validated['brand_id'] = $brands[0] ?? null;

        $product = Product::create($validated);
        
        if (!empty($categories)) {
            $product->categories()->sync($categories);
        }
        if (!empty($brands)) {
            $product->brands()->sync($brands);
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

    public function edit(Product $product)
    {
        $categories = Category::where('is_active', true)->get();
        $brands = Brand::where('is_active', true)->get();
        $units = Unit::where('is_active', true)->get();
        // VAT settings
        $vatEnabled = \App\Models\Setting::get('vat_enabled', false);
        $vatRate = (float) \App\Models\Setting::get('vat_rate', 0);

        return view('products.edit', compact('product', 'categories', 'brands', 'units', 'vatEnabled', 'vatRate'));
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
            }
            $validated['image'] = $request->file('image')->store('products', 'public');
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
        ActivityLog::log('delete', "Deleted product: {$product->name}", $product);

        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }

        $product->delete();

        return redirect()->route('products.index')->with('success', 'Product deleted successfully!');
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
