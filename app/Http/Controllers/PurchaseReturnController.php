<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\Product;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Support\SecretPos;

class PurchaseReturnController extends Controller
{
    public function index()
    {
        $returns = PurchaseReturn::with(['purchase.supplier', 'user'])
            ->whereHas('purchase', fn ($q) => SecretPos::excludeHiddenPurchaseRanges($q, 'total_amount'))
            ->latest()
            ->paginate(10);
        return view('purchase_returns.index', compact('returns'));
    }

    public function create(Request $request)
    {
        $purchase = null;
        $purchases = null;
        $suppliers = \App\Models\Supplier::orderBy('name')->get();

        if ($request->filled('purchase_id') || $request->filled('supplier_id') || $request->filled('date')) {
            $query = Purchase::with(['items.product', 'supplier']);
            $query = SecretPos::excludeHiddenPurchaseRanges($query, 'total_amount');

            if ($request->filled('purchase_id')) {
                $term = $request->purchase_id;
                $query->where(function($q) use ($term) {
                    $q->where('id', $term)
                      ->orWhere('purchase_no', 'LIKE', "%{$term}%");
                });
            }

            if ($request->filled('supplier_id')) {
                $query->where('supplier_id', $request->supplier_id);
            }

            if ($request->filled('date')) {
                $query->whereDate('purchase_date', $request->date);
            }

            $results = $query->latest()->get();

            if ($results->count() === 1) {
                $purchase = $results->first();
            } elseif ($results->count() > 1) {
                $purchases = $results;
            } else {
                session()->flash('error', 'No purchase found matching the criteria.');
            }
        }

        return view('purchase_returns.create', compact('purchase', 'purchases', 'suppliers'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'purchase_id' => 'required|exists:purchases,id',
            'items' => 'required|array',
            'items.*.id' => 'required|exists:purchase_items,id',
            'items.*.quantity' => 'required|integer|min:0',
            'refund_method' => 'required|in:cash,account',
        ]);

        $purchase = Purchase::with('items')->findOrFail($request->purchase_id);
        if (SecretPos::isPurchaseHidden((float) $purchase->total_amount)) {
            abort(404);
        }
        $totalRefund = 0;

        DB::beginTransaction();

        try {
            // Create Return Record
            $purchaseReturn = PurchaseReturn::create([
                'purchase_id' => $purchase->id,
                'user_id' => Auth::id(),
                'return_date' => now(),
                'total_refund' => 0,
                'notes' => $request->notes,
            ]);

            foreach ($request->items as $itemData) {
                if ($itemData['quantity'] > 0) {
                    $purchaseItem = $purchase->items->find($itemData['id']);
                    
                    if ($itemData['quantity'] > $purchaseItem->quantity) {
                        throw new \Exception("Return quantity cannot exceed purchased quantity for {$purchaseItem->product->name}");
                    }

                    $lineTotal = $purchaseItem->unit_cost * $itemData['quantity'];
                    $totalRefund += $lineTotal;

                    PurchaseReturnItem::create([
                        'purchase_return_id' => $purchaseReturn->id,
                        'purchase_item_id' => $purchaseItem->id,
                        'product_id' => $purchaseItem->product_id,
                        'quantity' => $itemData['quantity'],
                        'unit_price' => $purchaseItem->unit_cost,
                        'total' => $lineTotal,
                    ]);

                    // Destock Product (Decrement Stock)
                    // Wait, Purchase Return means we give back to supplier. So Stock DECREASES.
                    $product = Product::find($purchaseItem->product_id);
                    if ($product->stock_quantity < $itemData['quantity']) {
                         throw new \Exception("Insufficient stock to return {$itemData['quantity']} of {$product->name}");
                    }
                    $product->decrement('stock_quantity', $itemData['quantity']);
                }
            }

            if ($totalRefund == 0) {
                throw new \Exception("No items selected for return.");
            }

            $purchaseReturn->update(['total_refund' => $totalRefund]);

            // Handle Refund
            if ($request->refund_method === 'account') {
                // Deduct from Supplier Due (We owe them less)
                $purchase->decrement('due_amount', $totalRefund);
            }
            // If 'cash', we received cash back. No change to due amount.

            ActivityLog::log('return', "Created return for Purchase #{$purchase->id}", $purchaseReturn);

            DB::commit();

            return redirect()->route('purchase-returns.index')->with('success', 'Purchase return processed successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error processing return: ' . $e->getMessage());
        }
    }
}
