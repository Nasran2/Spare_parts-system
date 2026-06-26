<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\ProductPrice;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\Setting;
use App\Services\DashboardVisibilityService;
use App\Services\PrivacyModeService;
use App\Services\SalePaymentAccountingService;
use App\Services\SaleRecalculationService;
use App\Support\SecretPos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    private function hiddenProductIdsForCurrentUser(): array
    {
        return DashboardVisibilityService::hiddenProductIdsForUser(Auth::user());
    }

    private function hiddenCustomerIdsForCurrentUser(): array
    {
        return DashboardVisibilityService::hiddenCustomerIdsForUser(Auth::user());
    }

    private function hiddenSaleIdsForCurrentUser(): array
    {
        return DashboardVisibilityService::hiddenSaleIdsForUser(Auth::user());
    }

    private function saleContainsHiddenProduct(int $saleId, array $hiddenProductIds): bool
    {
        if (empty($hiddenProductIds)) {
            return false;
        }

        return SaleItem::query()
            ->where('sale_id', $saleId)
            ->whereIn('product_id', $hiddenProductIds)
            ->exists();
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $hiddenProductIds = $this->hiddenProductIdsForCurrentUser();
        $hiddenCustomerIds = $this->hiddenCustomerIdsForCurrentUser();
        $hiddenSaleIds = $this->hiddenSaleIdsForCurrentUser();

        $query = $this->filteredQuery($request);
        $isActive = PrivacyModeService::isActiveForUser($request->user()) && PrivacyModeService::shouldMaskForCurrentPage();

        $sales = $query->paginate(20)->appends($request->query());

        // Make sure totals are up-to-date for invoices that have returns.
        // (Older data or some flows might have recorded returns without recalculating the Sale row.)
        foreach ($sales->getCollection() as $sale) {
            if ($sale->returns->count() > 0) {
                SaleRecalculationService::recalculateSaleFinancials($sale);
            }
        }

        // Exchange return credit per sale (returns used during POS exchange checkout)
        $exchangeCredits = DB::table('sale_return_items')
            ->join('sale_returns', 'sale_return_items.sale_return_id', '=', 'sale_returns.id')
            ->whereIn('sale_returns.exchange_sale_id', $sales->getCollection()->pluck('id')->all())
            ->selectRaw('sale_returns.exchange_sale_id as exchange_sale_id, SUM(sale_return_items.total) as total')
            ->groupBy('sale_returns.exchange_sale_id')
            ->pluck('total', 'exchange_sale_id');

        $sales->getCollection()->transform(function ($sale) use ($exchangeCredits) {
            $sale->exchange_return_amount = (float) ($exchangeCredits[$sale->id] ?? 0);

            return $sale;
        });

        if ($isActive) {
            PrivacyModeService::applyDailyInvoiceLabels($sales->getCollection());
        }

        $customers = Customer::query()
            ->when(! empty($hiddenCustomerIds), fn ($query) => $query->whereNotIn('id', $hiddenCustomerIds))
            ->orderBy('name')
            ->get(['id', 'name']);

        $currency = config('app.currency', 'Rs ');

        return view('sales.index', [
            'sales' => $sales,
            'customers' => $customers,
            'filters' => $request->only(['date_from', 'date_to', 'customer_id', 'payment_status', 'all_dates']),
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
        $hiddenProductIds = $this->hiddenProductIdsForCurrentUser();
        $hiddenCustomerIds = $this->hiddenCustomerIdsForCurrentUser();
        $hiddenSaleIds = $this->hiddenSaleIdsForCurrentUser();
        $sale = Sale::with(['items.product', 'customer', 'user', 'payments', 'chequePayments'])->findOrFail($id);
        // Block access to hidden bills entirely
        if (SecretPos::isHidden((float) $sale->total_amount)) {
            abort(404);
        }
        if (in_array((int) $sale->id, $hiddenSaleIds, true)) {
            abort(404);
        }
        if ($sale->customer_id && in_array((int) $sale->customer_id, $hiddenCustomerIds, true)) {
            abort(404);
        }
        if ($this->saleContainsHiddenProduct($sale->id, $hiddenProductIds)) {
            abort(404);
        }

        if ($sale->returns()->exists()) {
            SaleRecalculationService::recalculateSaleFinancials($sale);
            $sale->refresh();
            $sale->load(['items.product', 'customer', 'user', 'payments', 'chequePayments']);
        }

        $returnedQtyByItem = SaleRecalculationService::returnedQtyBySaleItem($sale->id);
        $netItems = $sale->items
            ->map(function ($it) use ($returnedQtyByItem) {
                $returned = (int) ($returnedQtyByItem[$it->id] ?? 0);
                $netQty = max(0, (int) $it->quantity - $returned);
                $it->net_quantity = $netQty;
                $it->net_total = round($netQty * (float) $it->unit_price, 2);

                $originalUnitPrice = (float) ($it->product?->selling_price ?? $it->unit_price);
                if ($originalUnitPrice < (float) $it->unit_price) {
                    $originalUnitPrice = (float) $it->unit_price;
                }
                $it->display_unit_price = round($originalUnitPrice, 2);
                $it->line_discount_amount = round(max(0.0, ($originalUnitPrice - (float) $it->unit_price) * $netQty), 2);

                return $it;
            })
            ->filter(fn ($it) => (int) ($it->net_quantity ?? 0) > 0)
            ->values();

        $displaySubtotal = round((float) $netItems->sum(fn ($it) => (float) ($it->net_total ?? 0)), 2);

        $lineDiscountTotal = (float) $netItems->sum(fn ($it) => (float) ($it->line_discount_amount ?? 0));
        $cartDiscountAmount = max(0.0, round((float) $sale->discount - $lineDiscountTotal, 2));
        $currency = config('app.currency', 'Rs ');

        $returnItems = SaleReturnItem::with(['product'])
            ->whereHas('return', fn ($q) => $q->where('sale_id', $sale->id))
            ->get();

        $exchangeReturnItems = SaleReturnItem::with(['product'])
            ->whereHas('return', fn ($q) => $q->where('exchange_sale_id', $sale->id))
            ->get();
        $exchangeReturnAmount = round((float) $exchangeReturnItems->sum('total'), 2);

        return view('sales.show', compact(
            'sale',
            'currency',
            'netItems',
            'returnedQtyByItem',
            'cartDiscountAmount',
            'displaySubtotal',
            'returnItems',
            'exchangeReturnItems',
            'exchangeReturnAmount'
        ));
    }

    /**
     * Printable receipt view for a sale.
     */
    public function print(string $id)
    {
        $controls = DashboardVisibilityService::configForUser(Auth::user());
        $hiddenProductIds = $this->hiddenProductIdsForCurrentUser();
        $hiddenCustomerIds = $this->hiddenCustomerIdsForCurrentUser();
        $hiddenSaleIds = $this->hiddenSaleIdsForCurrentUser();

        $sale = Sale::with(['items.product', 'customer', 'user', 'payments', 'chequePayments'])->findOrFail($id);
        if (SecretPos::isHidden((float) $sale->total_amount)) {
            abort(404);
        }
        if (in_array((int) $sale->id, $hiddenSaleIds, true)) {
            abort(404);
        }
        if ($sale->customer_id && in_array((int) $sale->customer_id, $hiddenCustomerIds, true)) {
            abort(404);
        }
        if ($this->saleContainsHiddenProduct($sale->id, $hiddenProductIds)) {
            abort(404);
        }

        if ($sale->returns()->exists()) {
            SaleRecalculationService::recalculateSaleFinancials($sale);
            $sale->refresh();
            $sale->load(['items.product', 'customer', 'user', 'payments', 'chequePayments']);
        }

        $hasReturns = $sale->returns()->exists();

        $exchangeReturnAmount = 0.0;
        $exchangeReturnItems = collect();
        if (SaleReturn::query()->where('exchange_sale_id', $sale->id)->exists()) {
            $exchangeReturnItems = SaleReturnItem::with(['product'])
                ->whereHas('return', fn ($q) => $q->where('exchange_sale_id', $sale->id))
                ->get();
            $exchangeReturnAmount = max(0.0, round((float) $exchangeReturnItems->sum('total'), 2));
        }

        $returnAmount = 0.0;
        $originalTotalForDisplay = null;
        if ($hasReturns) {
            $returnAmount = (float) DB::table('sale_return_items')
                ->join('sale_returns', 'sale_return_items.sale_return_id', '=', 'sale_returns.id')
                ->where('sale_returns.sale_id', $sale->id)
                ->sum('sale_return_items.total');
            $returnAmount = max(0.0, round($returnAmount, 2));
            $originalTotalForDisplay = round((float) $sale->total_amount + $returnAmount, 2);
        }

        $returnItems = collect();
        if ($hasReturns) {
            $returnItems = SaleReturnItem::with(['product'])
                ->whereHas('return', fn ($q) => $q->where('sale_id', $sale->id))
                ->get();
        }

        $returnedQtyByItem = SaleRecalculationService::returnedQtyBySaleItem($sale->id);
        $netItems = $sale->items
            ->map(function ($it) use ($returnedQtyByItem) {
                $returned = (int) ($returnedQtyByItem[$it->id] ?? 0);
                $netQty = max(0, (int) $it->quantity - $returned);
                $it->net_quantity = $netQty;
                $it->net_total = round($netQty * (float) $it->unit_price, 2);

                $originalUnitPrice = (float) ($it->product?->selling_price ?? $it->unit_price);
                if ($originalUnitPrice < (float) $it->unit_price) {
                    $originalUnitPrice = (float) $it->unit_price;
                }
                $it->display_unit_price = round($originalUnitPrice, 2);
                $it->line_discount_amount = round(max(0.0, ($originalUnitPrice - (float) $it->unit_price) * $netQty), 2);

                return $it;
            })
            ->filter(fn ($it) => (int) ($it->net_quantity ?? 0) > 0)
            ->values();

        $displaySubtotal = round((float) $netItems->sum(fn ($it) => (float) ($it->net_total ?? 0)), 2);

        $lineDiscountTotal = (float) $netItems->sum(fn ($it) => (float) ($it->line_discount_amount ?? 0));
        $cartDiscountAmount = max(0.0, round((float) $sale->discount - $lineDiscountTotal, 2));

        $invoicePaperSize = Setting::get('invoice_paper_size', 'a4');
        $paperSize = strtolower((string) ($invoicePaperSize ?? 'a4'));
        $invoiceShowLogo = (bool) Setting::get('invoice_show_logo', true);
        $invoiceFooterText = (string) Setting::get('invoice_footer_text', 'Thank you for your business!');
        $invoiceTerms = (string) Setting::get('invoice_terms', '');

        $shop = [
            'name' => Setting::get('shop_name') ?? Setting::get('business_name') ?? config('app.name'),
            'address' => Setting::get('shop_address') ?? Setting::get('business_address') ?? '',
            'phone' => Setting::get('shop_phone') ?? Setting::get('business_phone') ?? '',
            'email' => Setting::get('shop_email') ?? Setting::get('business_email') ?? '',
            'logo' => Setting::get('shop_logo') ?? null,
        ];

        $logoValue = $shop['logo'] ?? null;
        $logoSrc = null;
        if (! empty($logoValue)) {
            if (preg_match('#^https?://#i', (string) $logoValue) || str_starts_with((string) $logoValue, '/')) {
                $logoSrc = (string) $logoValue;
            } else {
                if (is_file(public_path((string) $logoValue))) {
                    $logoSrc = asset((string) $logoValue);
                } elseif (is_file(public_path('storage/'.(string) $logoValue))) {
                    $logoSrc = asset('storage/'.(string) $logoValue);
                } else {
                    $logoSrc = asset((string) $logoValue);
                }
            }
        }
        $currency = config('app.currency', 'Rs ');

        return view('sales.print', compact(
            'sale',
            'netItems',
            'returnItems',
            'shop',
            'paperSize',
            'logoSrc',
            'hasReturns',
            'returnAmount',
            'originalTotalForDisplay',
            'exchangeReturnItems',
            'exchangeReturnAmount',
            'cartDiscountAmount',
            'displaySubtotal',
            'currency',
            'invoicePaperSize',
            'invoiceShowLogo',
            'invoiceFooterText',
            'invoiceTerms',
            'controls'
        ));
    }

    /**
     * Create a return for a sale
     */
    public function returnSale(Request $request, string $id)
    {
        $sale = Sale::with(['items.product'])->findOrFail($id);
        if (DashboardVisibilityService::isSaleHiddenForUser((int) $sale->id, $request->user())
            || ($sale->customer_id && DashboardVisibilityService::isCustomerHiddenForUser((int) $sale->customer_id, $request->user()))) {
            abort(404);
        }
        $data = $request->validate([
            'items' => 'required|array',
            'items.*.sale_item_id' => 'required|integer|exists:sale_items,id',
            'items.*.quantity' => 'required|integer|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Map sale items for quick lookup and ensure not exceeding sold qty
        $itemMap = $sale->items->keyBy('id');

        $subtotal = 0;

        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            $return = new \App\Models\SaleReturn;
            $return->sale_id = $sale->id;
            $return->user_id = Auth::id();
            $return->return_date = now();
            $return->notes = $data['notes'] ?? null;
            $return->subtotal = 0;
            $return->total_refund = 0;
            $return->save();

            foreach ($data['items'] as $ritem) {
                $saleItem = $itemMap->get($ritem['sale_item_id']);
                if (! $saleItem) {
                    continue;
                }

                $alreadyReturned = (int) SaleReturnItem::where('sale_item_id', $saleItem->id)->sum('quantity');
                $remainingQty = max(0, (int) $saleItem->quantity - $alreadyReturned);
                $requestedQty = (int) $ritem['quantity'];
                if ($requestedQty <= 0) {
                    continue;
                }
                if ($requestedQty > $remainingQty) {
                    throw new \Exception("Return quantity cannot exceed remaining quantity ({$remainingQty}) for {$saleItem->product?->name}");
                }

                $qty = $requestedQty;
                $unit = (float) $saleItem->unit_price;
                $line = $qty * $unit;
                $subtotal += $line;

                \App\Models\SaleReturnItem::create([
                    'sale_return_id' => $return->id,
                    'sale_item_id' => $saleItem->id,
                    'product_id' => $saleItem->product_id,
                    'product_price_id' => $saleItem->product_price_id,
                    'quantity' => $qty,
                    'unit_price' => $unit,
                    'total' => $line,
                ]);

                // Restock
                \App\Models\Product::where('id', $saleItem->product_id)->increment('stock_quantity', $qty);
                if ($saleItem->product_price_id && (bool) Setting::get('use_price_wise_stock', true)) {
                    ProductPrice::whereKey($saleItem->product_price_id)->increment('stock_qty', $qty);
                }
            }

            $return->subtotal = round($subtotal, 2);
            $return->total_refund = round($subtotal, 2); // simple: refund subtotal; taxes/discounts ignored for now
            if ((float) $return->total_refund <= 0) {
                throw new \Exception('No items selected for return.');
            }
            $return->save();

            $sale->refresh();
            $sale->load(['items', 'payments']);
            SaleRecalculationService::recalculateSaleFinancials($sale);

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
        $isActive = PrivacyModeService::isActiveForUser($request->user()) && PrivacyModeService::shouldMaskForCurrentPage();
        $filename = 'sales_'.now()->format('Ymd_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($query, $isActive) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Invoice No', 'Date', 'Customer', 'Amount', 'Paid', 'Due', 'Pay Status', 'Pay Method']);
            $dailyCounters = [];
            $query->chunk(500, function ($rows) use ($out, $isActive, &$dailyCounters) {
                foreach ($rows as $sale) {
                    $invoiceNo = $sale->sale_no;
                    if ($isActive) {
                        $dateKey = $sale->sale_date?->toDateString() ?? $sale->created_at?->toDateString() ?? 'unknown';
                        $dailyCounters[$dateKey] = ($dailyCounters[$dateKey] ?? 0) + 1;
                        $invoiceNo = PrivacyModeService::orderedInvoiceLabel((string) $sale->sale_no, $dailyCounters[$dateKey]);
                    }

                    fputcsv($out, [
                        $invoiceNo,
                        optional($sale->sale_date)->format('Y-m-d') ?? $sale->created_at->format('Y-m-d'),
                        $sale->customer->name ?? 'Walk-in Customer',
                        number_format((float) $sale->total_amount, 2, '.', ''),
                        number_format((float) $sale->paid_amount, 2, '.', ''),
                        number_format((float) $sale->due_amount, 2, '.', ''),
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
        if (PrivacyModeService::isActiveForUser($request->user()) && PrivacyModeService::shouldMaskForCurrentPage()) {
            PrivacyModeService::applyDailyInvoiceLabels($sales);
        }
        $controls = DashboardVisibilityService::configForUser($request->user());
        $shop = [
            'name' => Setting::get('shop_name', config('app.name')),
            'address' => Setting::get('shop_address', ''),
            'phone' => Setting::get('shop_phone', ''),
            'email' => Setting::get('shop_email', ''),
            'logo' => Setting::get('shop_logo') ?? null,
        ];

        // Lazy dependency note: requires barryvdh/laravel-dompdf
        if (! app()->bound('dompdf.wrapper')) {
            return back()->with('error', 'PDF export library not installed');
        }
        $pdf = app('dompdf.wrapper');
        $pdf->loadView('sales.export_pdf', compact('sales', 'shop', 'controls'));
        $pdf->setPaper('A4', 'portrait');

        return $pdf->download('sales_'.now()->format('Ymd_His').'.pdf');
    }

    /**
     * Download a Quotation PDF (separate from thermal receipt)
     */
    public function quotationPdf(string $id)
    {
        $sale = Sale::with(['items.product', 'customer', 'user'])->where('sale_type', 'quotation')->findOrFail($id);
        $shop = [
            'name' => Setting::get('shop_name', 'Vehicle POS System'),
            'tagline' => Setting::get('shop_tagline', 'Auto Parts System'),
            'address' => Setting::get('shop_address', ''),
            'phone' => Setting::get('shop_phone', ''),
            'email' => Setting::get('shop_email', ''),
            'logo' => Setting::get('shop_logo') ?? null,
            'valid_days' => (int) Setting::get('quotation_valid_days', 30),
            'terms' => (string) Setting::get('quotation_terms', 'Prices are subject to change without prior notice. Payment terms as agreed.'),
            'footer_text' => (string) Setting::get('quotation_footer_text', 'This is a system generated quotation.'),
        ];

        if (! app()->bound('dompdf.wrapper')) {
            abort(500, 'PDF export library not installed');
        }
        $pdf = app('dompdf.wrapper');
        $currency = config('app.currency', 'Rs ');
        $pdf->loadView('quotations.pdf', compact('sale', 'shop', 'currency'));
        $pdf->setPaper('A4', 'portrait');

        // Stream inline (do not force download) so it can be previewed/printed in-browser.
        return $pdf->stream($sale->sale_no.'.pdf');
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
                    if ((bool) Setting::get('use_price_wise_stock', true) && $it->product_price_id) {
                        $price = ProductPrice::whereKey($it->product_price_id)->lockForUpdate()->first();
                        if (! $price || (float) $price->stock_qty < (float) $it->quantity) {
                            throw new \Exception("Not enough stock for {$p->name} at the selected selling price.");
                        }
                        $price->decrement('stock_qty', $it->quantity);
                    }
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
        $hiddenProductIds = DashboardVisibilityService::hiddenProductIdsForUser($request->user());
        $hiddenCustomerIds = DashboardVisibilityService::hiddenCustomerIdsForUser($request->user());
        $hiddenSaleIds = DashboardVisibilityService::hiddenSaleIdsForUser($request->user());

        $query = Sale::with(['customer', 'user'])->orderByDesc('created_at');
        if (! empty($hiddenSaleIds)) {
            $query->whereNotIn('id', $hiddenSaleIds);
        }
        if (! empty($hiddenCustomerIds)) {
            $query->where(function ($customerQuery) use ($hiddenCustomerIds) {
                $customerQuery->whereNull('customer_id')
                    ->orWhereNotIn('customer_id', $hiddenCustomerIds);
            });
        }
        if ($request->string('sale_type') !== 'quotation') {
            if (! $request->filled('date_from') && ! $request->filled('date_to') && ! $request->boolean('all_dates')) {
                $query->whereDate('sale_date', now()->toDateString());
            }
        }

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
            $query->where('sale_type', (string) $request->string('sale_type'));
        }
        // Exclude hidden-range bills from any listing/export
        $query = SecretPos::excludeHiddenRanges($query, 'total_amount');
        if (! empty($hiddenProductIds)) {
            $query->whereDoesntHave('items', fn ($q) => $q->whereIn('product_id', $hiddenProductIds));
        }
        if (PrivacyModeService::isActiveForUser($request->user()) && PrivacyModeService::shouldMaskForCurrentPage()) {
            $query->where('sale_type', 'sale')
                ->where('sale_no', 'like', 'INV-%');

            $percentage = (int) PrivacyModeService::setting('visible_invoice_limit', 100);
            $mode = PrivacyModeService::setting('sales_list_percentage_mode', 'all_matching_bills');

            if ($mode === 'each_day') {
                $clone = clone $query;
                $salesData = $clone->get(['id', 'sale_date', 'created_at', 'total_amount']);
                $allowedIds = collect();
                $grouped = $salesData->groupBy(function($s) {
                    return $s->sale_date?->toDateString() ?? $s->created_at?->toDateString() ?? 'unknown';
                });
                foreach ($grouped as $date => $daySales) {
                    $dayTotal = $daySales->count();
                    $limit = (int) ceil($dayTotal * ($percentage / 100));
                    $dayAllowed = $daySales->sortBy('total_amount')->take($limit)->pluck('id');
                    $allowedIds = $allowedIds->concat($dayAllowed);
                }
                $query->whereIn('id', $allowedIds);
            } else {
                $clone = clone $query;
                $totalCount = $clone->count();
                $limit = (int) ceil($totalCount * ($percentage / 100));
                $allowedIds = $clone->reorder()->orderBy('total_amount', 'asc')->limit($limit)->pluck('id');
                $query->whereIn('id', $allowedIds);
            }
        }

        return $query;
    }

    /**
     * List quotations (sale_type=quotation)
     */
    public function quotations(Request $request)
    {
        $request->merge(['sale_type' => 'quotation']);
        $query = $this->filteredQuery($request);
        $quotations = $query->paginate(20)->appends($request->query());
        $hiddenCustomerIds = DashboardVisibilityService::hiddenCustomerIdsForUser($request->user());
        $customers = Customer::query()
            ->when(! empty($hiddenCustomerIds), fn ($q) => $q->whereNotIn('id', $hiddenCustomerIds))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('quotations.index', [
            'sales' => $quotations,
            'customers' => $customers,
            'filters' => $request->only(['date_from', 'date_to', 'customer_id', 'payment_status', 'sale_type']),
            'currency' => Setting::get('currency_symbol', '$'),
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
        if (! Auth::user()?->hasPermission('sales.delete')) {
            return redirect()->back()->with('error', 'You do not have permission to delete sales.');
        }

        $sale = Sale::findOrFail($id);
        if (DashboardVisibilityService::isSaleHiddenForUser((int) $sale->id, Auth::user())
            || ($sale->customer_id && DashboardVisibilityService::isCustomerHiddenForUser((int) $sale->customer_id, Auth::user()))) {
            abort(404);
        }

        try {
            DB::transaction(function () use ($sale) {
                if ($sale->sale_type === 'sale') {
                    $sale->loadMissing(['items', 'returns.items']);

                    $returnedByProduct = [];
                    $returnedByPrice = [];
                    foreach ($sale->returns as $return) {
                        foreach ($return->items as $returnItem) {
                            $productId = (int) $returnItem->product_id;
                            $returnedByProduct[$productId] = ($returnedByProduct[$productId] ?? 0) + (int) $returnItem->quantity;
                            if ($returnItem->product_price_id) {
                                $priceId = (int) $returnItem->product_price_id;
                                $returnedByPrice[$priceId] = ($returnedByPrice[$priceId] ?? 0) + (int) $returnItem->quantity;
                            }
                        }
                    }

                    foreach ($sale->items as $item) {
                        \App\Models\Product::where('id', $item->product_id)->increment('stock_quantity', (int) $item->quantity);
                        if ($item->product_price_id && (bool) Setting::get('use_price_wise_stock', true)) {
                            ProductPrice::whereKey($item->product_price_id)->increment('stock_qty', (int) $item->quantity);
                        }
                    }

                    foreach ($returnedByProduct as $productId => $qty) {
                        \App\Models\Product::where('id', $productId)->decrement('stock_quantity', (int) $qty);
                    }
                    if ((bool) Setting::get('use_price_wise_stock', true)) {
                        foreach ($returnedByPrice as $priceId => $qty) {
                            ProductPrice::whereKey($priceId)->decrement('stock_qty', (int) $qty);
                        }
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
        if (DashboardVisibilityService::isSaleHiddenForUser((int) $sale->id, $request->user())
            || ($sale->customer_id && DashboardVisibilityService::isCustomerHiddenForUser((int) $sale->customer_id, $request->user()))) {
            abort(404);
        }

        // Validate payment amount doesn't exceed due amount
        if ($validated['amount'] > $sale->due_amount) {
            return back()->with('error', 'Payment amount cannot exceed due amount of Rs '.number_format((float) $sale->due_amount, 2));
        }

        // Create payment record
        $payment = \App\Models\Payment::create([
            'sale_id' => $sale->id,
            'customer_id' => $sale->customer_id,
            'amount' => $validated['amount'],
            'payment_method' => $validated['payment_method'],
            'payment_date' => $validated['payment_date'],
            'notes' => $validated['notes'],
        ]);
        app(SalePaymentAccountingService::class)->recordSalePayment($payment, $sale, Auth::id());

        // Update sale payment status
        $sale->paid_amount += $validated['amount'];
        $sale->due_amount = max(0, $sale->total_amount - $sale->paid_amount);

        if ($sale->due_amount <= 0) {
            $sale->payment_status = 'paid';
        } elseif ($sale->paid_amount > 0) {
            $sale->payment_status = 'partial';
        }

        $sale->save();

        // Log activity
        \App\Models\ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'updated',
            'model_type' => 'sale',
            'model_id' => $sale->id,
            'description' => 'Payment added to sale '.$sale->sale_no.' (Rs '.number_format((float) $validated['amount'], 2).')',
            'ip_address' => request()->ip(),
        ]);

        return redirect()->route('sales.index')->with('success', 'Payment added successfully! Invoice #'.$sale->sale_no);
    }
}
