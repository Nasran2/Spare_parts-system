<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Setting;
use App\Models\TaxSetting;
use App\Services\DecimalMath;
use App\Services\DashboardVisibilityService;
use App\Services\TaxCalculationService;
use App\Services\TaxPostingService;
use App\Support\PublicStorageSync;
use App\Support\SecretPos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $isPreOrder = $request->boolean('is_pre_order');
        $hiddenSupplierIds = DashboardVisibilityService::hiddenSupplierIdsForUser(auth()->user());
        $query = Purchase::with('supplier')->where('is_pre_order', $isPreOrder)->latest();
        if (! empty($hiddenSupplierIds)) {
            $query->whereNotIn('supplier_id', $hiddenSupplierIds);
        }
        $query = SecretPos::excludeHiddenPurchaseRanges($query, 'total_amount');
        $purchases = $query->get();
        $controls = DashboardVisibilityService::configForUser(auth()->user());

        return view('purchases.index', compact('purchases', 'controls', 'isPreOrder'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $isPreOrder = $request->boolean('is_pre_order');
        $canUseSellingSecretCode = auth()->user()?->isSuperAdmin() === true;
        // load suppliers and products for the purchase create form if needed
        $hiddenSupplierIds = DashboardVisibilityService::hiddenSupplierIdsForUser(auth()->user());
        $suppliers = \App\Models\Supplier::query()
            ->when(! empty($hiddenSupplierIds), fn ($query) => $query->whereNotIn('id', $hiddenSupplierIds))
            ->orderBy('name')
            ->get();
        $includeOriginal = $request->boolean('include_original');
        $products = \App\Models\Product::with('taxSetting')
            ->when(!$includeOriginal, fn($q) => $q->where('is_pre_order', $isPreOrder))
            ->orderBy('name')->get();
        $taxSettings = TaxSetting::current();

        // Pre-format products for JS to avoid Blade parsing issues
        $productsData = $products->map(function ($p) use ($taxSettings) {
            return [
                'id' => $p->id,
                'name' => $p->name,
                'cost_price' => (float) $p->cost_price,
                'selling_price' => (float) $p->selling_price,
                'sku' => $p->sku,
                'barcode' => $p->barcode,
                'tax' => app(TaxCalculationService::class)->productRules($p, $taxSettings, 'purchase'),
            ];
        })->values()->toArray();

        $stores = \App\Models\Store::where('is_active', true)->orderBy('name')->get();
        $defaultStore = $stores->firstWhere('is_default', true) ?? $stores->first();

        $pendingCustomerCheques = \App\Models\ChequePayment::with('customer')
            ->where('status', 'pending')
            ->where('type', 'customer')
            ->orderBy('cheque_date')
            ->get();

        return view('purchases.create', compact('suppliers', 'products', 'productsData', 'canUseSellingSecretCode', 'stores', 'defaultStore', 'taxSettings', 'pendingCustomerCheques', 'isPreOrder', 'includeOriginal'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $rules = [
            'is_pre_order' => 'boolean',
            'supplier_id' => 'required|exists:suppliers,id',
            'store_ids' => 'required|array|min:1',
            'store_ids.*' => 'exists:stores,id',
        ];
        
        if ($request->boolean('is_pre_order')) {
            $rules['store_ids'] = 'nullable|array';
        }
        
        $validated = $request->validate($rules + [
            'reference_no' => 'nullable|string|max:255',
            'purchase_date' => 'nullable|date',
            'status' => 'nullable|string|in:pending,ordered,received',
            'discount_type' => 'nullable|string|in:none,fixed,percentage',
            'discount_amount' => 'nullable|numeric|min:0',
            'tax_id' => 'nullable|string',
            'supplier_tax_invoice_number' => 'nullable|string|max:100',
            'supplier_tax_invoice_date' => 'nullable|date',
            'purchase_vat_mode' => 'nullable|in:global,inclusive,exclusive',
            'input_vat_claimable' => 'nullable|boolean',
            'shipping_cost' => 'nullable|numeric|min:0',
            'shipping_type' => 'nullable|string|in:divided,expense',
            'payments' => 'nullable|array',
            'payments.*.method' => 'required|string',
            'payments.*.amount' => 'required|numeric|min:0',
            'payments.*.cheque_id' => 'nullable|exists:cheque_payments,id',
            'payments.*.cheque_date' => 'nullable|date',
            'payments.*.cheque_number' => 'nullable|string',
            'payments.*.bank_name' => 'nullable|string',
            'payment_method' => 'nullable|string',
            'document' => 'nullable|file|mimes:pdf,jpg,jpeg,png,csv,zip,doc,docx|max:5120',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'items.*.line_discount_type' => 'nullable|in:fixed,percent',
            'items.*.line_discount_value' => 'nullable|numeric|min:0',
            'items.*.selling_price' => 'required|numeric|min:0',
            'items.*.add_to_price_stock' => 'nullable|boolean',
            'items.*.store_stock' => 'nullable|array',
            'items.*.store_stock.*' => 'nullable|numeric|min:0',
        ]);

        if (DashboardVisibilityService::isSupplierHiddenForUser((int) $validated['supplier_id'], auth()->user())) {
            abort(404);
        }

        return DB::transaction(function () use ($request, $validated) {
            $usePriceWiseStock = (bool) Setting::get('use_price_wise_stock', true);
            $purchaseDate = $validated['purchase_date'] ?? now()->toDateString();
            $taxSettings = TaxSetting::current($purchaseDate);
            $taxCalculator = app(TaxCalculationService::class);
            $taxInputs = [];
            $totalQtyMinor = 0;
            foreach ($validated['items'] as $item) {
                $product = Product::with('taxSetting')->findOrFail($item['product_id']);
                $rules = $taxCalculator->productRules($product, $taxSettings, 'purchase');
                if (($validated['purchase_vat_mode'] ?? 'global') !== 'global') {
                    $rules['price_mode'] = $validated['purchase_vat_mode'];
                }
                $taxInputs[] = [
                    'unit_price' => (string) $item['unit_cost'],
                    'quantity' => (string) $item['quantity'],
                    'line_discount_type' => $item['line_discount_type'] ?? 'fixed',
                    'line_discount_value' => (string) ($item['line_discount_value'] ?? '0'),
                    'tax_status' => $rules['tax_status'],
                    'vat_rate' => $rules['vat_rate'],
                    'price_mode' => $rules['price_mode'],
                    'vat_allowed' => $rules['vat_allowed'],
                    'vat_enabled' => $taxSettings->vat_enabled,
                ];
                $totalQtyMinor += DecimalMath::parse((string) $item['quantity']);
            }

            $discountType = $validated['discount_type'] ?? 'none';
            $taxInvoice = $taxCalculator->calculateInvoice(
                $taxInputs,
                $discountType,
                (string) ($validated['discount_amount'] ?? '0')
            );
            $subtotal = $taxInvoice['totals']['gross'];
            $discountAmount = $taxInvoice['totals']['discount'];
            $taxAmount = $taxInvoice['totals']['vat'];
            $taxId = $taxSettings->vat_enabled ? $taxSettings->id : null;

            // Handle shipping cost
            $shippingCost = (string) ($validated['shipping_cost'] ?? '0');
            $shippingType = $validated['shipping_type'] ?? 'divided';
            $grandTotalMinor = DecimalMath::parse($taxInvoice['totals']['total'])
                + DecimalMath::parse($shippingCost);
            $grandTotal = DecimalMath::currency($grandTotalMinor);
            $inputVatClaimable = $request->boolean('input_vat_claimable')
                && $taxSettings->vat_enabled;

            // Handle document upload
            $documentPath = null;
            if ($request->hasFile('document')) {
                $documentPath = $request->file('document')->store('purchases', 'public');
                PublicStorageSync::syncFile($documentPath);
            }

                        // Payment calculation
            $payments = $validated['payments'] ?? [];
            if (empty($payments) && !empty($validated['payment_method']) && isset($validated['payment_amount'])) {
                $payments = [['method' => $validated['payment_method'], 'amount' => $validated['payment_amount']]];
            }

            $paidAmount = 0;
            $heldOwnChequeAmount = 0;
            $chequePaymentsData = [];

            foreach ($payments as $p) {
                if (empty($p['method']) || empty($p['amount']) || $p['amount'] <= 0) continue;
                if ($p['method'] === 'customer_cheque' && !empty($p['cheque_id'])) {
                    // It's a party cheque! We don't add this to paidAmount, we add it to paidAmount but it acts like cash because it's immediately passed.
                    // Wait, actually, if we give them a customer cheque, it acts as an immediate payment for the purchase.
                    $paidAmount += $p['amount'];
                    $chequePaymentsData[] = ['type' => 'customer', 'cheque_id' => $p['cheque_id'], 'amount' => $p['amount']];
                } elseif ($p['method'] === 'own_cheque') {
                    // Own cheques are held, they don't immediately reduce the purchase due_amount if we treat it like held_cheque_amount!
                    // Let's add it to heldOwnChequeAmount.
                    $heldOwnChequeAmount += $p['amount'];
                    $chequePaymentsData[] = [
                        'type' => 'own',
                        'amount' => $p['amount'],
                        'bank_name' => $p['bank_name'] ?? null,
                        'cheque_number' => $p['cheque_number'] ?? null,
                        'cheque_date' => $p['cheque_date'] ?? null,
                    ];
                } else {
                    $paidAmount += $p['amount'];
                }
            }

            $paidAmount = min($paidAmount, $grandTotal);
            $dueAmount = max(0, $grandTotal - $paidAmount - $heldOwnChequeAmount);
            
            $paymentStatus = 'unpaid';
            if ($dueAmount <= 0 && $heldOwnChequeAmount <= 0) {
                $paymentStatus = 'paid';
            } elseif ($paidAmount > 0 || $heldOwnChequeAmount > 0) {
                $paymentStatus = 'partial';
            }

            $purchase = Purchase::create([
                'is_pre_order' => $request->boolean('is_pre_order'),
                'supplier_id' => $validated['supplier_id'],
                'store_id' => $validated['store_ids'][0] ?? null,
                'user_id' => auth()->id(),
                'reference_no' => $validated['reference_no'] ?? null,
                'purchase_date' => $purchaseDate,
                'status' => $validated['status'] ?? 'pending',
                'discount_type' => $discountType,
                'discount_amount' => $discountAmount,
                'tax_id' => $taxId,
                'tax_amount' => $taxAmount,
                'supplier_tax_invoice_number' => $validated['supplier_tax_invoice_number'] ?? null,
                'supplier_tax_invoice_date' => $validated['supplier_tax_invoice_date'] ?? null,
                'purchase_vat_mode' => $validated['purchase_vat_mode'] ?? 'global',
                'taxable_purchase_value' => $taxInvoice['totals']['taxable'],
                'input_vat_claimable' => $inputVatClaimable,
                'tax_period' => substr($purchaseDate, 0, 7),
                'tax_snapshot' => $taxSettings->snapshot(),
                'shipping_cost' => $shippingCost,
                'shipping_type' => $shippingType,
                'payment_method' => $validated['payment_method'] ?? 'cash',
                'total_amount' => $grandTotal,
                'paid_amount' => $paidAmount,
                'held_own_cheque_amount' => $heldOwnChequeAmount,
                'due_amount' => $dueAmount,
                'payment_status' => $paymentStatus,
                'document_path' => $documentPath,
                'notes' => $validated['notes'] ?? null,
            ]);

            $shippingPerItemMinor = ($shippingType === 'divided' && $totalQtyMinor > 0)
                ? DecimalMath::roundDiv(
                    DecimalMath::parse($shippingCost) * DecimalMath::SCALE,
                    $totalQtyMinor
                )
                : 0;

                        foreach ($chequePaymentsData as $cd) {
                if ($cd['type'] === 'customer') {
                    $cheque = \App\Models\ChequePayment::find($cd['cheque_id']);
                    if ($cheque && $cheque->status === 'pending') {
                        // Pass this cheque to the supplier!
                        app(\App\Services\ChequePaymentService::class)->pass($cheque, auth()->id(), false, null, $purchase->supplier_id);
                        $cheque->purchase_id = $purchase->id;
                        $cheque->save();
                    }
                } elseif ($cd['type'] === 'own') {
                    \App\Models\ChequePayment::create([
                        'type' => 'own',
                        'purchase_id' => $purchase->id,
                        'supplier_id' => $purchase->supplier_id,
                        'user_id' => auth()->id(),
                        'cheque_date' => $cd['cheque_date'] ? \Carbon\Carbon::parse($cd['cheque_date'])->toDateString() : now()->toDateString(),
                        'cheque_number' => (string) $cd['cheque_number'],
                        'bank_name' => $cd['bank_name'],
                        'amount' => $cd['amount'],
                        'status' => 'pending',
                        'notes' => 'Issued for Purchase #' . $purchase->id,
                    ]);
                }
            }

            $taxPairs = [];
            foreach ($validated['items'] as $index => $it) {
                $taxResult = $taxInvoice['lines'][$index];
                $quantityMinor = DecimalMath::parse((string) $it['quantity']);
                $inventoryLineMinor = DecimalMath::parse(
                    $inputVatClaimable
                        ? $taxResult['taxable_amount']
                        : $taxResult['total_amount']
                );
                $finalUnitCostMinor = $quantityMinor > 0
                    ? DecimalMath::roundDiv($inventoryLineMinor * DecimalMath::SCALE, $quantityMinor)
                    : 0;
                if ($shippingType === 'divided') {
                    $finalUnitCostMinor += $shippingPerItemMinor;
                }
                $finalUnitCost = DecimalMath::currency($finalUnitCostMinor);

                $product = Product::findOrFail($it['product_id']);
                $priceOption = $this->findOrCreatePurchasePriceOption(
                    $product,
                    (float) $finalUnitCost,
                    (float) $it['selling_price'],
                    (float) $it['quantity'],
                    $usePriceWiseStock && (bool) ($it['add_to_price_stock'] ?? true)
                );

                $purchaseItem = PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $it['product_id'],
                    'product_price_id' => $priceOption?->id,
                    'quantity' => $it['quantity'],
                    'unit_cost' => $finalUnitCost,
                    'selling_price' => $it['selling_price'],
                    'total' => DecimalMath::currency(
                        DecimalMath::multiply(
                            DecimalMath::parse($finalUnitCost),
                            $quantityMinor
                        )
                    ),
                ]);
                $taxPairs[] = ['model' => $purchaseItem, 'tax' => $taxResult];

                // Update product stock and prices
                $product->stock_quantity = ($product->stock_quantity ?? 0) + $it['quantity'];
                // Update cost price with shipping if divided
                $product->cost_price = $finalUnitCost;
                $product->selling_price = $it['selling_price'];
                $product->save();

                // Increment store stock quantities for each store entered
                $storeStockInput = $it['store_stock'] ?? [];
                
                if (empty($storeStockInput) && $it['quantity'] > 0) {
                    $defaultStore = \App\Models\Store::where('is_default', true)->first() ?? \App\Models\Store::first();
                    if ($defaultStore) {
                        $storeStockInput[$defaultStore->id] = $it['quantity'];
                    }
                }

                foreach ($storeStockInput as $storeId => $storeQty) {
                    $storeQty = (float) $storeQty;
                    if ($storeQty <= 0) {
                        continue;
                    }
                    $storeStockRecord = \App\Models\StoreStock::firstOrCreate([
                        'store_id' => (int) $storeId,
                        'product_id' => $product->id,
                        'product_price_id' => $priceOption?->id,
                    ], ['quantity' => 0]);
                    $storeStockRecord->increment('quantity', $storeQty);
                }
            }

            $purchase->loadMissing(['supplier', 'store']);
            app(TaxPostingService::class)->postPurchase($purchase, $taxPairs, $taxSettings);

            // If shipping is expense, create expense record (future feature)
            // if ($shippingType === 'expense' && $shippingCost > 0) {
            //     // Create expense record
            // }

            return redirect()->route('purchases.index')
                ->with('success', 'Purchase created successfully');
        });
    }

    private function findOrCreatePurchasePriceOption(Product $product, float $costPrice, float $sellingPrice, float $qty, bool $addStock): ?ProductPrice
    {
        $costPrice = round($costPrice, 2);
        $sellingPrice = round($sellingPrice, 2);

        $price = ProductPrice::query()
            ->where('product_id', $product->id)
            ->where('status', 'active')
            ->where('cost_price', $costPrice)
            ->where('selling_price', $sellingPrice)
            ->lockForUpdate()
            ->first();

        if ($price) {
            if ($addStock) {
                $price->increment('stock_qty', $qty);
            }

            ProductPrice::ensureDefaultForProduct($product->id);

            return $price->fresh();
        }

        $hasActivePrice = ProductPrice::query()
            ->where('product_id', $product->id)
            ->where('status', 'active')
            ->exists();

        $price = ProductPrice::create([
            'product_id' => $product->id,
            'cost_price' => $costPrice,
            'selling_price' => $sellingPrice,
            'stock_qty' => $addStock ? round($qty, 3) : 0,
            'is_default' => ! $hasActivePrice,
            'status' => 'active',
        ]);

        ProductPrice::ensureDefaultForProduct($product->id);

        return $price;
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
        if (DashboardVisibilityService::isSupplierHiddenForUser((int) $purchase->supplier_id, auth()->user())) {
            abort(404);
        }
        $controls = DashboardVisibilityService::configForUser(auth()->user());

        return view('purchases.show', compact('purchase', 'controls'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, string $id)
    {
        $purchase = \App\Models\Purchase::with(['items.product', 'supplier'])->findOrFail($id);
        $isPreOrder = $purchase->is_pre_order;
        if (SecretPos::isPurchaseHidden((float) $purchase->total_amount)) {
            abort(404);
        }
        if (DashboardVisibilityService::isSupplierHiddenForUser((int) $purchase->supplier_id, auth()->user())) {
            abort(404);
        }
        $hiddenSupplierIds = DashboardVisibilityService::hiddenSupplierIdsForUser(auth()->user());
        $suppliers = \App\Models\Supplier::query()
            ->when(! empty($hiddenSupplierIds), fn ($query) => $query->whereNotIn('id', $hiddenSupplierIds))
            ->orderBy('name')
            ->get();
        $pendingCustomerCheques = \App\Models\ChequePayment::with('customer')
            ->where('status', 'pending')
            ->where('type', 'customer')
            ->orderBy('cheque_date')
            ->get();

                $includeOriginal = request()->boolean('include_original');
        return view('purchases.edit', compact('purchase', 'suppliers', 'pendingCustomerCheques', 'isPreOrder', 'includeOriginal'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $purchase = \App\Models\Purchase::findOrFail($id);
        if (SecretPos::isPurchaseHidden((float) $purchase->total_amount)) {
            abort(404);
        }
        if (DashboardVisibilityService::isSupplierHiddenForUser((int) $purchase->supplier_id, auth()->user())) {
            abort(404);
        }

        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'reference_no' => 'nullable|string|max:255',
            'purchase_date' => 'nullable|date',
            'status' => 'required|string|in:pending,ordered,received',
            'payments' => 'nullable|array',
            'payments.*.method' => 'required|string',
            'payments.*.amount' => 'required|numeric|min:0',
            'payments.*.cheque_id' => 'nullable|exists:cheque_payments,id',
            'payments.*.cheque_date' => 'nullable|date',
            'payments.*.cheque_number' => 'nullable|string',
            'payments.*.bank_name' => 'nullable|string',
            'payment_method' => 'nullable|string',
            'document' => 'nullable|file|mimes:pdf,jpg,jpeg,png,csv,zip,doc,docx|max:5120',
            'notes' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($request, $validated, $purchase) {
                        $payments = $validated['payments'] ?? [];
            if (empty($payments) && !empty($validated['payment_method']) && isset($validated['paid_amount'])) {
                $payments = [['method' => $validated['payment_method'], 'amount' => $validated['paid_amount']]];
            }

            $paidAmount = 0;
            $heldOwnChequeAmount = 0;
            $chequePaymentsData = [];

            foreach ($payments as $p) {
                if (empty($p['method']) || empty($p['amount']) || $p['amount'] <= 0) continue;
                if ($p['method'] === 'customer_cheque' && !empty($p['cheque_id'])) {
                    $paidAmount += $p['amount'];
                    $chequePaymentsData[] = ['type' => 'customer', 'cheque_id' => $p['cheque_id'], 'amount' => $p['amount']];
                } elseif ($p['method'] === 'own_cheque') {
                    $heldOwnChequeAmount += $p['amount'];
                    $chequePaymentsData[] = [
                        'type' => 'own',
                        'amount' => $p['amount'],
                        'bank_name' => $p['bank_name'] ?? null,
                        'cheque_number' => $p['cheque_number'] ?? null,
                        'cheque_date' => $p['cheque_date'] ?? null,
                    ];
                } else {
                    $paidAmount += $p['amount'];
                }
            }

            // We need to add existing held own cheques!
            // Wait, if they are editing the purchase, they might add MORE payments, or replace.
            // For simplicity, we just add the newly submitted payments on top of the old ones.
            // But wait, the edit screen usually just lets you update the `paid_amount` directly.
            // If we're fully supporting multi-pay in edit, we should probably append the new payments.
            
            $totalPaidAmount = min($purchase->paid_amount + $paidAmount, (float) $purchase->total_amount);
            $totalHeldAmount = $purchase->held_own_cheque_amount + $heldOwnChequeAmount;
            
            $dueAmount = max(0, (float) $purchase->total_amount - $totalPaidAmount - $totalHeldAmount);
            
            $paymentStatus = 'unpaid';
            if ($dueAmount <= 0 && $totalHeldAmount <= 0) {
                $paymentStatus = 'paid';
            } elseif ($totalPaidAmount > 0 || $totalHeldAmount > 0) {
                $paymentStatus = 'partial';
            }

                        foreach ($chequePaymentsData as $cd) {
                if ($cd['type'] === 'customer') {
                    $cheque = \App\Models\ChequePayment::find($cd['cheque_id']);
                    if ($cheque && $cheque->status === 'pending') {
                        app(\App\Services\ChequePaymentService::class)->pass($cheque, auth()->id(), false, null, $purchase->supplier_id);
                        $cheque->purchase_id = $purchase->id;
                        $cheque->save();
                    }
                } elseif ($cd['type'] === 'own') {
                    \App\Models\ChequePayment::create([
                        'type' => 'own',
                        'purchase_id' => $purchase->id,
                        'supplier_id' => $purchase->supplier_id,
                        'user_id' => auth()->id(),
                        'cheque_date' => $cd['cheque_date'] ? \Carbon\Carbon::parse($cd['cheque_date'])->toDateString() : now()->toDateString(),
                        'cheque_number' => (string) $cd['cheque_number'],
                        'bank_name' => $cd['bank_name'],
                        'amount' => $cd['amount'],
                        'status' => 'pending',
                        'notes' => 'Issued for Purchase #' . $purchase->id,
                    ]);
                }
            }

            $documentPath = $purchase->document_path;
            if ($request->hasFile('document')) {
                $documentPath = $request->file('document')->store('purchases', 'public');
                PublicStorageSync::syncFile($documentPath);
            }

            $purchase->update([
                'supplier_id' => $validated['supplier_id'],
                'reference_no' => $validated['reference_no'] ?? null,
                'purchase_date' => $validated['purchase_date'] ?? now()->toDateString(),
                'status' => $validated['status'],
                'payment_method' => $validated['payment_method'] ?? $purchase->payment_method,
                'paid_amount' => $totalPaidAmount,
                'held_own_cheque_amount' => $totalHeldAmount,
                'due_amount' => $dueAmount,
                'payment_status' => $paymentStatus,
                'document_path' => $documentPath,
                'notes' => $validated['notes'] ?? null,
            ]);

            return redirect()->route('purchases.show', $purchase->id)
                ->with('success', 'Purchase updated successfully');
        });
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
        DB::transaction(function () use ($purchase) {
            app(TaxPostingService::class)->reverse('purchase', $purchase->id, 'Purchase deleted');
            $purchase->delete();
        });

        return redirect()->route('purchases.index')->with('success', 'Purchase deleted successfully');
    }
}
