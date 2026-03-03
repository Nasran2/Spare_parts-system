<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\SaleItem;
use App\Models\Product;
use App\Models\Payment;
use App\Models\ActivityLog;
use App\Services\SaleRecalculationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class SaleReturnController extends Controller
{
    public function index()
    {
        $returns = SaleReturn::with(['sale.customer', 'user'])->latest()->paginate(10);
        return view('sale_returns.index', compact('returns'));
    }

    public function create(Request $request)
    {
        $sale = null;
        $sales = null;
        $customers = \App\Models\Customer::orderBy('name')->get();

        if ($request->filled('sale_id') || $request->filled('customer_id') || $request->filled('date')) {
            $query = Sale::with(['items.product', 'customer']);

            if ($request->filled('sale_id')) {
                $term = $request->sale_id;
                $query->where(function($q) use ($term) {
                    $q->where('id', $term)
                      ->orWhere('sale_no', 'LIKE', "%{$term}%");
                });
            }

            if ($request->filled('customer_id')) {
                $query->where('customer_id', $request->customer_id);
            }

            if ($request->filled('date')) {
                $query->whereDate('sale_date', $request->date);
            }

            $results = $query->latest()->get();

            if ($results->count() === 1) {
                $sale = $results->first();
            } elseif ($results->count() > 1) {
                $sales = $results;
            } else {
                session()->flash('error', 'No sale found matching the criteria.');
            }
        }

        return view('sale_returns.create', compact('sale', 'sales', 'customers'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'sale_id' => 'required|exists:sales,id',
            'items' => 'required|array',
            'items.*.id' => 'required|exists:sale_items,id',
            'items.*.quantity' => 'required|integer|min:0',
            'refund_method' => 'required|in:cash,account',
            'exchange_items' => 'nullable|array',
            'exchange_items.*.id' => 'required_with:exchange_items|exists:products,id',
            'exchange_items.*.quantity' => 'required_with:exchange_items|integer|min:1',
            'exchange_items.*.price' => 'required_with:exchange_items|numeric|min:0',
        ]);

    $sale = Sale::with(['items.product', 'payments'])->findOrFail($request->sale_id);
        $totalRefund = 0;
        $totalExchange = 0;

        DB::beginTransaction();

        try {
            // 1. Process Return Items
            $saleReturn = SaleReturn::create([
                'sale_id' => $sale->id,
                'user_id' => Auth::id(),
                'return_date' => now(),
                'subtotal' => 0,
                'total_refund' => 0, // Will update later
                'notes' => $request->notes,
            ]);

            foreach ($request->items as $itemData) {
                if ($itemData['quantity'] > 0) {
                    $saleItem = $sale->items->find($itemData['id']);
                    if (!$saleItem) {
                        continue;
                    }
                    
                    // Calculate already returned quantity for this item
                    $alreadyReturned = SaleReturnItem::where('sale_item_id', $saleItem->id)->sum('quantity');
                    $remainingQty = $saleItem->quantity - $alreadyReturned;

                    if ($itemData['quantity'] > $remainingQty) {
                        throw new \Exception("Return quantity cannot exceed remaining quantity ({$remainingQty}) for {$saleItem->product->name}");
                    }

                    $lineTotal = $saleItem->unit_price * $itemData['quantity'];
                    $totalRefund += $lineTotal;

                    SaleReturnItem::create([
                        'sale_return_id' => $saleReturn->id,
                        'sale_item_id' => $saleItem->id,
                        'product_id' => $saleItem->product_id,
                        'quantity' => $itemData['quantity'],
                        'unit_price' => $saleItem->unit_price,
                        'total' => $lineTotal,
                    ]);

                    // Restock Product
                    $product = Product::find($saleItem->product_id);
                    $product->increment('stock_quantity', $itemData['quantity']);
                }
            }

            if ($totalRefund == 0) {
                throw new \Exception("No items selected for return.");
            }

            $saleReturn->update([
                'subtotal' => round($totalRefund, 2),
                'total_refund' => round($totalRefund, 2),
            ]);

            // 2. Process Exchange Items (New Sale)
            $newSale = null;
            if ($request->has('exchange_items') && count($request->exchange_items) > 0) {
                foreach ($request->exchange_items as $exItem) {
                    $totalExchange += $exItem['quantity'] * $exItem['price'];
                }

                if ($totalExchange > 0) {
                    // Create a new Sale for the exchange items
                    $newSale = Sale::create([
                        'customer_id' => $sale->customer_id,
                        'user_id' => Auth::id(),
                        'sale_date' => now(),
                        'subtotal' => $totalExchange,
                        'tax' => 0,
                        'discount' => 0,
                        'total_amount' => $totalExchange,
                        'paid_amount' => 0, // Will adjust based on net calculation
                        'due_amount' => $totalExchange,
                        'payment_status' => 'unpaid',
                        'payment_method' => 'cash',
                        'notes' => 'Exchange for Return #' . $saleReturn->id,
                    ]);

                    foreach ($request->exchange_items as $exItem) {
                        $product = Product::find($exItem['id']);
                        if ($product->stock_quantity < $exItem['quantity']) {
                            throw new \Exception("Insufficient stock for exchange item: {$product->name}");
                        }

                        SaleItem::create([
                            'sale_id' => $newSale->id,
                            'product_id' => $product->id,
                            'quantity' => $exItem['quantity'],
                            'unit_price' => $exItem['price'],
                            'total' => $exItem['quantity'] * $exItem['price'],
                        ]);

                        $product->decrement('stock_quantity', $exItem['quantity']);
                    }
                }
            }

            // 3. Calculate Net Amount
            // Net > 0: Customer pays difference
            // Net < 0: Refund to customer
            $netAmount = $totalExchange - $totalRefund;

            if ($netAmount > 0) {
                // Customer needs to pay the difference
                // We can assume they pay it immediately or it goes to due
                // For this flow, let's assume cash payment for the difference if possible, or add to due
                
                // The new sale has due_amount = totalExchange.
                // We apply the return value ($totalRefund) as a payment towards the new sale.
                
                Payment::create([
                    'sale_id' => $newSale->id,
                    'customer_id' => $newSale->customer_id,
                    'amount' => $totalRefund,
                    'payment_date' => now()->toDateString(),
                    'payment_method' => 'return_adjustment', // Internal method
                    'notes' => 'Credit from Return #' . $saleReturn->id,
                ]);

                $newSale->paid_amount += $totalRefund;
                $newSale->due_amount -= $totalRefund;
                $newSale->payment_status = $newSale->due_amount > 0 ? 'partial' : 'paid';
                $newSale->save();

                // The remaining due on newSale is ($totalExchange - $totalRefund) which is $netAmount.
                // If the user provided cash for the difference (we could add a field for this, but for now let's leave it as due or assume paid if we want)
                // The prompt says "customer chnage thje procuts... need to give 500 as cash or if customer have any due we can minus that"
                // Let's assume the difference is paid in Cash if the user wants, but we don't have a field for "Amount Tendered" here yet.
                // We will leave it as Due on the new Sale, or we can auto-pay it if we assume cash.
                // Let's auto-pay the difference as Cash for now to close the transaction, or leave it as due.
                // Given "need to give 500 as cash", implies handling the money.
                // Let's add a payment for the difference.
                
                Payment::create([
                    'sale_id' => $newSale->id,
                    'customer_id' => $newSale->customer_id,
                    'amount' => $netAmount,
                    'payment_date' => now()->toDateString(),
                    'payment_method' => 'cash',
                    'notes' => 'Cash payment for exchange difference',
                ]);
                
                $newSale->paid_amount += $netAmount;
                $newSale->due_amount -= $netAmount;
                $newSale->payment_status = $newSale->due_amount > 0 ? 'partial' : 'paid';
                $newSale->save();

            } elseif ($netAmount < 0) {
                // Refund Amount = abs($netAmount)
                // We have a surplus from the return after covering the exchange items.
                $refundAmount = abs($netAmount);

                // If there was a new sale, it is fully paid by the return.
                if ($newSale) {
                    Payment::create([
                        'sale_id' => $newSale->id,
                        'customer_id' => $newSale->customer_id,
                        'amount' => $totalExchange,
                        'payment_date' => now()->toDateString(),
                        'payment_method' => 'return_adjustment',
                        'notes' => 'Credit from Return #' . $saleReturn->id,
                    ]);
                    
                    $newSale->paid_amount = $totalExchange;
                    $newSale->due_amount = 0;
                    $newSale->payment_status = 'paid';
                    $newSale->save();
                }

                // Handle the remaining refund ($refundAmount)
                if ($request->refund_method === 'cash') {
                    // Refund cash to customer
                    // We can record this as a negative payment on the ORIGINAL sale or just a log.
                    // Let's record on Original Sale to keep track of money out.
                    Payment::create([
                        'sale_id' => $sale->id,
                        'customer_id' => $sale->customer_id,
                        'amount' => -$refundAmount,
                        'payment_date' => now()->toDateString(),
                        'payment_method' => 'cash',
                        'notes' => 'Cash Refund for Return #' . $saleReturn->id . ' (Net after exchange)',
                    ]);

                } elseif ($request->refund_method === 'account') {
                    // Account / Store credit: record as a negative payment for correct net accounting.
                    Payment::create([
                        'sale_id' => $sale->id,
                        'customer_id' => $sale->customer_id,
                        'amount' => -$refundAmount,
                        'payment_date' => now()->toDateString(),
                        'payment_method' => 'account_credit',
                        'notes' => 'Account Credit for Return #' . $saleReturn->id . ' (Net after exchange)',
                    ]);
                }
            } else {
                // Net is 0. Perfect exchange.
                if ($newSale) {
                    Payment::create([
                        'sale_id' => $newSale->id,
                        'customer_id' => $newSale->customer_id,
                        'amount' => $totalExchange,
                        'payment_date' => now()->toDateString(),
                        'payment_method' => 'return_adjustment',
                        'notes' => 'Credit from Return #' . $saleReturn->id,
                    ]);
                    
                    $newSale->paid_amount = $totalExchange;
                    $newSale->due_amount = 0;
                    $newSale->payment_status = 'paid';
                    $newSale->save();
                }
            }

            // Always recalculate the original sale totals after returns so every report/dashboard reflects it.
            $sale->refresh();
            $sale->load(['items', 'payments']);
            SaleRecalculationService::recalculateSaleFinancials($sale);

            ActivityLog::log('return', "Created return for Sale #{$sale->id} with Exchange", $saleReturn);

            DB::commit();

            return redirect()->route('sale-returns.index')->with('success', 'Return and Exchange processed successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error processing return: ' . $e->getMessage());
        }
    }

    public function print($id)
    {
        $return = SaleReturn::with(['sale.customer', 'items.product', 'user'])->findOrFail($id);

        $invoicePaperSize = \App\Models\Setting::get('invoice_paper_size', 'a4');
        $paperSize = strtolower((string) ($invoicePaperSize ?? 'a4'));
        $invoiceShowLogo = (bool) \App\Models\Setting::get('invoice_show_logo', true);
        $invoiceFooterText = (string) \App\Models\Setting::get('invoice_footer_text', 'Thank you for your business!');
        $invoiceTerms = (string) \App\Models\Setting::get('invoice_terms', '');

        $shop = [
            'name' => \App\Models\Setting::get('shop_name') ?? \App\Models\Setting::get('business_name') ?? config('app.name'),
            'address' => \App\Models\Setting::get('shop_address') ?? \App\Models\Setting::get('business_address') ?? '',
            'phone' => \App\Models\Setting::get('shop_phone') ?? \App\Models\Setting::get('business_phone') ?? '',
            'email' => \App\Models\Setting::get('shop_email') ?? \App\Models\Setting::get('business_email') ?? '',
            'logo' => \App\Models\Setting::get('shop_logo') ?? null,
        ];
        $logoValue = $shop['logo'] ?? null;
        $logoSrc = null;
        if (!empty($logoValue)) {
            if (preg_match('#^https?://#i', (string) $logoValue) || str_starts_with((string) $logoValue, '/')) {
                $logoSrc = (string) $logoValue;
            } else {
                if (is_file(public_path((string) $logoValue))) {
                    $logoSrc = asset((string) $logoValue);
                } elseif (is_file(public_path('storage/' . (string) $logoValue))) {
                    $logoSrc = asset('storage/' . (string) $logoValue);
                } else {
                    $logoSrc = asset((string) $logoValue);
                }
            }
        }
        $currency = config('app.currency', 'Rs ');

        return view('sale_returns.print', compact(
            'return',
            'shop',
            'paperSize',
            'logoSrc',
            'currency',
            'invoicePaperSize',
            'invoiceShowLogo',
            'invoiceFooterText',
            'invoiceTerms'
        ));
    }
}
