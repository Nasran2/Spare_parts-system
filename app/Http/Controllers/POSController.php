<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Setting;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Models\Unit;

class POSController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Render the POS index view with current cart snapshot
        $cart = $this->cart();
        $customers = Customer::where('is_active', true)->orderBy('name')->get(['id','name']);
        $invoicePaperSize = Setting::get('invoice_paper_size', 'a4');
        $posLayout = Setting::get('pos_layout', 'default');

        $allUnits = Unit::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'short_name']);

        $products = Product::with(['unit', 'categories', 'brands'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $productPayload = $products
            ->map(fn (Product $product) => $this->formatProductForPOS($product))
            ->values();

        $posCardFee = [
            'enabled' => (bool) Setting::get('pos_card_fee_enabled', false),
            'rate' => (float) Setting::get('pos_card_fee_rate', 0),
            'mode' => Setting::get('pos_card_fee_mode', 'customer'),
            'record_expense' => (bool) Setting::get('pos_card_fee_record_expense', true),
            'expense_category_id' => (int) Setting::get('pos_card_fee_expense_category_id', 0),
        ];
        
        return view('pos.index', [
            'cart' => $cart,
            'customers' => $customers,
            'invoicePaperSize' => $invoicePaperSize,
            'posLayout' => $posLayout,
            'posCardFee' => $posCardFee,
            'allUnits' => $allUnits,
            'productPayload' => $productPayload,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    /**
     * Search products for autocomplete
     */
    public function searchProducts(Request $request)
    {
        $term = $request->input('term');
        if (!$term) return response()->json([]);

        $products = Product::with(['unit', 'categories', 'brands'])
            ->where('is_active', true)
            ->where(function ($q) use ($term) {
                $q->where('name', 'LIKE', "%{$term}%")
                    ->orWhere('sku', 'LIKE', "%{$term}%")
                    ->orWhere('barcode', 'LIKE', "%{$term}%");
            })
            ->orderBy('name')
            ->take(20)
            ->get();

        $payload = $products->map(fn($product) => $this->formatProductForPOS($product));
        return response()->json($payload);
    }

    /**
     * @param mixed $product
     */
    private function formatProductForPOS($product): array
    {
        if (!$product instanceof Product) {
            throw new \InvalidArgumentException('formatProductForPOS expects a Product model');
        }
        return [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
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
    }

    /**
     * Search for a sale to return items from.
     */
    public function searchSale(Request $request)
    {
        $term = $request->input('term');
        if (!$term) {
            return response()->json(['message' => 'Please enter a Sale ID or Invoice Number'], 422);
        }

        $sale = Sale::with(['items.product', 'customer'])
            ->where('id', $term)
            ->orWhere('sale_no', 'LIKE', "%{$term}%")
            ->first();

        if (!$sale) {
            return response()->json(['message' => 'Sale not found'], 404);
        }

        $items = $sale->items->map(function ($item) {
            $returnedQty = \App\Models\SaleReturnItem::where('sale_item_id', $item->id)->sum('quantity');
            $remainingQty = $item->quantity - $returnedQty;
            
            return [
                'sale_item_id' => $item->id,
                'product_name' => $item->product->name,
                'sold_qty' => $item->quantity,
                'returned_qty' => $returnedQty,
                'remaining_qty' => $remainingQty,
                'unit_price' => $item->unit_price,
                'total' => $item->total,
            ];
        });

        return response()->json([
            'sale' => [
                'id' => $sale->id,
                'sale_no' => $sale->sale_no,
                'customer' => $sale->customer ? $sale->customer->name : 'Walk-in',
                'date' => $sale->sale_date,
            ],
            'items' => $items
        ]);
    }

    /**
     * Add a return item to the cart.
     */
    public function addReturnItem(Request $request)
    {
        $request->validate([
            'sale_item_id' => 'required|exists:sale_items,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $saleItem = SaleItem::with('product')->find($request->sale_item_id);
        
        // Validate remaining quantity
        $returnedQty = \App\Models\SaleReturnItem::where('sale_item_id', $saleItem->id)->sum('quantity');
        $remainingQty = $saleItem->quantity - $returnedQty;

        if ($request->quantity > $remainingQty) {
            return response()->json(['message' => "Cannot return more than remaining quantity ({$remainingQty})"], 422);
        }

        $cart = $this->cart();
        $items = $cart['items'];
        
        // Unique key for return item
        $cartKey = 'return_' . $saleItem->id;
        
        // Negative quantity for return
        $returnQty = -1 * abs($request->quantity);
        
        $items[$cartKey] = [
            'id' => $saleItem->product_id,
            'cart_key' => $cartKey,
            'name' => $saleItem->product->name . ' (RETURN)',
            'price' => (float)$saleItem->unit_price, // Keep positive price, qty is negative
            'qty' => $returnQty,
            'base_price' => (float)$saleItem->unit_price,
            'is_return' => true,
            'sale_item_id' => $saleItem->id,
            'max_return_qty' => $remainingQty,
            'sale_ref' => $saleItem->sale->sale_no ?? 'Unknown',
        ];
        
        $cart['items'] = $items;
        $cart = $this->putCart($cart);
        return response()->json($cart);
    }

    /**
     * Handle POS checkout action.
     * This is a minimal placeholder to avoid route errors and can be expanded later.
     */
    public function checkout(Request $request)
    {
        $cart = $this->cart();
        if (empty($cart['items'])) {
            return response()->json(['message' => 'Cart is empty'], 422);
        }

        DB::beginTransaction();
        try {
            $userId = Auth::id();
            $customerId = $request->integer('customer_id') ?: null;
            
            // Separate items
            $saleItemsData = [];
            $returnItemsData = [];
            
            foreach ($cart['items'] as $item) {
                if ($item['qty'] > 0) {
                    $saleItemsData[] = $item;
                } elseif ($item['qty'] < 0) {
                    $returnItemsData[] = $item;
                }
            }

            $createdSale = null;
            $createdReturn = null;

            // Process Returns
            if (!empty($returnItemsData)) {
                // Group by original sale if needed. For now, we create one return record.
                // If items come from multiple sales, we might need multiple return records or just pick one sale_id.
                // To be safe, we'll group by sale_id.
                $returnsBySale = [];
                foreach ($returnItemsData as $rItem) {
                    // If sale_item_id is missing (shouldn't happen for returns), skip or handle error
                    if (!isset($rItem['sale_item_id'])) continue;
                    $saleItem = \App\Models\SaleItem::find($rItem['sale_item_id']);
                    if ($saleItem) {
                        $returnsBySale[$saleItem->sale_id][] = $rItem;
                    }
                }

                foreach ($returnsBySale as $saleId => $items) {
                    $totalRefund = 0;
                    foreach ($items as $item) {
                        $totalRefund += abs($item['qty']) * $item['price'];
                    }

                    $saleReturn = \App\Models\SaleReturn::create([
                        'sale_id' => $saleId,
                        'user_id' => $userId,
                        'return_date' => now(),
                        'total_refund' => $totalRefund,
                        'notes' => 'POS Exchange/Return',
                    ]);

                    foreach ($items as $item) {
                        $qty = abs($item['qty']);
                        // Validate again
                        $saleItem = \App\Models\SaleItem::find($item['sale_item_id']);
                        $alreadyReturned = \App\Models\SaleReturnItem::where('sale_item_id', $saleItem->id)->sum('quantity');
                        if ($qty > ($saleItem->quantity - $alreadyReturned)) {
                            throw new \Exception("Cannot return more than remaining quantity for " . $item['name']);
                        }

                        \App\Models\SaleReturnItem::create([
                            'sale_return_id' => $saleReturn->id,
                            'sale_item_id' => $item['sale_item_id'],
                            'product_id' => $item['id'],
                            'quantity' => $qty,
                            'return_price' => $item['price'],
                            'total' => $qty * $item['price'],
                        ]);

                        // Increment Stock
                        $product = Product::find($item['id']);
                        if ($product) {
                            $product->increment('stock_quantity', $qty);
                        }
                    }
                    $createdReturn = $saleReturn;
                }
            }

            // Process New Sale
            if (!empty($saleItemsData)) {
                $subtotal = 0.0;
                $lineDiscountTotal = 0.0;

                foreach ($saleItemsData as $idx => $it) {
                    $qty = (float) ($it['qty'] ?? 0);
                    $price = (float) ($it['price'] ?? 0);
                    $lineSubtotal = $qty * $price;
                    $lineDiscount = 0.0;

                    if ($qty > 0 && !empty($it['line_discount']) && is_array($it['line_discount'])) {
                        $type = ($it['line_discount']['type'] ?? 'fixed') === 'percent' ? 'percent' : 'fixed';
                        $value = (float) ($it['line_discount']['value'] ?? 0);
                        $value = max(0, $value);

                        if ($type === 'percent') {
                            $value = min(100, $value);
                            $lineDiscount = round($lineSubtotal * ($value / 100), 2);
                        } else {
                            $lineDiscount = round($value, 2);
                        }
                        $lineDiscount = min($lineDiscount, max(0, $lineSubtotal));
                    }

                    $saleItemsData[$idx]['line_subtotal'] = round($lineSubtotal, 2);
                    $saleItemsData[$idx]['line_discount_amount'] = round($lineDiscount, 2);
                    $saleItemsData[$idx]['line_total'] = round($lineSubtotal - $lineDiscount, 2);

                    $subtotal += $lineSubtotal;
                    $lineDiscountTotal += $lineDiscount;
                }

                $baseForCartDiscount = $subtotal - $lineDiscountTotal;
                $cartDiscount = 0.0;
                if ($baseForCartDiscount > 0) {
                    if (($cart['discount']['type'] ?? 'fixed') === 'percent') {
                        $cartDiscount = round($baseForCartDiscount * ((float) ($cart['discount']['value'] ?? 0)) / 100, 2);
                    } else {
                        $cartDiscount = (float) ($cart['discount']['value'] ?? 0);
                    }
                    $cartDiscount = min(max(0, $cartDiscount), $baseForCartDiscount);
                }

                $discountAmount = $lineDiscountTotal + $cartDiscount;
                $taxAmount = round(($subtotal - $discountAmount) * ((float)($cart['tax_rate'] ?? 0)) / 100, 2);
                $totalAmount = max(0, $subtotal - $discountAmount + $taxAmount);

                // Payment method(s)
                $allowedMethods = ['cash', 'card', 'bank_transfer', 'mobile_payment'];

                $paymentMethod = (string) $request->input('payment_method', 'cash');
                if (!in_array($paymentMethod, $allowedMethods, true)) {
                    $paymentMethod = 'cash';
                }

                $splitPayments = $request->input('payments');
                $normalizedPayments = [];
                $cardPaidAmount = 0.0;
                if (is_array($splitPayments)) {
                    foreach ($splitPayments as $p) {
                        if (!is_array($p)) continue;
                        $method = (string) ($p['method'] ?? $p['payment_method'] ?? '');
                        $amount = (float) ($p['amount'] ?? 0);
                        if (!in_array($method, $allowedMethods, true)) continue;
                        if ($amount <= 0) continue;
                        $amount = round($amount, 2);
                        $normalizedPayments[] = ['method' => $method, 'amount' => $amount];
                        if ($method === 'card') {
                            $cardPaidAmount += $amount;
                        }
                    }

                    if (!empty($normalizedPayments)) {
                        // Choose a representative payment_method for sales table enum
                        $paymentMethod = collect($normalizedPayments)
                            ->sortByDesc('amount')
                            ->first()['method'] ?? $paymentMethod;
                    }
                }

                // Card fee (optional)
                $cardFeeEnabled = (bool) Setting::get('pos_card_fee_enabled', false);
                $cardFeeRate = (float) Setting::get('pos_card_fee_rate', 0);
                $cardFeeMode = (string) Setting::get('pos_card_fee_mode', 'customer'); // customer|seller
                $cardFeeRecordExpense = (bool) Setting::get('pos_card_fee_record_expense', true);
                $cardFeeExpenseCategoryId = (int) Setting::get('pos_card_fee_expense_category_id', 0);

                $cardFee = 0.0;
                $hasCard = ($paymentMethod === 'card') || ($cardPaidAmount > 0);
                $cardFeeBase = $cardPaidAmount > 0 ? $cardPaidAmount : $totalAmount;
                if ($hasCard && $cardFeeEnabled && $cardFeeRate > 0 && $cardFeeBase > 0) {
                    $cardFee = round($cardFeeBase * ($cardFeeRate / 100), 2);
                }

                $effectiveTotalAmount = $totalAmount;
                if ($hasCard && $cardFee > 0 && $cardFeeMode === 'customer') {
                    $effectiveTotalAmount = round($totalAmount + $cardFee, 2);
                }

                // Calculate Payment
                $paidCash = (float) $request->input('paid_amount', 0);
                if (!empty($normalizedPayments)) {
                    $paidCash = (float) collect($normalizedPayments)->sum('amount');
                }
                
                $returnCredit = 0;
                if (!empty($returnItemsData)) {
                     foreach ($returnItemsData as $rItem) {
                         $returnCredit += abs($rItem['qty']) * $rItem['price'];
                     }
                }

                $totalPaid = $paidCash + $returnCredit;
                $salePaid = min($effectiveTotalAmount, $totalPaid);
                $due = $effectiveTotalAmount - $salePaid;
                if ($due < 0) $due = 0;

                if ($due > 0 && !$customerId) {
                    throw new \Exception('Customer is required when there is a due amount.');
                }

                $sale = Sale::create([
                    'customer_id'   => $customerId,
                    'user_id'       => $userId,
                    'sale_date'     => now(),
                    'subtotal'      => $subtotal,
                    'tax'           => $taxAmount,
                    'discount'      => $discountAmount,
                    'total_amount'  => $effectiveTotalAmount,
                    'paid_amount'   => $salePaid,
                    'due_amount'    => $due,
                    'payment_status'=> $due > 0 ? 'partial' : 'paid',
                    'payment_method'=> $paymentMethod,
                    'sale_type'     => 'sale',
                    'notes'         => $request->string('notes')
                        . (!empty($returnItemsData) ? " (Exchange/Return Processed)" : "")
                        . (($hasCard && $cardFee > 0)
                            ? ($cardFeeMode === 'customer'
                                ? " (Card fee charged to customer: {$cardFeeRate}% = {$cardFee})"
                                : " (Card fee paid by seller: {$cardFeeRate}% = {$cardFee})")
                            : "")
                ]);

                // Record split payments as Payment rows (optional but useful for reporting)
                if (!empty($normalizedPayments)) {
                    foreach ($normalizedPayments as $p) {
                        Payment::create([
                            'sale_id' => $sale->id,
                            'customer_id' => $customerId,
                            'amount' => $p['amount'],
                            'payment_method' => $p['method'],
                            'payment_date' => now()->toDateString(),
                            'notes' => $request->input('notes'),
                        ]);
                    }
                } else {
                    // Single-payment flow: write a single Payment row when money is taken
                    if ($salePaid > 0) {
                        Payment::create([
                            'sale_id' => $sale->id,
                            'customer_id' => $customerId,
                            'amount' => $salePaid,
                            'payment_method' => $paymentMethod,
                            'payment_date' => now()->toDateString(),
                            'notes' => $request->input('notes'),
                        ]);
                    }
                }

                // Record card fee as expense when seller pays
                if (
                    $hasCard
                    && $cardFee > 0
                    && $cardFeeMode === 'seller'
                    && $cardFeeRecordExpense
                    && $cardFeeExpenseCategoryId > 0
                ) {
                    if (ExpenseCategory::whereKey($cardFeeExpenseCategoryId)->exists()) {
                        Expense::create([
                            'expense_category_id' => $cardFeeExpenseCategoryId,
                            'user_id' => $userId,
                            'expense_date' => now()->toDateString(),
                            'amount' => $cardFee,
                            'description' => 'Card fee for Sale ' . ($sale->sale_no ?? ('#' . $sale->id)),
                        ]);
                    }
                }

                foreach ($saleItemsData as $item) {
                    $qty = (float) ($item['qty'] ?? 0);
                    $lineTotal = array_key_exists('line_total', $item)
                        ? (float) $item['line_total']
                        : ($qty * (float) ($item['price'] ?? 0));
                    $effectiveUnitPrice = $qty > 0 ? round($lineTotal / $qty, 2) : (float) ($item['price'] ?? 0);

                    SaleItem::create([
                        'sale_id'    => $sale->id,
                        'product_id' => $item['id'],
                        'quantity'   => $item['qty'],
                        'unit_price' => $effectiveUnitPrice,
                        'total'      => round($lineTotal, 2),
                    ]);

                    // Reduce stock
                    $product = Product::find($item['id']);
                    if ($product) {
                        $multiplier = isset($item['unit_multiplier']) ? (int)$item['unit_multiplier'] : 1;
                        $decrementQty = $item['qty'] * max(1, $multiplier);
                        $product->decrement('stock_quantity', $decrementQty);
                        $product->refresh();
                        \App\Services\StockAlertService::check($product);
                    }
                }
                $createdSale = $sale;
            }

            DB::commit();
            Session::forget('pos.cart');
            
            return response()->json([
                'message' => 'Transaction completed successfully',
                'sale_id' => $createdSale ? $createdSale->id : null,
                'return_id' => $createdReturn ? $createdReturn->id : null,
                'sale' => $createdSale ? [
                    'id' => $createdSale->id,
                    'sale_no' => $createdSale->sale_no,
                    'total_amount' => $createdSale->total_amount,
                ] : null
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            return response()->json(['message' => 'Checkout failed: ' . $e->getMessage()], 500);
        }
    }

    /* ----------------------- Session Cart Helpers ----------------------- */
    private function cart(): array
    {
        $cart = Session::get('pos.cart');
        if (!$cart) {
            $cart = [
                'items' => [], // keyed by product id
                'discount' => ['type' => 'fixed', 'value' => 0],
                'tax_rate' => 0,
            ];
            Session::put('pos.cart', $cart);
        }
        return $this->withTotals($cart);
    }

    private function withTotals(array $cart): array
    {
        $subtotal = 0.0;
        $lineDiscountTotal = 0.0;

        foreach ($cart['items'] as $key => $it) {
            $qty = (float) ($it['qty'] ?? 0);
            $price = (float) ($it['price'] ?? 0);
            $lineSubtotal = $qty * $price;
            $lineDiscount = 0.0;

            // No discounts on return lines (negative qty)
            if ($qty > 0 && !empty($it['line_discount']) && is_array($it['line_discount'])) {
                $type = ($it['line_discount']['type'] ?? 'fixed') === 'percent' ? 'percent' : 'fixed';
                $value = (float) ($it['line_discount']['value'] ?? 0);
                $value = max(0, $value);

                if ($type === 'percent') {
                    $value = min(100, $value);
                    $lineDiscount = round($lineSubtotal * ($value / 100), 2);
                } else {
                    $lineDiscount = round($value, 2);
                }
                $lineDiscount = min($lineDiscount, max(0, $lineSubtotal));
            }

            $lineTotal = $lineSubtotal - $lineDiscount;
            $subtotal += $lineSubtotal;
            $lineDiscountTotal += $lineDiscount;

            // Add computed fields for frontend display
            $cart['items'][$key]['line_subtotal'] = round($lineSubtotal, 2);
            $cart['items'][$key]['line_discount_amount'] = round($lineDiscount, 2);
            $cart['items'][$key]['line_total'] = round($lineTotal, 2);
        }

        $baseForCartDiscount = $subtotal - $lineDiscountTotal;
        $cartDiscount = 0.0;
        if ($baseForCartDiscount > 0) {
            if (($cart['discount']['type'] ?? 'fixed') === 'percent') {
                $cartDiscount = round($baseForCartDiscount * ((float) ($cart['discount']['value'] ?? 0)) / 100, 2);
            } else {
                $cartDiscount = (float) ($cart['discount']['value'] ?? 0);
            }
            $cartDiscount = min(max(0, $cartDiscount), $baseForCartDiscount);
        }

        $discountAmount = $lineDiscountTotal + $cartDiscount;
        $taxAmount = round(($subtotal - $discountAmount) * ((float)($cart['tax_rate'] ?? 0)) / 100, 2);
        $total = $subtotal - $discountAmount + $taxAmount;
        $cart['totals'] = [
            'subtotal' => round($subtotal, 2),
            'discount_amount' => round($discountAmount, 2),
            'tax_amount' => round($taxAmount, 2),
            'total' => round($total, 2),
        ];
        return $cart;
    }

    private function putCart(array $cart): array
    {
        Session::put('pos.cart', $cart);
        return $this->withTotals($cart);
    }

    private function getHoldStorage(): array
    {
        return Session::get('pos.holds', []);
    }

    private function saveHoldStorage(array $holds): void
    {
        Session::put('pos.holds', $holds);
    }

    private function formatHoldPreview(array $hold): array
    {
        $items = $hold['cart']['items'] ?? [];
        $totals = $hold['cart']['totals'] ?? ['total' => 0];
        return [
            'id' => $hold['id'],
            'label' => $hold['label'] ?? 'Hold',
            'created_at' => $hold['created_at'] ?? now()->toDateTimeString(),
            'total' => $totals['total'] ?? 0,
            'item_count' => count($items),
            'cart' => $hold['cart'],
        ];
    }

    /* ----------------------- Cart Endpoints ----------------------- */
    public function addToCart(Request $request)
    {
        $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'nullable|integer|min:1',
            'unit' => 'nullable|array',
            'unit.id' => 'required_with:unit|integer',
            'unit.name' => 'required_with:unit|string'
        ]);
        
        $product = Product::findOrFail($request->integer('product_id'));
        $quantity = $request->integer('quantity', 1);
        $unit = $request->input('unit');

        // Determine available units for product
        $visibleUnits = collect($product->visible_units ?? [])->filter()->values();
        $basePrice = (float)$product->selling_price; // price per base unit
        $selectedUnitId = null;
        $selectedUnitName = null;
        $selectedMultiplier = 1;

        if ($unit) {
            $selectedUnitId = (int)$unit['id'];
            $selectedUnit = \App\Models\Unit::find($selectedUnitId);
            if ($selectedUnit) {
                $selectedUnitName = $selectedUnit->short_name ?? $selectedUnit->name;
                $selectedMultiplier = (float)$selectedUnit->base_unit_multiplier;
            }
        } elseif ($visibleUnits->count() > 0) {
            // Auto-select default unit if none provided:
            // Prefer base unit (multiplier == 1) if present, else first visible unit.
            $candidateUnits = \App\Models\Unit::whereIn('id', $visibleUnits->all())->get(['id', 'name', 'short_name', 'base_unit_multiplier']);
            $default = $candidateUnits->firstWhere('base_unit_multiplier', 1) ?? $candidateUnits->first();
            $selectedUnitId = $default ? (int)$default->id : (int)$visibleUnits->first();
            $selectedUnit = $default ?: \App\Models\Unit::find($selectedUnitId);
            if ($selectedUnit) {
                $selectedUnitName = $selectedUnit->short_name ?? $selectedUnit->name;
                $selectedMultiplier = (float)$selectedUnit->base_unit_multiplier;
            }
        }

        $finalPrice = round($basePrice * $selectedMultiplier, 2);
        
        $cart = $this->cart();
        $items = $cart['items'];
        // Unique key includes unit if selected
        $cartKey = $selectedUnitId ? ($product->id . '_unit_' . $selectedUnitId) : (string)$product->id;
        
        if (isset($items[$cartKey])) {
            $items[$cartKey]['qty'] += $quantity;
        } else {
            $itemName = $product->name;
            if ($selectedUnitName) {
                $itemName .= ' (' . $selectedUnitName . ')';
            }
            
            $items[$cartKey] = [
                'id' => $product->id,
                'cart_key' => $cartKey,
                'name' => $itemName,
                'price' => $finalPrice,
                'qty' => $quantity,
                'base_price' => $basePrice,
                'stock_quantity' => (int)($product->stock_quantity ?? 0),
                'unit_id' => $selectedUnitId,
                'unit_name' => $selectedUnitName,
                'unit_multiplier' => $selectedMultiplier,
                'visible_units' => $visibleUnits->toArray(),
            ];
        }
        
        $cart['items'] = $items;
        $cart = $this->putCart($cart);
        return response()->json($cart);
    }

    /**
     * Change the unit of an existing cart item (and adjust price, merge if needed).
     */
    public function setItemUnit(Request $request)
    {
        $data = $request->validate([
            'cart_key' => 'required|string',
            'unit_id' => 'required|integer|exists:units,id'
        ]);
        $cart = $this->cart();
        $oldKey = $data['cart_key'];
        if (!isset($cart['items'][$oldKey])) {
            return response()->json(['message' => 'Item not found'], 404);
        }
        $item = $cart['items'][$oldKey];

        // Parse product id from cart key (format productId[_unit_unitId])
        $productId = $item['id'];
        $product = Product::find($productId);
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        $unit = \App\Models\Unit::find($data['unit_id']);
        if (!$unit) {
            return response()->json(['message' => 'Unit not found'], 404);
        }

        // Build new key & compute price
        $newKey = $product->id . '_unit_' . $unit->id;
        $multiplier = (float)$unit->base_unit_multiplier;
        $newPrice = round(((float)$item['base_price']) * $multiplier, 2);
        $unitName = $unit->short_name ?? $unit->name;

        // If new key exists, merge quantities
        if (isset($cart['items'][$newKey])) {
            $cart['items'][$newKey]['qty'] += $item['qty'];
            unset($cart['items'][$oldKey]);
        } else {
            // Update item data and move under new key
            $item['cart_key'] = $newKey;
            $item['unit_id'] = $unit->id;
            $item['unit_name'] = $unitName;
            $item['unit_multiplier'] = $multiplier;
            $item['price'] = $newPrice;
            $item['name'] = $product->name . ' (' . $unitName . ')';
            unset($cart['items'][$oldKey]);
            $cart['items'][$newKey] = $item;
        }

        $cart = $this->putCart($cart);
        return response()->json($cart);
    }

    public function updateQty(Request $request)
    {
        $data = $request->validate([
            'cart_key' => 'required|string',
            'qty' => 'required|integer|min:1'
        ]);
        $cart = $this->cart();
        if (!isset($cart['items'][$data['cart_key']])) {
            return response()->json(['message' => 'Item not found'], 404);
        }
        $cart['items'][$data['cart_key']]['qty'] = (int)$data['qty'];
        $cart = $this->putCart($cart);
        return response()->json($cart);
    }

    /**
     * Update per-item details (unit price override, line discount, description).
     */
    public function updateCartItem(Request $request)
    {
        $data = $request->validate([
            'cart_key' => 'required|string',
            'unit_price' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|in:fixed,percent',
            'discount_value' => 'nullable|numeric|min:0',
            'description' => 'nullable|string|max:5000',
        ]);

        $cart = Session::get('pos.cart');
        if (!$cart || empty($cart['items'][$data['cart_key']])) {
            return response()->json(['message' => 'Item not found'], 404);
        }

        $item = $cart['items'][$data['cart_key']];
        if (($item['qty'] ?? 0) < 0) {
            return response()->json(['message' => 'Cannot edit return items'], 422);
        }

        if (array_key_exists('unit_price', $data) && $data['unit_price'] !== null) {
            $item['price'] = round((float) $data['unit_price'], 2);
        }

        if (($data['discount_type'] ?? null) !== null || ($data['discount_value'] ?? null) !== null) {
            $type = ($data['discount_type'] ?? ($item['line_discount']['type'] ?? 'fixed'));
            $type = $type === 'percent' ? 'percent' : 'fixed';
            $value = (float) ($data['discount_value'] ?? ($item['line_discount']['value'] ?? 0));
            $value = max(0, $value);
            if ($type === 'percent') {
                $value = min(100, $value);
            }
            $item['line_discount'] = ['type' => $type, 'value' => $value];
        }

        if (array_key_exists('description', $data)) {
            $item['description'] = (string) ($data['description'] ?? '');
        }

        $cart['items'][$data['cart_key']] = $item;
        $cart = $this->putCart($cart);
        return response()->json($cart);
    }

    public function removeItem(Request $request)
    {
        $request->validate(['cart_key' => 'required|string']);
        $cart = $this->cart();
        unset($cart['items'][$request->input('cart_key')]);
        $cart = $this->putCart($cart);
        return response()->json($cart);
    }

    public function clearCart()
    {
        Session::forget('pos.cart');
        return response()->json($this->cart());
    }

    public function setDiscount(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:percent,fixed',
            'value' => 'required|numeric|min:0'
        ]);
        $cart = $this->cart();
        $cart['discount'] = $data;
        $cart = $this->putCart($cart);
        return response()->json($cart);
    }

    public function holdCart(Request $request)
    {
        $cart = Session::get('pos.cart');
        if (empty($cart['items'] ?? [])) {
            return response()->json(['message' => 'Cart is empty'], 422);
        }

        $label = trim($request->input('label') ?? '');
        $holds = $this->getHoldStorage();
        $holdId = Str::uuid()->toString();
        $holds[$holdId] = [
            'id' => $holdId,
            'label' => $label ?: 'Hold ' . now()->format('Y-m-d H:i'),
            'created_at' => now()->toDateTimeString(),
            'cart' => $cart,
        ];
        $this->saveHoldStorage($holds);
        Session::forget('pos.cart');
        return response()->json([ 'message' => 'Bill held', 'cart' => $this->cart() ]);
    }

    public function listHolds()
    {
        $holds = array_values(array_map(fn($hold) => $this->formatHoldPreview($hold), $this->getHoldStorage()));
        return response()->json($holds);
    }

    public function loadHold(Request $request)
    {
        $holdId = $request->input('hold_id');
        $holds = $this->getHoldStorage();
        if (!isset($holds[$holdId])) {
            return response()->json(['message' => 'Hold not found'], 404);
        }
        Session::put('pos.cart', $holds[$holdId]['cart']);
        unset($holds[$holdId]);
        $this->saveHoldStorage($holds);
        return response()->json(['message' => 'Hold loaded', 'cart' => $this->cart()]);
    }

    public function removeHold(Request $request)
    {
        $holdId = $request->input('hold_id');
        $holds = $this->getHoldStorage();
        if (isset($holds[$holdId])) {
            unset($holds[$holdId]);
            $this->saveHoldStorage($holds);
        }
        return response()->json(['message' => 'Hold deleted']);
    }

    public function saveDraft(Request $request)
    {
        $cart = $this->cart();
        if (empty($cart['items'])) {
            return response()->json(['message' => 'Cart is empty'], 422);
        }
        DB::beginTransaction();
        try {
            $sale = Sale::create([
                'customer_id'   => $request->integer('customer_id') ?: null,
                'user_id'       => Auth::id(),
                'sale_date'     => now(),
                'subtotal'      => $cart['totals']['subtotal'],
                'tax'           => $cart['totals']['tax_amount'],
                'discount'      => $cart['totals']['discount_amount'],
                'total_amount'  => $cart['totals']['total'],
                // Quotations shouldn't record payment fields
                'paid_amount'   => 0,
                'due_amount'    => 0,
                'payment_status'=> 'unpaid',
                'payment_method'=> 'cash',
                'sale_type'     => 'quotation',
                'notes'         => $request->string('notes')
            ]);

            foreach ($cart['items'] as $item) {
                $qty = (float) ($item['qty'] ?? 0);
                $lineTotal = array_key_exists('line_total', $item)
                    ? (float) $item['line_total']
                    : ($qty * (float) ($item['price'] ?? 0));
                $effectiveUnitPrice = $qty != 0 ? round($lineTotal / $qty, 2) : (float) ($item['price'] ?? 0);

                SaleItem::create([
                    'sale_id'    => $sale->id,
                    'product_id' => $item['id'],
                    'quantity'   => $item['qty'],
                    'unit_price' => $effectiveUnitPrice,
                    'total'      => round($lineTotal, 2),
                ]);
            }

            DB::commit();
            return response()->json(['message' => 'Draft saved', 'sale_id' => $sale->id, 'sale_no' => $sale->sale_no]);
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            return response()->json(['message' => 'Failed to save draft', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get shop details for receipt
     */
    public function getShopDetails()
    {
        return response()->json([
            'shop_name' => Setting::get('shop_name', 'Vehicle POS System'),
            'shop_address' => Setting::get('shop_address', ''),
            'shop_phone' => Setting::get('shop_phone', ''),
            'shop_email' => Setting::get('shop_email', ''),
        ]);
    }

    /**
     * Get sale receipt data
     */
    public function getSaleReceipt($id)
    {
        $sale = Sale::with(['items.product', 'customer', 'user', 'payments'])->findOrFail($id);
        $tenderedAmount = (float) $sale->payments->sum('amount');
        if ($tenderedAmount <= 0) {
            $tenderedAmount = (float) $sale->paid_amount;
        }
        
        return response()->json([
            'id' => $sale->id,
            'sale_no' => $sale->sale_no,
            'sale_date' => $sale->sale_date,
            'subtotal' => $sale->subtotal,
            'tax' => $sale->tax,
            'discount' => $sale->discount,
            'total_amount' => $sale->total_amount,
            'paid_amount' => $sale->paid_amount,
            'tendered_amount' => $tenderedAmount,
            'due_amount' => $sale->due_amount,
            'payment_status' => $sale->payment_status,
            'payment_method' => $sale->payment_method,
            'customer_name' => $sale->customer?->name,
            'cashier_name' => $sale->user?->name,
            'payments' => $sale->payments->map(function ($p) {
                return [
                    'method' => $p->payment_method,
                    'amount' => $p->amount,
                    'date' => $p->payment_date,
                    'notes' => $p->notes,
                ];
            })->values(),
            'items' => $sale->items->map(function ($item) {
                return [
                    'product_name' => $item->product->name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total' => $item->total,
                ];
            }),
        ]);
    }

    /**
     * Get current outstanding due balance for a customer
     */
    public function getCustomerDue($id)
    {
        $due = Sale::where('customer_id', $id)
            ->where('sale_type', 'sale')
            ->where('due_amount', '>', 0)
            ->sum('due_amount');
        return response()->json(['customer_id' => (int)$id, 'outstanding_due' => (float)$due]);
    }
}
