<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductPriceController extends Controller
{
    public function index(Product $product)
    {
        $prices = $product->prices()
            ->orderByDesc('is_default')
            ->orderBy('selling_price')
            ->get();

        return response()->json($prices);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'stock_qty' => ['nullable', 'numeric', 'min:0'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $duplicate = ProductPrice::query()
            ->where('product_id', $validated['product_id'])
            ->where('status', 'active')
            ->where('cost_price', round((float) $validated['cost_price'], 2))
            ->where('selling_price', round((float) $validated['selling_price'], 2))
            ->exists();

        if ($duplicate) {
            return back()->withErrors(['selling_price' => 'This active cost and selling price option already exists.'])->withInput();
        }

        DB::transaction(function () use ($validated) {
            if (! empty($validated['is_default'])) {
                ProductPrice::where('product_id', $validated['product_id'])->update(['is_default' => false]);
            }

            $price = ProductPrice::create([
                'product_id' => $validated['product_id'],
                'cost_price' => round((float) $validated['cost_price'], 2),
                'selling_price' => round((float) $validated['selling_price'], 2),
                'stock_qty' => round((float) ($validated['stock_qty'] ?? 0), 3),
                'is_default' => (bool) ($validated['is_default'] ?? false),
                'status' => 'active',
            ]);

            ProductPrice::ensureDefaultForProduct($price->product_id);
            $this->syncProductFallbackPrice($price);
        });

        return back()->with('success', 'Price option saved successfully.');
    }

    public function update(Request $request, ProductPrice $productPrice)
    {
        $validated = $request->validate([
            'cost_price' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'stock_qty' => ['nullable', 'numeric', 'min:0'],
            'is_default' => ['nullable', 'boolean'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $cost = round((float) $validated['cost_price'], 2);
        $selling = round((float) $validated['selling_price'], 2);
        $status = $validated['status'] ?? $productPrice->status;

        $duplicate = ProductPrice::query()
            ->where('product_id', $productPrice->product_id)
            ->where('id', '!=', $productPrice->id)
            ->where('status', 'active')
            ->where('cost_price', $cost)
            ->where('selling_price', $selling)
            ->when($status !== 'active', fn ($query) => $query->whereRaw('1 = 0'))
            ->exists();

        if ($duplicate) {
            return back()->withErrors(['selling_price' => 'This active cost and selling price option already exists.'])->withInput();
        }

        DB::transaction(function () use ($productPrice, $validated, $cost, $selling, $status) {
            if (! empty($validated['is_default']) && $status === 'active') {
                ProductPrice::where('product_id', $productPrice->product_id)->update(['is_default' => false]);
            }

            $productPrice->update([
                'cost_price' => $cost,
                'selling_price' => $selling,
                'stock_qty' => round((float) ($validated['stock_qty'] ?? 0), 3),
                'is_default' => $status === 'active' ? (bool) ($validated['is_default'] ?? false) : false,
                'status' => $status,
            ]);

            ProductPrice::ensureDefaultForProduct($productPrice->product_id);
            $this->syncProductFallbackPrice($productPrice->fresh());
        });

        return back()->with('success', 'Price option updated successfully.');
    }

    public function destroy(ProductPrice $productPrice)
    {
        DB::transaction(function () use ($productPrice) {
            $usedInSale = $productPrice->saleItems()->exists();
            $productId = $productPrice->product_id;

            if ($usedInSale) {
                $productPrice->update([
                    'status' => 'inactive',
                    'is_default' => false,
                ]);
            } else {
                $productPrice->delete();
            }

            ProductPrice::ensureDefaultForProduct($productId);
        });

        return back()->with('success', 'Price option removed successfully.');
    }

    private function syncProductFallbackPrice(ProductPrice $price): void
    {
        if (! $price->is_default || $price->status !== 'active') {
            return;
        }

        Product::whereKey($price->product_id)->update([
            'cost_price' => $price->cost_price,
            'selling_price' => $price->selling_price,
        ]);
    }
}
