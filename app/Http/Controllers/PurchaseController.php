<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use App\Support\PublicStorageSync;
use App\Support\SecretPos;

class PurchaseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $query = Purchase::with('supplier')->latest();
        $query = SecretPos::excludeHiddenPurchaseRanges($query, 'total_amount');
        $purchases = $query->get();
        return view('purchases.index', compact('purchases'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // load suppliers and products for the purchase create form if needed
        $suppliers = \App\Models\Supplier::orderBy('name')->get();
        $products = \App\Models\Product::orderBy('name')->get();
        
        // Pre-format products for JS to avoid Blade parsing issues
        $productsData = $products->map(function($p) {
            return [
                'id' => $p->id,
                'name' => $p->name,
                'cost_price' => (float) $p->cost_price,
                'selling_price' => (float) $p->selling_price,
                'sku' => $p->sku,
                'barcode' => $p->barcode,
            ];
        })->values()->toArray();
        
        return view('purchases.create', compact('suppliers', 'products', 'productsData'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'reference_no' => 'nullable|string|max:255',
            'purchase_date' => 'nullable|date',
            'status' => 'nullable|string|in:pending,ordered,received',
            'discount_type' => 'nullable|string|in:none,fixed,percentage',
            'discount_amount' => 'nullable|numeric|min:0',
            'tax_id' => 'nullable|string',
            'shipping_cost' => 'nullable|numeric|min:0',
            'shipping_type' => 'nullable|string|in:divided,expense',
            'payment_method' => 'required|string|in:cash,card,bank_transfer,cheque,credit',
            'payment_amount' => 'nullable|numeric|min:0',
            'document' => 'nullable|file|mimes:pdf,jpg,jpeg,png,csv,zip,doc,docx|max:5120',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'items.*.selling_price' => 'required|numeric|min:0',
        ]);

        return DB::transaction(function () use ($request, $validated) {
            // Calculate totals
            $subtotal = 0;
            $totalQty = 0;
            foreach ($validated['items'] as $it) {
                $subtotal += $it['quantity'] * $it['unit_cost'];
                $totalQty += $it['quantity'];
            }

            // Calculate discount
            $discountAmount = 0;
            $discountType = $validated['discount_type'] ?? 'none';
            if ($discountType === 'fixed') {
                $discountAmount = $validated['discount_amount'] ?? 0;
            } elseif ($discountType === 'percentage') {
                $discountAmount = $subtotal * (($validated['discount_amount'] ?? 0) / 100);
            }

            // Calculate tax
            $taxAmount = 0;
            $taxId = $validated['tax_id'] ?? null;
            if ($taxId === 'vat_10') {
                $taxAmount = ($subtotal - $discountAmount) * 0.10;
            } elseif ($taxId === 'vat_5') {
                $taxAmount = ($subtotal - $discountAmount) * 0.05;
            }

            // Handle shipping cost
            $shippingCost = $validated['shipping_cost'] ?? 0;
            $shippingType = $validated['shipping_type'] ?? 'divided';

            $grandTotal = $subtotal - $discountAmount + $taxAmount + $shippingCost;

            // Handle document upload
            $documentPath = null;
            if ($request->hasFile('document')) {
                $documentPath = $request->file('document')->store('purchases', 'public');
                PublicStorageSync::syncFile($documentPath);
            }

            // Payment calculation
            $paymentAmount = $validated['payment_amount'] ?? 0;
            $paidAmount = min($paymentAmount, $grandTotal);
            $dueAmount = $grandTotal - $paidAmount;
            $paymentStatus = 'unpaid';
            if ($paidAmount >= $grandTotal) {
                $paymentStatus = 'paid';
            } elseif ($paidAmount > 0) {
                $paymentStatus = 'partial';
            }

            $purchase = Purchase::create([
                'supplier_id' => $validated['supplier_id'],
                'user_id' => auth()->id(),
                'reference_no' => $validated['reference_no'] ?? null,
                'purchase_date' => $validated['purchase_date'] ?? now()->toDateString(),
                'status' => $validated['status'] ?? 'pending',
                'discount_type' => $discountType,
                'discount_amount' => $discountAmount,
                'tax_id' => $taxId,
                'tax_amount' => $taxAmount,
                'shipping_cost' => $shippingCost,
                'shipping_type' => $shippingType,
                'payment_method' => $validated['payment_method'],
                'total_amount' => $grandTotal,
                'paid_amount' => $paidAmount,
                'due_amount' => $dueAmount,
                'payment_status' => $paymentStatus,
                'document_path' => $documentPath,
                'notes' => $validated['notes'] ?? null,
            ]);

            // Calculate shipping cost per item if dividing
            $shippingPerItem = ($shippingType === 'divided' && $totalQty > 0) ? $shippingCost / $totalQty : 0;

            foreach ($validated['items'] as $it) {
                // If shipping is divided, add it to unit cost
                $finalUnitCost = $it['unit_cost'];
                if ($shippingType === 'divided') {
                    $finalUnitCost += $shippingPerItem;
                }

                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $it['product_id'],
                    'quantity' => $it['quantity'],
                    'unit_cost' => $finalUnitCost,
                    'total' => $it['quantity'] * $finalUnitCost,
                ]);

                // Update product stock and prices
                $product = Product::find($it['product_id']);
                $product->stock_quantity = ($product->stock_quantity ?? 0) + $it['quantity'];
                // Update cost price with shipping if divided
                $product->cost_price = $finalUnitCost;
                $product->selling_price = $it['selling_price'];
                $product->save();
            }

            // If shipping is expense, create expense record (future feature)
            // if ($shippingType === 'expense' && $shippingCost > 0) {
            //     // Create expense record
            // }

            return redirect()->route('purchases.index')
                ->with('success', 'Purchase created successfully');
        });
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $purchase = \App\Models\Purchase::with('supplier', 'items')->findOrFail($id);
        if (SecretPos::isPurchaseHidden((float) $purchase->total_amount)) {
            abort(404);
        }
        return view('purchases.show', compact('purchase'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $purchase = \App\Models\Purchase::with('items')->findOrFail($id);
        if (SecretPos::isPurchaseHidden((float) $purchase->total_amount)) {
            abort(404);
        }
        $suppliers = \App\Models\Supplier::orderBy('name')->get();
        $products = \App\Models\Product::orderBy('name')->get();
        return view('purchases.edit', compact('purchase', 'suppliers', 'products'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        // Minimal placeholder: validate and redirect back
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'purchase_date' => 'nullable|date',
        ]);

        // TODO: implement full update logic
        return redirect()->route('purchases.index')->with('success', 'Purchase updated (placeholder)');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $purchase = \App\Models\Purchase::findOrFail($id);
        if (SecretPos::isPurchaseHidden((float) $purchase->total_amount)) {
            abort(404);
        }
        $purchase->delete();

        return redirect()->route('purchases.index')->with('success', 'Purchase deleted successfully');
    }
}
