<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sale;
use App\Models\Customer;
use App\Models\Setting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Support\SecretPos;

class SaleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Sale::with(['customer', 'user'])->orderByDesc('created_at');

        // Filters: date range, customer, status
        if ($request->filled('date_from')) {
            $query->whereDate('sale_date', '>=', $request->date('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('sale_date', '<=', $request->date('date_to'));
        }
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->integer('customer_id'));
        }
        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->string('payment_status'));
        }

        // Exclude bills whose totals fall into hidden ranges
        $query = SecretPos::excludeHiddenRanges($query, 'total_amount');

        $sales = $query->paginate(20)->appends($request->query());
        $customers = Customer::orderBy('name')->get(['id','name']);

        $currency = config('app.currency', 'Rs ');
        return view('sales.index', [
            'sales' => $sales,
            'customers' => $customers,
            'filters' => $request->only(['date_from','date_to','customer_id','payment_status']),
            'currency' => $currency,
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
        $sale = Sale::with(['items.product', 'customer', 'user'])->findOrFail($id);
        // Block access to hidden bills entirely
        if (SecretPos::isHidden((float)$sale->total_amount)) {
            abort(404);
        }
        $currency = config('app.currency', 'Rs ');
        return view('sales.show', compact('sale','currency'));
    }

    /**
     * Printable receipt view for a sale.
     */
    public function print(string $id)
    {
        $sale = Sale::with(['items.product', 'customer', 'user', 'payments'])->findOrFail($id);
        if (SecretPos::isHidden((float)$sale->total_amount)) {
            abort(404);
        }
        $shop = [
            'name' => Setting::get('shop_name') ?? Setting::get('business_name') ?? config('app.name'),
            'address' => Setting::get('shop_address') ?? Setting::get('business_address') ?? '',
            'phone' => Setting::get('shop_phone') ?? Setting::get('business_phone') ?? '',
            'email' => Setting::get('shop_email') ?? Setting::get('business_email') ?? '',
            'logo' => Setting::get('shop_logo') ?? null,
        ];
        $currency = config('app.currency', 'Rs ');
        return view('sales.print', compact('sale', 'shop','currency'));
    }

    /**
     * Create a return for a sale
     */
    public function returnSale(Request $request, string $id)
    {
        $sale = Sale::with(['items.product'])->findOrFail($id);
        $data = $request->validate([
            'items' => 'required|array',
            'items.*.sale_item_id' => 'required|integer|exists:sale_items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Map sale items for quick lookup and ensure not exceeding sold qty
        $itemMap = $sale->items->keyBy('id');

        $subtotal = 0;

    \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            $return = new \App\Models\SaleReturn();
            $return->sale_id = $sale->id;
            $return->user_id = Auth::id();
            $return->return_date = now();
            $return->notes = $data['notes'] ?? null;
            $return->subtotal = 0;
            $return->total_refund = 0;
            $return->save();

            foreach ($data['items'] as $ritem) {
                $saleItem = $itemMap->get($ritem['sale_item_id']);
                if (!$saleItem) { continue; }
                $qty = min((int)$ritem['quantity'], (int)$saleItem->quantity);
                $unit = (float)$saleItem->unit_price;
                $line = $qty * $unit;
                $subtotal += $line;

                \App\Models\SaleReturnItem::create([
                    'sale_return_id' => $return->id,
                    'sale_item_id' => $saleItem->id,
                    'product_id' => $saleItem->product_id,
                    'quantity' => $qty,
                    'unit_price' => $unit,
                    'total' => $line,
                ]);

                // Restock
                \App\Models\Product::where('id', $saleItem->product_id)->increment('stock_quantity', $qty);
            }

            $return->subtotal = round($subtotal, 2);
            $return->total_refund = round($subtotal, 2); // simple: refund subtotal; taxes/discounts ignored for now
            $return->save();

            // Optionally adjust sale due/paid; keeping as-is for simplicity.

            \Illuminate\Support\Facades\DB::commit();
            return redirect()->route('sales.show', $sale->id)->with('success', 'Return processed successfully');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            report($e);
            return redirect()->route('sales.show', $sale->id)->with('error', 'Return failed: '.$e->getMessage());
        }
    }

    /**
     * Export filtered sales as CSV
     */
    public function exportCsv(Request $request)
    {
        $query = $this->filteredQuery($request);
        $filename = 'sales_'.now()->format('Ymd_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

            $callback = function() use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Invoice No', 'Date', 'Customer', 'Amount', 'Paid', 'Due', 'Pay Status', 'Pay Method']);
            $query->chunk(500, function($rows) use ($out) {
                foreach ($rows as $sale) {
                    fputcsv($out, [
                        $sale->sale_no,
                        optional($sale->sale_date)->format('Y-m-d') ?? $sale->created_at->format('Y-m-d'),
                        $sale->customer->name ?? 'Walk-in Customer',
                            number_format((float)$sale->total_amount, 2, '.', ''),
                            number_format((float)$sale->paid_amount, 2, '.', ''),
                            number_format((float)$sale->due_amount, 2, '.', ''),
                        $sale->payment_status,
                        $sale->payment_method,
                    ]);
                }
            });
            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export filtered sales as PDF using a Blade template
     */
    public function exportPdf(Request $request)
    {
        $sales = $this->filteredQuery($request)->get();
        $shop = [
            'name' => Setting::get('shop_name', config('app.name')),
            'address' => Setting::get('shop_address', ''),
            'phone' => Setting::get('shop_phone', ''),
            'email' => Setting::get('shop_email', ''),
            'logo' => Setting::get('shop_logo') ?? null,
        ];

        // Lazy dependency note: requires barryvdh/laravel-dompdf
        if (!app()->bound('dompdf.wrapper')) {
            return back()->with('error', 'PDF export library not installed');
        }
        $pdf = app('dompdf.wrapper');
        $pdf->loadView('sales.export_pdf', compact('sales', 'shop'));
        $pdf->setPaper('A4', 'portrait');
        return $pdf->download('sales_'.now()->format('Ymd_His').'.pdf');
    }

    /**
     * Download a Quotation PDF (separate from thermal receipt)
     */
    public function quotationPdf(string $id)
    {
        $sale = Sale::with(['items.product','customer','user'])->where('sale_type','quotation')->findOrFail($id);
        $shop = [
            'name' => Setting::get('shop_name', config('app.name')),
            'address' => Setting::get('shop_address', ''),
            'phone' => Setting::get('shop_phone', ''),
            'email' => Setting::get('shop_email', ''),
            'logo' => Setting::get('shop_logo') ?? null,
            'valid_days' => (int) Setting::get('quotation_valid_days', 30),
            'terms' => (string) Setting::get('quotation_terms', 'Prices are subject to change without prior notice. Payment terms as agreed.'),
        ];

        if (!app()->bound('dompdf.wrapper')) {
            abort(500, 'PDF export library not installed');
        }
        $pdf = app('dompdf.wrapper');
        $currency = config('app.currency', 'Rs ');
        $pdf->loadView('quotations.pdf', compact('sale','shop','currency'));
        $pdf->setPaper('A4', 'portrait');
        // Stream inline (do not force download) so it can be previewed/printed in-browser.
        return $pdf->stream($sale->sale_no . '.pdf', ['Attachment' => false]);
    }

    /**
     * Convert a quotation to a sale (one-click)
     */
    public function convertQuotation(string $id)
    {
        $sale = Sale::with(['items'])->findOrFail($id);
        if ($sale->sale_type !== 'quotation') {
            return redirect()->route('sales.show', $sale->id)->with('error', 'Only quotations can be converted.');
        }

        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            // Update sale as invoice/sale
            $sale->sale_type = 'sale';
            $sale->sale_no = Sale::generateNumber('sale');
            $sale->sale_date = now();
            $sale->payment_status = 'unpaid';
            $sale->paid_amount = 0;
            $sale->due_amount = $sale->total_amount;
            $sale->payment_method = 'cash';
            $sale->save();

            // Reduce stock for each item
            foreach ($sale->items as $it) {
                $p = \App\Models\Product::find($it->product_id);
                if ($p) {
                    $p->decrement('stock_quantity', $it->quantity);
                    $p->refresh();
                    \App\Services\StockAlertService::check($p);
                }
            }

            \Illuminate\Support\Facades\DB::commit();
            return redirect()->route('sales.print', $sale->id);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            report($e);
            return redirect()->route('quotations.index')->with('error', 'Failed to convert: '.$e->getMessage());
        }
    }

    /**
     * Build filtered query for reuse (index + exports)
     */
    private function filteredQuery(Request $request)
    {
        $query = Sale::with(['customer','user'])->orderByDesc('created_at');
        if ($request->filled('date_from')) {
            $query->whereDate('sale_date', '>=', $request->date('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('sale_date', '<=', $request->date('date_to'));
        }
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->integer('customer_id'));
        }
        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->string('payment_status'));
        }
        if ($request->has('sale_type') && $request->string('sale_type') !== '') {
            $query->where('sale_type', (string)$request->string('sale_type'));
        }
        // Exclude hidden-range bills from any listing/export
        return SecretPos::excludeHiddenRanges($query, 'total_amount');
    }

    /**
     * List quotations (sale_type=quotation)
     */
    public function quotations(Request $request)
    {
        $request->merge(['sale_type' => 'quotation']);
        $query = $this->filteredQuery($request);
        $quotations = $query->paginate(20)->appends($request->query());
        $customers = Customer::orderBy('name')->get(['id','name']);

        return view('quotations.index', [
            'sales' => $quotations,
            'customers' => $customers,
            'filters' => $request->only(['date_from','date_to','customer_id','payment_status','sale_type']),
            'currency' => Setting::get('currency_symbol', '$')
        ]);
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
        if (!Auth::user()?->hasPermission('sales.delete')) {
            return redirect()->back()->with('error', 'You do not have permission to delete sales.');
        }

        $sale = Sale::findOrFail($id);

        try {
            DB::transaction(function () use ($sale) {
                if ($sale->sale_type === 'sale') {
                    $sale->loadMissing(['items', 'returns.items']);

                    $returnedByProduct = [];
                    foreach ($sale->returns as $return) {
                        foreach ($return->items as $returnItem) {
                            $productId = (int) $returnItem->product_id;
                            $returnedByProduct[$productId] = ($returnedByProduct[$productId] ?? 0) + (int) $returnItem->quantity;
                        }
                    }

                    foreach ($sale->items as $item) {
                        \App\Models\Product::where('id', $item->product_id)->increment('stock_quantity', (int) $item->quantity);
                    }

                    foreach ($returnedByProduct as $productId => $qty) {
                        \App\Models\Product::where('id', $productId)->decrement('stock_quantity', (int) $qty);
                    }
                }

                $sale->delete();
            });

            if ($sale->sale_type === 'quotation') {
                return redirect()->route('quotations.index')->with('success', 'Quotation deleted successfully');
            }

            return redirect()->route('sales.index')->with('success', 'Sale deleted successfully');
        } catch (\Throwable $e) {
            report($e);
            if ($sale->sale_type === 'quotation') {
                return redirect()->route('quotations.index')->with('error', 'Failed to delete quotation.');
            }

            return redirect()->route('sales.index')->with('error', 'Failed to delete sale.');
        }
    }

    /**
     * Add payment to a sale
     */
    public function addPayment(Request $request)
    {
        $validated = $request->validate([
            'sale_id' => 'required|exists:sales,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string',
            'payment_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $sale = Sale::findOrFail($validated['sale_id']);

        // Validate payment amount doesn't exceed due amount
        if ($validated['amount'] > $sale->due_amount) {
            return back()->with('error', 'Payment amount cannot exceed due amount of Rs ' . number_format((float)$sale->due_amount, 2));
        }

        // Create payment record
        \App\Models\Payment::create([
            'sale_id' => $sale->id,
            'customer_id' => $sale->customer_id,
            'amount' => $validated['amount'],
            'payment_method' => $validated['payment_method'],
            'payment_date' => $validated['payment_date'],
            'notes' => $validated['notes'],
        ]);

        // Update sale payment status
        $sale->paid_amount += $validated['amount'];
        $sale->due_amount = max(0, $sale->total_amount - $sale->paid_amount);
        
        if ($sale->due_amount <= 0) {
            $sale->payment_status = 'paid';
        } else if ($sale->paid_amount > 0) {
            $sale->payment_status = 'partial';
        }
        
        $sale->save();

        // Log activity
        \App\Models\ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'updated',
            'model_type' => 'sale',
            'model_id' => $sale->id,
            'description' => 'Payment added to sale '.$sale->sale_no.' (Rs '.number_format((float)$validated['amount'],2).')',
            'ip_address' => request()->ip(),
        ]);

        return redirect()->route('sales.index')->with('success', 'Payment added successfully! Invoice #' . $sale->sale_no);
    }
}
