<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Payment;
// use App\Models\Setting; // already imported above
use App\Models\Customer;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\Setting;
use App\Support\SecretPos;
use App\Services\DashboardVisibilityService;
use App\Services\PrivacyModeService;

class ReportController extends Controller
{
    private function shouldRoundPriceDisplay(array $controls): bool
    {
        return (float) ($controls['price_visible_percentage'] ?? 100) < 100;
    }

    private function visibilityControls(Request $request): array
    {
        return DashboardVisibilityService::configForUser($request->user());
    }

    private function priceValue(float|int $value, array $controls): float
    {
        if (!empty($controls['hide_price_wise_data'])) {
            return 0.0;
        }

        return DashboardVisibilityService::maskByPercentage((float) $value, (float) ($controls['price_visible_percentage'] ?? 100));
    }

    private function qtyValue(float|int $value, array $controls): float
    {
        if (!empty($controls['hide_qty_wise_data'])) {
            return 0.0;
        }

        return DashboardVisibilityService::maskByPercentage((float) $value, (float) ($controls['qty_visible_percentage'] ?? 100));
    }

    private function stockValue(float|int $value, array $controls): float
    {
        if (!empty($controls['hide_actual_stock_quantity']) || !empty($controls['hide_qty_wise_data'])) {
            return 0.0;
        }

        return DashboardVisibilityService::maskByPercentage((float) $value, (float) ($controls['stock_visible_percentage'] ?? 100));
    }

    private function inventoryQtyPercentage(array $controls): float
    {
        $qtyPct = (float) ($controls['qty_visible_percentage'] ?? 100);
        $stockPct = (float) ($controls['stock_visible_percentage'] ?? 100);

        return max(0.0, min(100.0, min($qtyPct, $stockPct)));
    }

    private function inventoryQtyValue(float|int $value, array $controls): float
    {
        if (!empty($controls['hide_qty_wise_data'])) {
            return 0.0;
        }

        return DashboardVisibilityService::maskByPercentage((float) $value, $this->inventoryQtyPercentage($controls));
    }

    private function inventoryAmountValue(float|int $value, array $controls): float
    {
        $stockAdjusted = DashboardVisibilityService::maskByPercentage((float) $value, $this->inventoryQtyPercentage($controls));

        return $this->priceValue($stockAdjusted, $controls);
    }

    private function maskCurrencyForControls(float|int $value, array $controls, bool $forceHide = false): string
    {
        if (\App\Services\PrivacyModeService::isActiveForUser(auth()->user()) && \App\Services\PrivacyModeService::shouldMaskForCurrentPage()) {
            return \App\Services\PrivacyModeService::maskAmount((float) $value);
        }

        if ($forceHide || !empty($controls['hide_price_wise_data'])) {
            return '—';
        }

        $masked = $this->priceValue((float) $value, $controls);
        $roundToWhole = $this->shouldRoundPriceDisplay($controls);

        return number_format($roundToWhole ? round($masked) : $masked, $roundToWhole ? 0 : 2);
    }

    private function maskQtyForControls(float|int $value, array $controls, bool $forceHide = false): string
    {
        if ($forceHide || !empty($controls['hide_qty_wise_data'])) {
            return '—';
        }

        return number_format(round($this->qtyValue((float) $value, $controls)), 0);
    }

    private function maskInventoryQtyForControls(float|int $value, array $controls, bool $forceHide = false): string
    {
        if ($forceHide || !empty($controls['hide_qty_wise_data'])) {
            return '—';
        }

        return number_format(round($this->inventoryQtyValue((float) $value, $controls)), 0);
    }

    private function maskInventoryAmountForControls(float|int $value, array $controls, bool $forceHide = false): string
    {
        if (\App\Services\PrivacyModeService::isActiveForUser(auth()->user()) && \App\Services\PrivacyModeService::shouldMaskForCurrentPage()) {
            return \App\Services\PrivacyModeService::maskAmount((float) $value);
        }

        if ($forceHide || !empty($controls['hide_stock_values']) || !empty($controls['hide_price_wise_data'])) {
            return '—';
        }

        $masked = $this->inventoryAmountValue((float) $value, $controls);
        $roundToWhole = $this->shouldRoundPriceDisplay($controls)
            || $this->inventoryQtyPercentage($controls) < 100;

        return number_format($roundToWhole ? round($masked) : $masked, $roundToWhole ? 0 : 2);
    }

    private function ensureReportsVisible(array $controls): void
    {
        if (!empty($controls['hide_reports'])) {
            abort(404);
        }
    }

    private function resolveCategoryFilterIds(?int $mainCategoryId, ?int $subCategoryId): ?array
    {
        if ($subCategoryId) {
            return [$subCategoryId];
        }

        if ($mainCategoryId) {
            $childIds = \App\Models\Category::query()
                ->where('parent_id', $mainCategoryId)
                ->pluck('id')
                ->all();

            return array_values(array_unique(array_merge([$mainCategoryId], $childIds)));
        }

        return null;
    }

    private function hiddenProductIds(Request $request): array
    {
        return DashboardVisibilityService::hiddenProductIdsForUser($request->user());
    }

    private function applyHiddenRecordsToSalesQuery($query, Request $request)
    {
        $hiddenSaleIds = DashboardVisibilityService::hiddenSaleIdsForUser($request->user());
        $hiddenCustomerIds = DashboardVisibilityService::hiddenCustomerIdsForUser($request->user());

        if (!empty($hiddenSaleIds)) {
            $query->whereNotIn('id', $hiddenSaleIds);
        }

        if (!empty($hiddenCustomerIds)) {
            $query->where(function ($customerQuery) use ($hiddenCustomerIds) {
                $customerQuery->whereNull('customer_id')
                    ->orWhereNotIn('customer_id', $hiddenCustomerIds);
            });
        }

        return $query;
    }

    private function applyHiddenRecordsToPurchaseQuery($query, Request $request)
    {
        $hiddenSupplierIds = DashboardVisibilityService::hiddenSupplierIdsForUser($request->user());

        if (!empty($hiddenSupplierIds)) {
            $query->whereNotIn('supplier_id', $hiddenSupplierIds);
        }

        return $query;
    }

    private function applyHiddenProductsToSalesQuery($query, array $hiddenProductIds)
    {
        if (empty($hiddenProductIds)) {
            return $query;
        }

        return $query->whereDoesntHave('items', fn ($q) => $q->whereIn('product_id', $hiddenProductIds));
    }

    private function applyHiddenProductsToPurchaseQuery($query, array $hiddenProductIds)
    {
        if (empty($hiddenProductIds)) {
            return $query;
        }

        return $query->whereDoesntHave('items', fn ($q) => $q->whereIn('product_id', $hiddenProductIds));
    }

    /**
     * Sales report.
     */
    public function sales(Request $request)
    {
        $controls = $this->visibilityControls($request);
        $this->ensureReportsVisible($controls);
        $hiddenProductIds = $this->hiddenProductIds($request);

        [$from, $to] = $this->dateRange($request);
        $categoryId = $request->filled('category_id') ? (int) $request->input('category_id') : null;
        $subcategoryId = $request->filled('subcategory_id') ? (int) $request->input('subcategory_id') : null;
        $categoryIds = $this->resolveCategoryFilterIds($categoryId, $subcategoryId);

        $query = Sale::with(['customer', 'items.product.category'])
            ->when($from, fn($q) => $q->whereDate('sale_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('sale_date', '<=', $to))
            ->when(!empty($categoryIds), function ($q) use ($categoryIds) {
                $q->whereHas('items.product', function ($pq) use ($categoryIds) {
                    $pq->where(function ($outer) use ($categoryIds) {
                        $outer->whereIn('category_id', $categoryIds)
                            ->orWhereHas('categories', fn($cq) => $cq->whereIn('categories.id', $categoryIds));
                    });
                });
            })
            ->orderBy('sale_date', 'desc');

        $query = $this->applyHiddenProductsToSalesQuery($query, $hiddenProductIds);
        $query = $this->applyHiddenRecordsToSalesQuery($query, $request);
        $query = SecretPos::excludeHiddenSaleRanges($query, 'total_amount');

        $sales = $query->get();
        $visible = $sales;

        $summary = [
            'total_sales' => $visible->sum('total_amount'),
            'total_paid' => $visible->sum('paid_amount'),
            'total_due' => $visible->sum('due_amount'),
            'count' => $visible->count(),
            'filtered_category' => $subcategoryId
                ? optional(\App\Models\Category::find($subcategoryId))->name
                : ($categoryId ? optional(\App\Models\Category::find($categoryId))->name : null),
        ];

        if (!empty($controls['hide_total_sales'])) {
            $summary['total_sales'] = 0;
        }
        if (!empty($controls['hide_supplier_payments']) || !empty($controls['hide_invoice_details'])) {
            $summary['total_paid'] = 0;
            $summary['total_due'] = 0;
        }
        if (!empty($controls['hide_invoice_details'])) {
            $summary['count'] = 0;
        }

        $dailyMap = [];
        foreach ($visible as $s) {
            $key = $s->sale_date ? $s->sale_date->toDateString() : null;
            if (!$key) { continue; }
            if (!isset($dailyMap[$key])) {
                $dailyMap[$key] = ['date' => $key, 'count' => 0, 'total' => 0.0];
            }
            $dailyMap[$key]['count'] += 1;
            $dailyMap[$key]['total'] += (float) $s->total_amount;
        }
        $daily = collect(array_values($dailyMap));
        $sales = $visible->values();
        if (PrivacyModeService::isActiveForUser($request->user()) && PrivacyModeService::shouldMaskForCurrentPage()) {
            PrivacyModeService::applyDailyInvoiceLabels($sales);
        }

        $categories = \App\Models\Category::whereNull('parent_id')->orderBy('name')->get(['id', 'name']);
        return view('reports.sales', compact('sales', 'summary', 'daily', 'from', 'to', 'categories', 'categoryId', 'subcategoryId', 'controls'));
    }

    public function salesPdf(Request $request)
    {
        $controls = $this->visibilityControls($request);
        $this->ensureReportsVisible($controls);
        $hiddenProductIds = $this->hiddenProductIds($request);

        [$from, $to] = $this->dateRange($request);
        $categoryId = $request->filled('category_id') ? (int) $request->input('category_id') : null;
        $subcategoryId = $request->filled('subcategory_id') ? (int) $request->input('subcategory_id') : null;
        $categoryIds = $this->resolveCategoryFilterIds($categoryId, $subcategoryId);

        $sales = Sale::with(['customer'])
            ->when($from, fn($q) => $q->whereDate('sale_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('sale_date', '<=', $to))
            ->when(!empty($categoryIds), function ($q) use ($categoryIds) {
                $q->whereHas('items.product', function ($pq) use ($categoryIds) {
                    $pq->where(function ($outer) use ($categoryIds) {
                        $outer->whereIn('category_id', $categoryIds)
                            ->orWhereHas('categories', fn($cq) => $cq->whereIn('categories.id', $categoryIds));
                    });
                });
            })
            ->orderBy('sale_date', 'desc')
            ->when(!empty($hiddenProductIds), fn ($q) => $q->whereDoesntHave('items', fn ($sq) => $sq->whereIn('product_id', $hiddenProductIds)));
        $sales = $this->applyHiddenRecordsToSalesQuery($sales, $request);
        $sales = SecretPos::excludeHiddenSaleRanges($sales, 'total_amount')->get();
        $visible = $sales;
        $summary = [
            'total_sales' => $visible->sum('total_amount'),
            'total_paid' => $visible->sum('paid_amount'),
            'total_due' => $visible->sum('due_amount'),
            'count' => $visible->count(),
        ];

        if (!empty($controls['hide_total_sales'])) {
            $summary['total_sales'] = 0;
        }
        if (!empty($controls['hide_supplier_payments']) || !empty($controls['hide_invoice_details'])) {
            $summary['total_paid'] = 0;
            $summary['total_due'] = 0;
        }
        if (!empty($controls['hide_invoice_details'])) {
            $summary['count'] = 0;
        }

        $sales = $visible->values();
        if (PrivacyModeService::isActiveForUser($request->user()) && PrivacyModeService::shouldMaskForCurrentPage()) {
            PrivacyModeService::applyDailyInvoiceLabels($sales);
        }
        $pdf = Pdf::loadView('reports.pdf.sales', compact('sales','summary','from','to','controls'))->setPaper('a4', 'portrait');
        return $pdf->download('sales-report.pdf');
    }

    public function salesCsv(Request $request)
    {
        $controls = $this->visibilityControls($request);
        $this->ensureReportsVisible($controls);
        $hiddenProductIds = $this->hiddenProductIds($request);

        [$from, $to] = $this->dateRange($request);
        $categoryId = $request->filled('category_id') ? (int) $request->input('category_id') : null;
        $subcategoryId = $request->filled('subcategory_id') ? (int) $request->input('subcategory_id') : null;
        $categoryIds = $this->resolveCategoryFilterIds($categoryId, $subcategoryId);

        $sales = Sale::with(['customer'])
            ->when($from, fn($q) => $q->whereDate('sale_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('sale_date', '<=', $to))
            ->when(!empty($categoryIds), function ($q) use ($categoryIds) {
                $q->whereHas('items.product', function ($pq) use ($categoryIds) {
                    $pq->where(function ($outer) use ($categoryIds) {
                        $outer->whereIn('category_id', $categoryIds)
                            ->orWhereHas('categories', fn($cq) => $cq->whereIn('categories.id', $categoryIds));
                    });
                });
            })
            ->orderBy('sale_date', 'desc')
            ->when(!empty($hiddenProductIds), fn ($q) => $q->whereDoesntHave('items', fn ($sq) => $sq->whereIn('product_id', $hiddenProductIds)));
        $sales = $this->applyHiddenRecordsToSalesQuery($sales, $request);
        $sales = SecretPos::excludeHiddenSaleRanges($sales, 'total_amount')->get();
        if (PrivacyModeService::isActiveForUser($request->user()) && PrivacyModeService::shouldMaskForCurrentPage()) {
            PrivacyModeService::applyDailyInvoiceLabels($sales);
        }

        $rows = [['Date','Invoice','Customer','Total','Paid','Due','Status']];
        foreach ($sales as $s) {
            $hideInvoice = !empty($controls['hide_invoice_details']);
            $hidePayments = !empty($controls['hide_supplier_payments']) || $hideInvoice;
            $rows[] = [
                optional($s->sale_date)->toDateString(),
                $hideInvoice ? 'HIDDEN' : PrivacyModeService::displayInvoiceNumber($s),
                !empty($controls['hide_supplier_names']) ? 'Hidden' : ($s->customer?->name ?? 'Walk-in'),
                $this->maskCurrencyForControls((float) $s->total_amount, $controls, $hideInvoice),
                $this->maskCurrencyForControls((float) $s->paid_amount, $controls, $hidePayments),
                $this->maskCurrencyForControls((float) $s->due_amount, $controls, $hidePayments),
                $s->payment_status,
            ];
        }
        $csv = fopen('php://temp','r+'); foreach ($rows as $r) { fputcsv($csv,$r); } rewind($csv);
        return response(stream_get_contents($csv), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="sales-report.csv"'
        ]);
    }

    /**
     * Purchase report.
     */
    public function purchase(Request $request)
    {
        $controls = $this->visibilityControls($request);
        $this->ensureReportsVisible($controls);
        $hiddenProductIds = $this->hiddenProductIds($request);

        [$from, $to] = $this->dateRange($request);
        $categoryId = $request->filled('category_id') ? (int) $request->input('category_id') : null;
        $subcategoryId = $request->filled('subcategory_id') ? (int) $request->input('subcategory_id') : null;
        $categoryIds = $this->resolveCategoryFilterIds($categoryId, $subcategoryId);

        $query = Purchase::with(['supplier', 'items.product.category'])
            ->when($from, fn($q) => $q->whereDate('purchase_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('purchase_date', '<=', $to))
            ->when(!empty($categoryIds), function ($q) use ($categoryIds) {
                $q->whereHas('items.product', function ($pq) use ($categoryIds) {
                    $pq->where(function ($outer) use ($categoryIds) {
                        $outer->whereIn('category_id', $categoryIds)
                            ->orWhereHas('categories', fn($cq) => $cq->whereIn('categories.id', $categoryIds));
                    });
                });
            })
            ->orderBy('purchase_date', 'desc');

        $query = $this->applyHiddenProductsToPurchaseQuery($query, $hiddenProductIds);
        $query = $this->applyHiddenRecordsToPurchaseQuery($query, $request);

        $query = SecretPos::excludeHiddenPurchaseRanges($query, 'total_amount');

        $purchases = $query->get();

        $summary = [
            'total_purchases' => $purchases->sum('total_amount'),
            'total_paid' => $purchases->sum('paid_amount'),
            'total_due' => $purchases->sum('due_amount'),
            'count' => $purchases->count(),
        ];

        if (!empty($controls['hide_total_purchase'])) {
            $summary['total_purchases'] = 0;
        }
        if (!empty($controls['hide_supplier_payments']) || !empty($controls['hide_invoice_details'])) {
            $summary['total_paid'] = 0;
            $summary['total_due'] = 0;
        }
        if (!empty($controls['hide_invoice_details'])) {
            $summary['count'] = 0;
        }

        $categories = \App\Models\Category::whereNull('parent_id')->orderBy('name')->get(['id', 'name']);
        return view('reports.purchase', compact('purchases', 'summary', 'from', 'to', 'categories', 'categoryId', 'subcategoryId', 'controls'));
    }

    public function purchasePdf(Request $request)
    {
        $controls = $this->visibilityControls($request);
        $this->ensureReportsVisible($controls);
        $hiddenProductIds = $this->hiddenProductIds($request);

        [$from, $to] = $this->dateRange($request);
        $categoryId = $request->filled('category_id') ? (int) $request->input('category_id') : null;
        $subcategoryId = $request->filled('subcategory_id') ? (int) $request->input('subcategory_id') : null;
        $categoryIds = $this->resolveCategoryFilterIds($categoryId, $subcategoryId);

        $query = Purchase::with(['supplier'])
            ->when($from, fn($q) => $q->whereDate('purchase_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('purchase_date', '<=', $to))
            ->when(!empty($categoryIds), function ($q) use ($categoryIds) {
                $q->whereHas('items.product', function ($pq) use ($categoryIds) {
                    $pq->where(function ($outer) use ($categoryIds) {
                        $outer->whereIn('category_id', $categoryIds)
                            ->orWhereHas('categories', fn($cq) => $cq->whereIn('categories.id', $categoryIds));
                    });
                });
            })
            ->orderBy('purchase_date', 'desc');
        $query = $this->applyHiddenProductsToPurchaseQuery($query, $hiddenProductIds);
        $query = $this->applyHiddenRecordsToPurchaseQuery($query, $request);
        $query = SecretPos::excludeHiddenPurchaseRanges($query, 'total_amount');
        $purchases = $query->get();
        $summary = [
            'total_purchases' => $purchases->sum('total_amount'),
            'total_paid' => $purchases->sum('paid_amount'),
            'total_due' => $purchases->sum('due_amount'),
            'count' => $purchases->count(),
        ];

        if (!empty($controls['hide_total_purchase'])) {
            $summary['total_purchases'] = 0;
        }
        if (!empty($controls['hide_supplier_payments']) || !empty($controls['hide_invoice_details'])) {
            $summary['total_paid'] = 0;
            $summary['total_due'] = 0;
        }
        if (!empty($controls['hide_invoice_details'])) {
            $summary['count'] = 0;
        }

        $pdf = Pdf::loadView('reports.pdf.purchase', compact('purchases','summary','from','to','controls'))->setPaper('a4', 'portrait');
        return $pdf->download('purchase-report.pdf');
    }

    public function purchaseCsv(Request $request)
    {
        $controls = $this->visibilityControls($request);
        $this->ensureReportsVisible($controls);
        $hiddenProductIds = $this->hiddenProductIds($request);

        [$from, $to] = $this->dateRange($request);
        $categoryId = $request->filled('category_id') ? (int) $request->input('category_id') : null;
        $subcategoryId = $request->filled('subcategory_id') ? (int) $request->input('subcategory_id') : null;
        $categoryIds = $this->resolveCategoryFilterIds($categoryId, $subcategoryId);

        $query = Purchase::with(['supplier'])
            ->when($from, fn($q) => $q->whereDate('purchase_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('purchase_date', '<=', $to))
            ->when(!empty($categoryIds), function ($q) use ($categoryIds) {
                $q->whereHas('items.product', function ($pq) use ($categoryIds) {
                    $pq->where(function ($outer) use ($categoryIds) {
                        $outer->whereIn('category_id', $categoryIds)
                            ->orWhereHas('categories', fn($cq) => $cq->whereIn('categories.id', $categoryIds));
                    });
                });
            })
            ->orderBy('purchase_date', 'desc');
        $query = $this->applyHiddenProductsToPurchaseQuery($query, $hiddenProductIds);
        $query = $this->applyHiddenRecordsToPurchaseQuery($query, $request);
        $query = SecretPos::excludeHiddenPurchaseRanges($query, 'total_amount');
        $purchases = $query->get();
        $rows = [['Date','PO #','Supplier','Total','Paid','Due','Status']];
        foreach ($purchases as $p) {
            $hideInvoice = !empty($controls['hide_invoice_details']);
            $hidePayments = !empty($controls['hide_supplier_payments']) || $hideInvoice;
            $rows[] = [
                optional($p->purchase_date)->toDateString(),
                $hideInvoice ? 'HIDDEN' : $p->purchase_no,
                !empty($controls['hide_supplier_names']) ? 'Hidden' : ($p->supplier?->name ?? 'N/A'),
                $this->maskCurrencyForControls((float) $p->total_amount, $controls, !empty($controls['hide_total_purchase']) || $hideInvoice),
                $this->maskCurrencyForControls((float) $p->paid_amount, $controls, $hidePayments),
                $this->maskCurrencyForControls((float) $p->due_amount, $controls, $hidePayments),
                $p->payment_status,
            ];
        }
        $csv = fopen('php://temp','r+'); foreach ($rows as $r) { fputcsv($csv,$r); } rewind($csv);
        return response(stream_get_contents($csv), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="purchase-report.csv"'
        ]);
    }

    /**
     * Expense report.
     */
    public function expense(Request $request)
    {
        $controls = $this->visibilityControls($request);
        $this->ensureReportsVisible($controls);

        [$from, $to] = $this->dateRange($request);
        $query = Expense::with(['category'])->when($from, fn($q) => $q->whereDate('expense_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('expense_date', '<=', $to))
            ->orderBy('expense_date', 'desc');

        $expenses = $query->get();

        $summary = [
            'total_expense' => $expenses->sum('amount'),
            'count' => $expenses->count(),
        ];

        if (!empty($controls['hide_price_wise_data']) || !empty($controls['hide_widgets'])) {
            $summary['total_expense'] = 0;
        }

        if (!empty($controls['hide_tables'])) {
            $expenses = collect();
            $byCategory = collect();
            return view('reports.expense', compact('expenses', 'summary', 'byCategory', 'from', 'to', 'controls'));
        }

        $byCategory = $expenses->groupBy(fn($e) => $e->category?->name ?? 'Uncategorized')->map(function ($group) {
            return [
                'category' => $group->first()->category?->name ?? 'Uncategorized',
                'total' => $group->sum('amount'),
                'count' => $group->count(),
            ];
        })->sortByDesc('total')->values();

        return view('reports.expense', compact('expenses', 'summary', 'byCategory', 'from', 'to', 'controls'));
    }

    public function expensePdf(Request $request)
    {
        $controls = $this->visibilityControls($request);
        $this->ensureReportsVisible($controls);

        [$from, $to] = $this->dateRange($request);
        $expenses = Expense::with('category')
            ->when($from, fn($q) => $q->whereDate('expense_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('expense_date', '<=', $to))
            ->orderBy('expense_date','desc')->get();
        $summary = [ 'total_expense' => $expenses->sum('amount'), 'count' => $expenses->count() ];
        if (!empty($controls['hide_price_wise_data']) || !empty($controls['hide_widgets'])) {
            $summary['total_expense'] = 0;
        }
        $pdf = Pdf::loadView('reports.pdf.expense', compact('expenses','summary','from','to','controls'))->setPaper('a4');
        return $pdf->download('expense-report.pdf');
    }

    public function expenseCsv(Request $request)
    {
        $controls = $this->visibilityControls($request);
        $this->ensureReportsVisible($controls);

        [$from, $to] = $this->dateRange($request);
        $expenses = Expense::with('category')
            ->when($from, fn($q) => $q->whereDate('expense_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('expense_date', '<=', $to))
            ->orderBy('expense_date','desc')->get();
        $rows = [['Date','Category','Description','Amount']];
        foreach ($expenses as $e) {
            $rows[] = [
                optional($e->expense_date)->toDateString(),
                $e->category?->name ?? 'Uncategorized',
                $e->description,
                $this->maskCurrencyForControls((float) $e->amount, $controls),
            ];
        }
        $csv = fopen('php://temp','r+'); foreach ($rows as $r) { fputcsv($csv,$r); } rewind($csv);
        return response(stream_get_contents($csv), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="expense-report.csv"'
        ]);
    }

    /**
     * Stock report.
     */
    public function stock(Request $request)
    {
        $controls = $this->visibilityControls($request);
        $this->ensureReportsVisible($controls);
        $hiddenProductIds = $this->hiddenProductIds($request);

        $selectedCategoryId = $request->filled('category_id') ? (int) $request->input('category_id') : null;
        $selectedSubcategoryId = $request->filled('subcategory_id') ? (int) $request->input('subcategory_id') : null;
        $categoryIds = $this->resolveCategoryFilterIds($selectedCategoryId, $selectedSubcategoryId);
        $selectedBrandId = $request->filled('brand_id') ? (int) $request->input('brand_id') : null;
        $lowStockOnly = $request->boolean('low_stock');
        $search = $request->filled('search') ? trim((string) $request->input('search')) : null;

        // Lightweight query for summary totals across ALL matching products
        $allProducts = $this->stockBaseQuery($categoryIds, $selectedBrandId, $lowStockOnly, $search, $hiddenProductIds)
            ->get(['id','name','cost_price','selling_price','stock_quantity','alert_quantity']);

        $totalCostValue    = (float) $allProducts->sum(fn($p) => max(0, (float)($p->stock_quantity ?? 0)) * (float)($p->cost_price ?? 0));
        $totalSellingValue = (float) $allProducts->sum(fn($p) => max(0, (float)($p->stock_quantity ?? 0)) * (float)($p->selling_price ?? 0));
        $expectedProfit    = $totalSellingValue - $totalCostValue;
        $lowStockCount     = $allProducts->filter(fn($p) => (float)($p->stock_quantity ?? 0) <= (float)($p->alert_quantity ?? 0))->count();

        $summary = [
            'total_products'     => $allProducts->count(),
            'low_stock'          => $lowStockCount,
            'total_stock'        => $allProducts->sum('stock_quantity'),
            'total_cost_value'   => $totalCostValue,
            'total_selling_value'=> $totalSellingValue,
            'expected_profit'    => $expectedProfit,
        ];

        if (!empty($controls['hide_stock_values'])) {
            $summary['total_cost_value'] = 0;
            $summary['total_selling_value'] = 0;
            $summary['expected_profit'] = 0;
        }
        if (!empty($controls['hide_actual_stock_count']) || !empty($controls['hide_actual_stock_quantity'])) {
            $summary['total_stock'] = 0;
            $summary['low_stock'] = 0;
        }

        // Paginated query (100 per page) for the table display
        $paginator = $this->stockBaseQuery($categoryIds, $selectedBrandId, $lowStockOnly, $search, $hiddenProductIds)
            ->paginate(100)
            ->withQueryString();

        $pageProducts = $paginator->getCollection();
        $pageTotalCost = (float) $pageProducts->sum(fn($p) => max(0, (float) ($p->stock_quantity ?? 0)) * (float) ($p->cost_price ?? 0));
        $pageTotalSelling = (float) $pageProducts->sum(fn($p) => max(0, (float) ($p->stock_quantity ?? 0)) * (float) ($p->selling_price ?? 0));

        $items = $this->mapStockItems($paginator->getCollection());

        $categories = \App\Models\Category::whereNull('parent_id')->orderBy('name')->get(['id', 'name']);
        $brands = \App\Models\Brand::orderBy('name')->get(['id', 'name']);

        return view('reports.stock', compact(
            'items',
            'paginator',
            'pageTotalCost',
            'pageTotalSelling',
            'summary',
            'categories',
            'brands',
            'selectedCategoryId',
            'selectedSubcategoryId',
            'selectedBrandId',
            'lowStockOnly',
            'search',
            'controls'
        ));
    }

    public function stockPdf(Request $request)
    {
        $controls = $this->visibilityControls($request);
        $this->ensureReportsVisible($controls);
        $hiddenProductIds = $this->hiddenProductIds($request);

        $selectedCategoryId = $request->filled('category_id') ? (int) $request->input('category_id') : null;
        $selectedSubcategoryId = $request->filled('subcategory_id') ? (int) $request->input('subcategory_id') : null;
        $categoryIds = $this->resolveCategoryFilterIds($selectedCategoryId, $selectedSubcategoryId);
        $selectedBrandId = $request->filled('brand_id') ? (int) $request->input('brand_id') : null;
        $lowStockOnly = $request->boolean('low_stock');
        $search = $request->filled('search') ? trim((string) $request->input('search')) : null;

        $products = $this->stockBaseQuery($categoryIds, $selectedBrandId, $lowStockOnly, $search, $hiddenProductIds)->get();
        $items = $products->map(function ($p) {
            $purchasedQty = $p->purchaseItems->sum('quantity');
            $soldQty = $p->saleItems->sum('quantity');
            $isLowStock = (float) ($p->stock_quantity ?? 0) <= (float) ($p->alert_quantity ?? 0);
            return [
                'name' => $p->name,
                'categories' => $p->categories->pluck('name')->join(', '),
                'brand' => $p->brands->pluck('name')->join(', ') ?: ($p->brand?->name ?? '-'),
                'cost_price' => (float) ($p->cost_price ?? 0),
                'selling_price' => (float) ($p->selling_price ?? 0),
                'purchased' => $purchasedQty,
                'sold' => $soldQty,
                'current_stock' => $p->stock_quantity,
                'low_stock' => $isLowStock,
            ];
        });

        $totalCostValue = (float) $products->sum(fn($p) => (float) ($p->stock_quantity ?? 0) * (float) ($p->cost_price ?? 0));
        $totalSellingValue = (float) $products->sum(fn($p) => (float) ($p->stock_quantity ?? 0) * (float) ($p->selling_price ?? 0));
        $expectedProfit = $totalSellingValue - $totalCostValue;

        $summary = [
            'total_products' => $products->count(),
            'low_stock' => $items->where('low_stock', true)->count(),
            'total_stock' => $products->sum('stock_quantity'),
            'total_cost_value' => $totalCostValue,
            'total_selling_value' => $totalSellingValue,
            'expected_profit' => $expectedProfit,
        ];

        if (!empty($controls['hide_stock_values'])) {
            $summary['total_cost_value'] = 0;
            $summary['total_selling_value'] = 0;
            $summary['expected_profit'] = 0;
        }
        if (!empty($controls['hide_actual_stock_count']) || !empty($controls['hide_actual_stock_quantity'])) {
            $summary['total_stock'] = 0;
            $summary['low_stock'] = 0;
        }

        $pdf = Pdf::loadView('reports.pdf.stock', compact('items','summary','controls'))->setPaper('a4', 'portrait');
        return $pdf->download('stock-report.pdf');
    }

    public function stockCsv(Request $request)
    {
        $controls = $this->visibilityControls($request);
        $this->ensureReportsVisible($controls);
        $hiddenProductIds = $this->hiddenProductIds($request);

        $selectedCategoryId = $request->filled('category_id') ? (int) $request->input('category_id') : null;
        $selectedSubcategoryId = $request->filled('subcategory_id') ? (int) $request->input('subcategory_id') : null;
        $categoryIds = $this->resolveCategoryFilterIds($selectedCategoryId, $selectedSubcategoryId);
        $selectedBrandId = $request->filled('brand_id') ? (int) $request->input('brand_id') : null;
        $lowStockOnly = $request->boolean('low_stock');
        $search = $request->filled('search') ? trim((string) $request->input('search')) : null;

        $products = $this->stockBaseQuery($categoryIds, $selectedBrandId, $lowStockOnly, $search, $hiddenProductIds)->get();
        $rows = [['Product','Category','Cost Price','Selling Price','Purchased Qty','Sold Qty','Current Stock','Total Cost','Total Selling','Status']];
        foreach ($products as $p) {
            $isLowStock = (float) ($p->stock_quantity ?? 0) <= (float) ($p->alert_quantity ?? 0);
            $stock = max(0, (int) ($p->stock_quantity ?? 0));
            $lineCost    = (float) ($p->cost_price ?? 0) * $stock;
            $lineSelling = (float) ($p->selling_price ?? 0) * $stock;
            $rows[] = [
                !empty($controls['hide_product_wise_data']) ? 'Hidden Product' : $p->name,
                $p->categories->pluck('name')->join(', '),
                $this->maskCurrencyForControls((float) ($p->cost_price ?? 0), $controls, !empty($controls['hide_actual_purchase_price']) || !empty($controls['hide_actual_stock_price'])),
                $this->maskCurrencyForControls((float) ($p->selling_price ?? 0), $controls, !empty($controls['hide_actual_stock_price'])),
                $this->maskInventoryQtyForControls((float) $p->purchaseItems->sum('quantity'), $controls, !empty($controls['hide_qty_wise_data'])),
                $this->maskInventoryQtyForControls((float) $p->saleItems->sum('quantity'), $controls, !empty($controls['hide_qty_wise_data'])),
                $this->maskInventoryQtyForControls((float) $p->stock_quantity, $controls, !empty($controls['hide_actual_stock_quantity']) || !empty($controls['hide_qty_wise_data'])),
                $this->maskInventoryAmountForControls((float) $lineCost, $controls, !empty($controls['hide_actual_stock_price']) || !empty($controls['hide_stock_values'])),
                $this->maskInventoryAmountForControls((float) $lineSelling, $controls, !empty($controls['hide_actual_stock_price']) || !empty($controls['hide_stock_values'])),
                $isLowStock ? 'Low' : 'OK',
            ];
        }
        $csv = fopen('php://temp','r+'); foreach ($rows as $r) { fputcsv($csv,$r); } rewind($csv);
        return response(stream_get_contents($csv), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="stock-report.csv"'
        ]);
    }

    private function stockBaseQuery(
        ?array $categoryIds,
        ?int $brandId,
        bool $lowStockOnly,
        ?string $search = null,
        array $hiddenProductIds = []
    )
    {
        $query = Product::with(['category', 'categories', 'brand', 'brands', 'unit', 'saleItems', 'purchaseItems']);

        if (!empty($hiddenProductIds)) {
            $query->whereNotIn('id', $hiddenProductIds);
        }

        if ($search !== null && $search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        if (!empty($categoryIds)) {
            $query->where(function ($q) use ($categoryIds) {
                $q->whereIn('category_id', $categoryIds)
                    ->orWhereHas('categories', fn($cq) => $cq->whereIn('categories.id', $categoryIds));
            });
        }

        if ($brandId) {
            $query->where(function ($q) use ($brandId) {
                $q->where('brand_id', $brandId)
                  ->orWhereHas('brands', fn($bq) => $bq->whereKey($brandId));
            });
        }

        if ($lowStockOnly) {
            $query->whereColumn('stock_quantity', '<=', 'alert_quantity');
        }

        return $query;
    }

    private function mapStockItems($products)
    {
        return $products->map(function ($p) {
            $purchasedQty = $p->purchaseItems->sum('quantity');
            $soldQty = $p->saleItems->sum('quantity');
            return [
                'product' => $p,
                'purchased' => $purchasedQty,
                'sold' => $soldQty,
                'current_stock' => $p->stock_quantity,
                'low_stock' => $p->isLowStock(),
            ];
        });
    }

    /**
     * Profit & Loss report.
     */
    public function profitLoss(Request $request)
    {
        $controls = $this->visibilityControls($request);
        $this->ensureReportsVisible($controls);
        $hiddenProductIds = $this->hiddenProductIds($request);

        [$from, $to] = $this->dateRange($request);
        $sales = Sale::when($from, fn($q) => $q->whereDate('sale_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('sale_date', '<=', $to))
            ->when(!empty($hiddenProductIds), fn ($q) => $q->whereDoesntHave('items', fn ($sq) => $sq->whereIn('product_id', $hiddenProductIds)));
        $sales = $this->applyHiddenRecordsToSalesQuery($sales, $request);
        $sales = SecretPos::excludeHiddenSaleRanges($sales, 'total_amount')->get();

        $visible = $sales;
        $salesRevenue = $visible->sum('total_amount');

        $saleItemIds = $visible->pluck('id');
        $saleItems = SaleItem::with('product')->whereIn('sale_id', $saleItemIds)->get();
        $returnedAgg = $this->returnedAggForSaleItemIds($saleItems->pluck('id')->all());
        $returnedQty = $returnedAgg['qty'];
        $cogs = $saleItems->sum(function ($i) use ($returnedQty) {
            $netQty = max(0, (int) $i->quantity - (int) ($returnedQty[$i->id] ?? 0));
            return $netQty * (float) ($i->product?->cost_price ?? 0);
        });
        $grossProfit = $salesRevenue - $cogs;

        $expenses = Expense::when($from, fn($q) => $q->whereDate('expense_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('expense_date', '<=', $to))->get();
        $expenseTotal = $expenses->sum('amount');
        $netProfit = $grossProfit - $expenseTotal;

        $summary = [
            'sales_revenue' => $salesRevenue,
            'cogs' => $cogs,
            'gross_profit' => $grossProfit,
            'expenses' => $expenseTotal,
            'net_profit' => $netProfit,
        ];

        return view('reports.profit-loss', compact('summary', 'from', 'to', 'controls'));
    }

    public function profitLossPdf(Request $request)
    {
        $controls = $this->visibilityControls($request);
        $this->ensureReportsVisible($controls);
        $hiddenProductIds = $this->hiddenProductIds($request);

        [$from, $to] = $this->dateRange($request);
        $sales = Sale::when($from, fn($q) => $q->whereDate('sale_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('sale_date', '<=', $to))
            ->when(!empty($hiddenProductIds), fn ($q) => $q->whereDoesntHave('items', fn ($sq) => $sq->whereIn('product_id', $hiddenProductIds)));
        $sales = $this->applyHiddenRecordsToSalesQuery($sales, $request);
        $sales = SecretPos::excludeHiddenSaleRanges($sales, 'total_amount')->get();
        $visible = $sales;
        $salesRevenue = $visible->sum('total_amount');
        $saleItems = SaleItem::with('product')->whereIn('sale_id', $visible->pluck('id'))->get();
        $returnedAgg = $this->returnedAggForSaleItemIds($saleItems->pluck('id')->all());
        $returnedQty = $returnedAgg['qty'];
        $cogs = $saleItems->sum(function ($i) use ($returnedQty) {
            $netQty = max(0, (int) $i->quantity - (int) ($returnedQty[$i->id] ?? 0));
            return $netQty * (float) ($i->product?->cost_price ?? 0);
        });
        $grossProfit = $salesRevenue - $cogs;
        $expenses = Expense::when($from, fn($q) => $q->whereDate('expense_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('expense_date', '<=', $to))->get();
        $expenseTotal = $expenses->sum('amount');
        $netProfit = $grossProfit - $expenseTotal;
        $summary = compact('salesRevenue','cogs','grossProfit','expenseTotal','netProfit');
        $pdf = Pdf::loadView('reports.pdf.profit-loss', compact('summary','from','to','controls'))->setPaper('a4');
        return $pdf->download('profit-loss-report.pdf');
    }

    public function profitLossCsv(Request $request)
    {
        $controls = $this->visibilityControls($request);
        $this->ensureReportsVisible($controls);
        $hiddenProductIds = $this->hiddenProductIds($request);

        [$from, $to] = $this->dateRange($request);
        $sales = Sale::when($from, fn($q) => $q->whereDate('sale_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('sale_date', '<=', $to))
            ->when(!empty($hiddenProductIds), fn ($q) => $q->whereDoesntHave('items', fn ($sq) => $sq->whereIn('product_id', $hiddenProductIds)));
        $sales = $this->applyHiddenRecordsToSalesQuery($sales, $request);
        $sales = SecretPos::excludeHiddenSaleRanges($sales, 'total_amount')->get();
        $visible = $sales;
        $salesRevenue = $visible->sum('total_amount');
        $saleItems = SaleItem::with('product')->whereIn('sale_id', $visible->pluck('id'))->get();
        $returnedAgg = $this->returnedAggForSaleItemIds($saleItems->pluck('id')->all());
        $returnedQty = $returnedAgg['qty'];
        $cogs = $saleItems->sum(function ($i) use ($returnedQty) {
            $netQty = max(0, (int) $i->quantity - (int) ($returnedQty[$i->id] ?? 0));
            return $netQty * (float) ($i->product?->cost_price ?? 0);
        });
        $grossProfit = $salesRevenue - $cogs;
        $expenses = Expense::when($from, fn($q) => $q->whereDate('expense_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('expense_date', '<=', $to))->get();
        $expenseTotal = $expenses->sum('amount');
        $netProfit = $grossProfit - $expenseTotal;
        $rows = [
            ['Metric','Value'],
            ['Sales Revenue', $this->maskCurrencyForControls((float) $salesRevenue, $controls, !empty($controls['hide_total_sales']))],
            ['COGS', $this->maskCurrencyForControls((float) $cogs, $controls)],
            ['Gross Profit', $this->maskCurrencyForControls((float) $grossProfit, $controls)],
            ['Expenses', $this->maskCurrencyForControls((float) $expenseTotal, $controls)],
            ['Net Profit', $this->maskCurrencyForControls((float) $netProfit, $controls)],
        ];
        $csv = fopen('php://temp','r+'); foreach ($rows as $r) { fputcsv($csv,$r); } rewind($csv);
        return response(stream_get_contents($csv), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="profit-loss-report.csv"'
        ]);
    }

    /**
     * Trending products (top selling).
     */
    public function trending(Request $request)
    {
        $controls = $this->visibilityControls($request);
        $this->ensureReportsVisible($controls);
        $hiddenProductIds = $this->hiddenProductIds($request);

        [$from, $to] = $this->dateRange($request);
        $itemsQuery = SaleItem::with('product', 'sale')
            ->whereHas('sale', fn ($saleQuery) => $this->applyHiddenRecordsToSalesQuery($saleQuery, $request))
            ->when($from || $to, function ($q) use ($from, $to, $request) {
                $q->whereHas('sale', function ($sq) use ($from, $to, $request) {
                    $this->applyHiddenRecordsToSalesQuery($sq, $request);
                    $sq->when($from, fn($qq) => $qq->whereDate('sale_date', '>=', $from))
                        ->when($to, fn($qq) => $qq->whereDate('sale_date', '<=', $to));
                });
            })
            ->when(!empty($hiddenProductIds), fn ($q) => $q->whereNotIn('product_id', $hiddenProductIds));

        $items = $itemsQuery->get();
        $items = $items->reject(fn($i) => SecretPos::isHidden((float) ($i->sale?->total_amount ?? 0)));
        $returnedAgg = $this->returnedAggForSaleItemIds($items->pluck('id')->all());
        $returnedQty = $returnedAgg['qty'];
        $returnedTotal = $returnedAgg['total'];
        $top = $items->groupBy('product_id')->map(function ($group) use ($returnedQty, $returnedTotal) {
            $product = $group->first()->product;
            $netQty = $group->sum(function ($i) use ($returnedQty) {
                return max(0, (int) $i->quantity - (int) ($returnedQty[$i->id] ?? 0));
            });
            $netRevenue = $group->sum(function ($i) use ($returnedTotal) {
                return max(0.0, (float) $i->total - (float) ($returnedTotal[$i->id] ?? 0));
            });
            return [
                'product' => $product,
                'quantity' => $netQty,
                'revenue' => $netRevenue,
            ];
        })->sortByDesc('quantity')->values()->take(15);

        return view('reports.trending', compact('top', 'from', 'to', 'controls'));
    }

    protected function returnedAggForSaleItemIds(array $saleItemIds): array
    {
        $saleItemIds = array_values(array_filter($saleItemIds, fn ($v) => !is_null($v)));
        if (empty($saleItemIds)) {
            return ['qty' => [], 'total' => []];
        }

        $rows = DB::table('sale_return_items')
            ->select(
                'sale_item_id',
                DB::raw('SUM(quantity) as qty'),
                DB::raw('SUM(total) as total')
            )
            ->whereIn('sale_item_id', $saleItemIds)
            ->groupBy('sale_item_id')
            ->get();

        $qty = [];
        $total = [];
        foreach ($rows as $r) {
            $qty[(int) $r->sale_item_id] = (int) $r->qty;
            $total[(int) $r->sale_item_id] = (float) $r->total;
        }
        return ['qty' => $qty, 'total' => $total];
    }

    /**
     * Helper to extract date range.
     */
    protected function dateRange(Request $request): array
    {
        $from = $request->input('from');
        $to = $request->input('to');
        return [$from ?: null, $to ?: null];
    }

    /**
     * VAT Report (sales-side VAT estimation).
     */
    public function vat(Request $request)
    {
        $controls = $this->visibilityControls($request);
        $this->ensureReportsVisible($controls);
        $hiddenProductIds = $this->hiddenProductIds($request);

        [$from, $to] = $this->dateRange($request);
        $rate = (float) (Setting::get('vat_rate', 0));
        $enabled = (bool) (Setting::get('vat_enabled', false));

        $sales = Sale::when($from, fn($q) => $q->whereDate('sale_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('sale_date', '<=', $to))
            ->when(!empty($hiddenProductIds), fn ($q) => $q->whereDoesntHave('items', fn ($sq) => $sq->whereIn('product_id', $hiddenProductIds)))
            ->orderBy('sale_date', 'desc')
            ;
        $sales = $this->applyHiddenRecordsToSalesQuery($sales, $request);
        $sales = SecretPos::excludeHiddenSaleRanges($sales, 'total_amount')->get(['id','sale_date','total_amount']);
        $visible = $sales;
        $totalFinal = $visible->sum('total_amount');
        $vatExclusive = $enabled ? ($totalFinal * ($rate / 100)) : 0; // if price stored exclusive of VAT added
        $vatInclusive = $enabled ? ($totalFinal * ($rate / (100 + $rate))) : 0; // if price stored inclusive

        $dailyTemp = [];
        foreach ($visible as $saleEntry) {
            $key = $saleEntry->sale_date ? $saleEntry->sale_date->toDateString() : null;
            if (!$key) { continue; }
            if (!isset($dailyTemp[$key])) { $dailyTemp[$key] = 0.0; }
            $dailyTemp[$key] += (float) $saleEntry->total_amount;
        }
        $daily = collect(array_map(function ($date, $final) use ($enabled, $rate) {
            return [
                'date' => $date,
                'final_total' => $final,
                'vat_exclusive' => $enabled ? ($final * ($rate / 100)) : 0,
                'vat_inclusive' => $enabled ? ($final * ($rate / (100 + $rate))) : 0,
            ];
        }, array_keys($dailyTemp), array_values($dailyTemp)));

        $summary = [
            'enabled' => $enabled,
            'rate' => $rate,
            'total_final' => $totalFinal,
            'vat_exclusive' => $vatExclusive,
            'vat_inclusive' => $vatInclusive,
            'count' => $visible->count(),
        ];

        return view('reports.vat', compact('summary', 'daily', 'from', 'to', 'controls'));
    }

    /**
     * Incoming payments (receipts from customers).
     */
    public function receive(Request $request)
    {
        $controls = $this->visibilityControls($request);
        $this->ensureReportsVisible($controls);
        $hiddenProductIds = $this->hiddenProductIds($request);
        $hiddenCustomerIds = DashboardVisibilityService::hiddenCustomerIdsForUser($request->user());
        $hiddenSaleIds = DashboardVisibilityService::hiddenSaleIdsForUser($request->user());

        [$from, $to] = $this->dateRange($request);
        $query = Payment::with(['customer', 'sale'])
            ->where(function ($q) {
                $q->whereNotNull('sale_id')->orWhereNotNull('customer_id');
            })
            ->when(!empty($hiddenCustomerIds), function ($q) use ($hiddenCustomerIds) {
                $q->where(function ($customerQuery) use ($hiddenCustomerIds) {
                    $customerQuery->whereNull('customer_id')
                        ->orWhereNotIn('customer_id', $hiddenCustomerIds);
                });
            })
            ->when(!empty($hiddenSaleIds), function ($q) use ($hiddenSaleIds) {
                $q->where(function ($saleQuery) use ($hiddenSaleIds) {
                    $saleQuery->whereNull('sale_id')
                        ->orWhereNotIn('sale_id', $hiddenSaleIds);
                });
            })
            ->where(function ($q) use ($hiddenProductIds) {
                $q->whereNull('sale_id')
                    ->orWhereHas('sale', function ($sq) use ($hiddenProductIds) {
                        SecretPos::excludeHiddenSaleRanges($sq, 'total_amount');
                        if (!empty($hiddenProductIds)) {
                            $sq->whereDoesntHave('items', fn ($iq) => $iq->whereIn('product_id', $hiddenProductIds));
                        }
                    });
            })
            ->when($from, fn($q) => $q->whereDate('payment_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('payment_date', '<=', $to))
            ->orderBy('payment_date', 'desc');

        $payments = $query->get();

        $summary = [
            'total_received' => $payments->sum('amount'),
            'count' => $payments->count(),
            'by_method' => $payments->groupBy('payment_method')->map(fn($g) => $g->sum('amount')),
        ];

        $daily = $payments->groupBy(fn($p) => optional($p->payment_date)->toDateString())
            ->map(fn($g) => [
                'date' => optional($g->first()->payment_date)->toDateString(),
                'total' => $g->sum('amount'),
                'count' => $g->count(),
            ])->values();

        return view('reports.receive', compact('payments', 'summary', 'daily', 'from', 'to', 'controls'));
    }

    /**
     * Outgoing payments (debits to suppliers).
     */
    public function debit(Request $request)
    {
        $controls = $this->visibilityControls($request);
        $this->ensureReportsVisible($controls);
        $hiddenProductIds = $this->hiddenProductIds($request);
        $hiddenSupplierIds = DashboardVisibilityService::hiddenSupplierIdsForUser($request->user());

        [$from, $to] = $this->dateRange($request);
        $query = Payment::with(['supplier', 'purchase'])
            ->where(function ($q) {
                $q->whereNotNull('purchase_id')->orWhereNotNull('supplier_id');
            })
            ->when(!empty($hiddenSupplierIds), function ($q) use ($hiddenSupplierIds) {
                $q->where(function ($supplierQuery) use ($hiddenSupplierIds) {
                    $supplierQuery->whereNull('supplier_id')
                        ->orWhereNotIn('supplier_id', $hiddenSupplierIds);
                });
            })
            ->where(function ($q) use ($hiddenProductIds) {
                $q->whereNull('purchase_id')
                ->orWhereHas('purchase', function ($pq) use ($hiddenProductIds) {
                    SecretPos::excludeHiddenPurchaseRanges($pq, 'total_amount');
                    if (!empty($hiddenProductIds)) {
                        $pq->whereDoesntHave('items', fn ($iq) => $iq->whereIn('product_id', $hiddenProductIds));
                    }
                });
            })
            ->when($from, fn($q) => $q->whereDate('payment_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('payment_date', '<=', $to))
            ->orderBy('payment_date', 'desc');

        $payments = $query->get();

        $summary = [
            'total_debit' => $payments->sum('amount'),
            'count' => $payments->count(),
            'by_method' => $payments->groupBy('payment_method')->map(fn($g) => $g->sum('amount')),
        ];

        $daily = $payments->groupBy(fn($p) => optional($p->payment_date)->toDateString())
            ->map(fn($g) => [
                'date' => optional($g->first()->payment_date)->toDateString(),
                'total' => $g->sum('amount'),
                'count' => $g->count(),
            ])->values();

        return view('reports.debit', compact('payments', 'summary', 'daily', 'from', 'to', 'controls'));
    }

    /** PDF exports */
    public function vatPdf(Request $request)
    {
        $controls = $this->visibilityControls($request);
        $this->ensureReportsVisible($controls);
        $hiddenProductIds = $this->hiddenProductIds($request);

        [$from, $to] = $this->dateRange($request);
        $rate = (float) (Setting::get('vat_rate', 0));
        $enabled = (bool) (Setting::get('vat_enabled', false));
        $sales = Sale::when($from, fn($q) => $q->whereDate('sale_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('sale_date', '<=', $to))
            ->when(!empty($hiddenProductIds), fn ($q) => $q->whereDoesntHave('items', fn ($sq) => $sq->whereIn('product_id', $hiddenProductIds)));
        $sales = SecretPos::excludeHiddenSaleRanges($sales, 'total_amount')->get(['id','sale_date','total_amount']);
        $visible = $sales;
        $totalFinal = $visible->sum('total_amount');
        $vatExclusive = $enabled ? ($totalFinal * ($rate / 100)) : 0;
        $vatInclusive = $enabled ? ($totalFinal * ($rate / (100 + $rate))) : 0;
        $dailyTemp = [];
        foreach ($visible as $saleEntry) {
            $saleDate = data_get($saleEntry, 'sale_date');
            $key = $saleDate ? $saleDate->toDateString() : null;
            if (!$key) { continue; }
            if (!isset($dailyTemp[$key])) { $dailyTemp[$key] = 0.0; }
            $dailyTemp[$key] += (float) data_get($saleEntry, 'total_amount', 0);
        }
        $daily = collect(array_map(function ($date, $final) use ($enabled, $rate) {
            return [
                'date' => $date,
                'final_total' => $final,
                'vat_exclusive' => $enabled ? ($final * ($rate / 100)) : 0,
                'vat_inclusive' => $enabled ? ($final * ($rate / (100 + $rate))) : 0,
            ];
        }, array_keys($dailyTemp), array_values($dailyTemp)));
        $summary = compact('enabled','rate','totalFinal','vatExclusive','vatInclusive');
        $pdf = Pdf::loadView('reports.pdf.vat', compact('summary','daily','from','to','controls'))->setPaper('a4');
        return $pdf->download('vat-report.pdf');
    }

    public function vatCsv(Request $request)
    {
        $controls = $this->visibilityControls($request);
        $this->ensureReportsVisible($controls);
        $hiddenProductIds = $this->hiddenProductIds($request);

        [$from, $to] = $this->dateRange($request);
        $rate = (float) (Setting::get('vat_rate', 0));
        $enabled = (bool) (Setting::get('vat_enabled', false));
        $sales = Sale::when($from, fn($q) => $q->whereDate('sale_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('sale_date', '<=', $to))
            ->when(!empty($hiddenProductIds), fn ($q) => $q->whereDoesntHave('items', fn ($sq) => $sq->whereIn('product_id', $hiddenProductIds)));
        $sales = SecretPos::excludeHiddenSaleRanges($sales, 'total_amount')->get(['id','sale_date','total_amount']);
        $visible = $sales;
        $rows = [ ['Date','Sales Total','VAT (Exclusive)','VAT (Inclusive)'] ];
        $byDate = [];
        foreach ($visible as $saleEntry) {
            $saleDate = data_get($saleEntry, 'sale_date');
            $key = $saleDate ? $saleDate->toDateString() : null;
            if (!$key) { continue; }
            if (!isset($byDate[$key])) { $byDate[$key] = 0.0; }
            $byDate[$key] += (float) data_get($saleEntry, 'total_amount', 0);
        }
        foreach ($byDate as $date => $final) {
            $ex = $enabled ? ($final * ($rate / 100)) : 0;
            $inc = $enabled ? ($final * ($rate / (100 + $rate))) : 0;
            $rows[] = [
                $date,
                $this->maskCurrencyForControls((float) $final, $controls, !empty($controls['hide_total_sales'])),
                $this->maskCurrencyForControls((float) $ex, $controls),
                $this->maskCurrencyForControls((float) $inc, $controls),
            ];
        }
        $csv = fopen('php://temp', 'r+');
        foreach ($rows as $r) { fputcsv($csv, $r); }
        rewind($csv);
        return response(stream_get_contents($csv), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="vat-report.csv"'
        ]);
    }

    public function vatDayDetails(Request $request)
    {
        $controls = $this->visibilityControls($request);
        $this->ensureReportsVisible($controls);
        $hiddenProductIds = $this->hiddenProductIds($request);

        $date = $request->query('date');
        if (!$date) { return response()->json(['error' => 'date required'], 422); }
        $rate = (float) (Setting::get('vat_rate', 0));
        $enabled = (bool) (Setting::get('vat_enabled', false));
        $items = SaleItem::with('product','sale')
            ->whereHas('sale', fn($q) => $q->whereDate('sale_date', $date))
            ->when(!empty($hiddenProductIds), fn ($q) => $q->whereNotIn('product_id', $hiddenProductIds))
            ->get()
            ->reject(fn($i) => \App\Support\SecretPos::isHidden((float) ($i->sale?->total_amount ?? 0)));
        $lines = $items->map(function ($i) use ($rate, $enabled) {
            $lineTotal = $i->quantity * $i->unit_price;
            $vatExclusive = $enabled ? ($lineTotal * ($rate / 100)) : 0;
            $vatInclusive = $enabled ? ($lineTotal * ($rate / (100 + $rate))) : 0;
            return [
                'product' => $i->product?->name ?? 'Unknown',
                'invoice' => $i->sale?->sale_no ?? ($i->sale?->id),
                'quantity' => (float) $i->quantity,
                'unit_price' => round((float) $i->unit_price, 2),
                'line_total' => round((float) $lineTotal, 2),
                'vat_exclusive' => round((float) $vatExclusive, 2),
                'vat_inclusive' => round((float) $vatInclusive, 2),
            ];
        });

        $hideTotalSales = !empty($controls['hide_total_sales']);
        $maskedLines = $lines->map(function ($line) use ($controls, $hideTotalSales) {
            return [
                'product' => $line['product'],
                'invoice' => !empty($controls['hide_invoice_details']) ? 'HIDDEN' : $line['invoice'],
                'quantity' => $this->qtyValue((float) $line['quantity'], $controls),
                'unit_price' => $this->priceValue((float) $line['unit_price'], $controls),
                'line_total' => $this->priceValue((float) $line['line_total'], $controls),
                'vat_exclusive' => $this->priceValue((float) $line['vat_exclusive'], $controls),
                'vat_inclusive' => $this->priceValue((float) $line['vat_inclusive'], $controls),
                'quantity_display' => $this->maskQtyForControls((float) $line['quantity'], $controls),
                'unit_price_display' => $this->maskCurrencyForControls((float) $line['unit_price'], $controls),
                'line_total_display' => $this->maskCurrencyForControls((float) $line['line_total'], $controls, $hideTotalSales),
                'vat_exclusive_display' => $this->maskCurrencyForControls((float) $line['vat_exclusive'], $controls),
                'vat_inclusive_display' => $this->maskCurrencyForControls((float) $line['vat_inclusive'], $controls),
            ];
        });

        $totals = [
            'line_total' => $this->priceValue((float) $lines->sum('line_total'), $controls),
            'vat_exclusive' => $this->priceValue((float) $lines->sum('vat_exclusive'), $controls),
            'vat_inclusive' => $this->priceValue((float) $lines->sum('vat_inclusive'), $controls),
            'line_total_display' => $this->maskCurrencyForControls((float) $lines->sum('line_total'), $controls, $hideTotalSales),
            'vat_exclusive_display' => $this->maskCurrencyForControls((float) $lines->sum('vat_exclusive'), $controls),
            'vat_inclusive_display' => $this->maskCurrencyForControls((float) $lines->sum('vat_inclusive'), $controls),
        ];

        return response()->json([
            'date' => $date,
            'rate' => $rate,
            'enabled' => $enabled,
            'items' => $maskedLines,
            'totals' => $totals,
        ]);
    }

    public function vatDayPdf(Request $request)
    {
        $controls = $this->visibilityControls($request);
        $this->ensureReportsVisible($controls);
        $hiddenProductIds = $this->hiddenProductIds($request);

        $date = $request->query('date');
        if (!$date) { abort(422, 'date required'); }
        $rate = (float) (Setting::get('vat_rate', 0));
        $enabled = (bool) (Setting::get('vat_enabled', false));
        $items = SaleItem::with('product','sale')
            ->whereHas('sale', fn($q) => $q->whereDate('sale_date', $date))
            ->when(!empty($hiddenProductIds), fn ($q) => $q->whereNotIn('product_id', $hiddenProductIds))
            ->get()
            ->reject(fn($i) => \App\Support\SecretPos::isHidden((float) ($i->sale?->total_amount ?? 0)));
        $lines = $items->map(function ($i) use ($rate, $enabled) {
            $lineTotal = $i->quantity * $i->unit_price;
            $vatExclusive = $enabled ? ($lineTotal * ($rate / 100)) : 0;
            $vatInclusive = $enabled ? ($lineTotal * ($rate / (100 + $rate))) : 0;
            return [
                'product' => $i->product?->name ?? 'Unknown',
                'invoice' => $i->sale?->invoice_no ?? ($i->sale?->id),
                'quantity' => (float) $i->quantity,
                'unit_price' => round((float) $i->unit_price, 2),
                'line_total' => round((float) $lineTotal, 2),
                'vat_exclusive' => round((float) $vatExclusive, 2),
                'vat_inclusive' => round((float) $vatInclusive, 2),
            ];
        });
        $totals = [
            'line_total' => $lines->sum('line_total'),
            'vat_exclusive' => $lines->sum('vat_exclusive'),
            'vat_inclusive' => $lines->sum('vat_inclusive'),
        ];
        $pdf = Pdf::loadView('reports.pdf.vat-day', compact('date','rate','enabled','lines','totals','controls'))
            ->setPaper('a4');
        return $pdf->download('vat-day-'.$date.'.pdf');
    }

    public function receivePdf(Request $request)
    {
        $controls = $this->visibilityControls($request);
        $this->ensureReportsVisible($controls);
        $hiddenProductIds = $this->hiddenProductIds($request);
        $hiddenCustomerIds = DashboardVisibilityService::hiddenCustomerIdsForUser($request->user());
        $hiddenSaleIds = DashboardVisibilityService::hiddenSaleIdsForUser($request->user());

        [$from, $to] = $this->dateRange($request);
        $payments = Payment::with(['customer','sale'])
            ->where(function ($q) { $q->whereNotNull('sale_id')->orWhereNotNull('customer_id'); })
            ->when(!empty($hiddenCustomerIds), fn ($q) => $q->where(fn ($cq) => $cq->whereNull('customer_id')->orWhereNotIn('customer_id', $hiddenCustomerIds)))
            ->when(!empty($hiddenSaleIds), fn ($q) => $q->where(fn ($sq) => $sq->whereNull('sale_id')->orWhereNotIn('sale_id', $hiddenSaleIds)))
            ->where(function ($q) use ($hiddenProductIds) {
                $q->whereNull('sale_id')
                    ->orWhereHas('sale', function ($sq) use ($hiddenProductIds) {
                        SecretPos::excludeHiddenSaleRanges($sq, 'total_amount');
                        if (!empty($hiddenProductIds)) {
                            $sq->whereDoesntHave('items', fn ($iq) => $iq->whereIn('product_id', $hiddenProductIds));
                        }
                    });
            })
            ->when($from, fn($q) => $q->whereDate('payment_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('payment_date', '<=', $to))
            ->orderBy('payment_date','desc')->get();
        $summary = [
            'total_received' => $payments->sum('amount'),
            'count' => $payments->count(),
            'by_method' => $payments->groupBy('payment_method')->map(fn($g) => $g->sum('amount')),
        ];
        $pdf = Pdf::loadView('reports.pdf.receive', compact('payments','summary','from','to','controls'))->setPaper('a4');
        return $pdf->download('receive-report.pdf');
    }

    public function receiveCsv(Request $request)
    {
        $controls = $this->visibilityControls($request);
        $this->ensureReportsVisible($controls);
        $hiddenProductIds = $this->hiddenProductIds($request);
        $hiddenCustomerIds = DashboardVisibilityService::hiddenCustomerIdsForUser($request->user());
        $hiddenSaleIds = DashboardVisibilityService::hiddenSaleIdsForUser($request->user());

        [$from, $to] = $this->dateRange($request);
        $payments = Payment::with(['customer','sale'])
            ->where(function ($q) { $q->whereNotNull('sale_id')->orWhereNotNull('customer_id'); })
            ->when(!empty($hiddenCustomerIds), fn ($q) => $q->where(fn ($cq) => $cq->whereNull('customer_id')->orWhereNotIn('customer_id', $hiddenCustomerIds)))
            ->when(!empty($hiddenSaleIds), fn ($q) => $q->where(fn ($sq) => $sq->whereNull('sale_id')->orWhereNotIn('sale_id', $hiddenSaleIds)))
            ->where(function ($q) use ($hiddenProductIds) {
                $q->whereNull('sale_id')
                    ->orWhereHas('sale', function ($sq) use ($hiddenProductIds) {
                        SecretPos::excludeHiddenSaleRanges($sq, 'total_amount');
                        if (!empty($hiddenProductIds)) {
                            $sq->whereDoesntHave('items', fn ($iq) => $iq->whereIn('product_id', $hiddenProductIds));
                        }
                    });
            })
            ->when($from, fn($q) => $q->whereDate('payment_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('payment_date', '<=', $to))
            ->orderBy('payment_date','desc')->get();
        $rows = [['Date','Customer','Sale','Method','Amount']];
        foreach ($payments as $p) {
            $hideInvoice = !empty($controls['hide_invoice_details']);
            $hidePaymentAmount = !empty($controls['hide_supplier_payments']) || $hideInvoice;
            $rows[] = [
                optional($p->payment_date)->toDateString(),
                !empty($controls['hide_supplier_names']) ? 'Hidden' : ($p->customer?->name ?? '-'),
                $hideInvoice ? 'HIDDEN' : ($p->sale?->invoice_no ?? '-'),
                $p->payment_method,
                $this->maskCurrencyForControls((float) $p->amount, $controls, $hidePaymentAmount),
            ];
        }
        $csv = fopen('php://temp','r+'); foreach ($rows as $r) { fputcsv($csv,$r); } rewind($csv);
        return response(stream_get_contents($csv), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="receive-report.csv"'
        ]);
    }

    public function debitPdf(Request $request)
    {
        $controls = $this->visibilityControls($request);
        $this->ensureReportsVisible($controls);
        $hiddenProductIds = $this->hiddenProductIds($request);
        $hiddenSupplierIds = DashboardVisibilityService::hiddenSupplierIdsForUser($request->user());

        [$from, $to] = $this->dateRange($request);
        $payments = Payment::with(['supplier','purchase'])
            ->where(function ($q) { $q->whereNotNull('purchase_id')->orWhereNotNull('supplier_id'); })
            ->when(!empty($hiddenSupplierIds), fn ($q) => $q->where(fn ($sq) => $sq->whereNull('supplier_id')->orWhereNotIn('supplier_id', $hiddenSupplierIds)))
            ->where(function ($q) use ($hiddenProductIds) {
                $q->whereNull('purchase_id')
                ->orWhereHas('purchase', function ($pq) use ($hiddenProductIds) {
                    SecretPos::excludeHiddenPurchaseRanges($pq, 'total_amount');
                    if (!empty($hiddenProductIds)) {
                        $pq->whereDoesntHave('items', fn ($iq) => $iq->whereIn('product_id', $hiddenProductIds));
                    }
                });
            })
            ->when($from, fn($q) => $q->whereDate('payment_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('payment_date', '<=', $to))
            ->orderBy('payment_date','desc')->get();
        $summary = [
            'total_debit' => $payments->sum('amount'),
            'count' => $payments->count(),
            'by_method' => $payments->groupBy('payment_method')->map(fn($g) => $g->sum('amount')),
        ];
        $pdf = Pdf::loadView('reports.pdf.debit', compact('payments','summary','from','to','controls'))->setPaper('a4');
        return $pdf->download('debit-report.pdf');
    }

    public function debitCsv(Request $request)
    {
        $controls = $this->visibilityControls($request);
        $this->ensureReportsVisible($controls);
        $hiddenProductIds = $this->hiddenProductIds($request);
        $hiddenSupplierIds = DashboardVisibilityService::hiddenSupplierIdsForUser($request->user());

        [$from, $to] = $this->dateRange($request);
        $payments = Payment::with(['supplier','purchase'])
            ->where(function ($q) { $q->whereNotNull('purchase_id')->orWhereNotNull('supplier_id'); })
            ->when(!empty($hiddenSupplierIds), fn ($q) => $q->where(fn ($sq) => $sq->whereNull('supplier_id')->orWhereNotIn('supplier_id', $hiddenSupplierIds)))
            ->where(function ($q) use ($hiddenProductIds) {
                $q->whereNull('purchase_id')
                ->orWhereHas('purchase', function ($pq) use ($hiddenProductIds) {
                    SecretPos::excludeHiddenPurchaseRanges($pq, 'total_amount');
                    if (!empty($hiddenProductIds)) {
                        $pq->whereDoesntHave('items', fn ($iq) => $iq->whereIn('product_id', $hiddenProductIds));
                    }
                });
            })
            ->when($from, fn($q) => $q->whereDate('payment_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('payment_date', '<=', $to))
            ->orderBy('payment_date','desc')->get();
        $rows = [['Date','Supplier','Purchase','Method','Amount']];
        foreach ($payments as $p) {
            $hideInvoice = !empty($controls['hide_invoice_details']);
            $hidePaymentAmount = !empty($controls['hide_supplier_payments']) || $hideInvoice;
            $rows[] = [
                optional($p->payment_date)->toDateString(),
                !empty($controls['hide_supplier_names']) ? 'Hidden' : ($p->supplier?->name ?? '-'),
                $hideInvoice ? 'HIDDEN' : ($p->purchase?->reference_no ?? '-'),
                $p->payment_method,
                $this->maskCurrencyForControls((float) $p->amount, $controls, $hidePaymentAmount),
            ];
        }
        $csv = fopen('php://temp','r+'); foreach ($rows as $r) { fputcsv($csv,$r); } rewind($csv);
        return response(stream_get_contents($csv), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="debit-report.csv"'
        ]);
    }

    /**
     * Customers due report.
     */
    public function customerDue(Request $request)
    {
        $controls = $this->visibilityControls($request);
        $this->ensureReportsVisible($controls);
                $hiddenProductIds = $this->hiddenProductIds($request);
        $hiddenCustomerIds = DashboardVisibilityService::hiddenCustomerIdsForUser($request->user());

        [$from, $to] = $this->dateRange($request);
                $customers = Customer::query()
                    ->when(!empty($hiddenCustomerIds), fn ($q) => $q->whereNotIn('id', $hiddenCustomerIds))
                    ->with(['sales' => function ($q) use ($from, $to, $hiddenProductIds, $request) {
            $q->where('due_amount', '>', 0)
              ->when($from, fn($qq) => $qq->whereDate('sale_date', '>=', $from))
                            ->when($to, fn($qq) => $qq->whereDate('sale_date', '<=', $to));
                        $this->applyHiddenRecordsToSalesQuery($q, $request);
                        SecretPos::excludeHiddenSaleRanges($q, 'total_amount');
                        if (!empty($hiddenProductIds)) {
                                $q->whereDoesntHave('items', fn ($sq) => $sq->whereIn('product_id', $hiddenProductIds));
                        }
        }])->get();
        $items = $customers->map(function ($c) {
            $due = $c->sales->sum('due_amount');
            return [
                'customer' => $c,
                'due' => $due,
                'invoices' => $c->sales->count(),
            ];
        })->filter(fn($row) => $row['due'] > 0)->sortByDesc('due');

        $summary = [
            'total_due' => $items->sum('due'),
            'customers' => $items->count(),
        ];

        return view('reports.customer-due', compact('items','summary','from','to','controls'));
    }

    public function customerDuePdf(Request $request)
    {
        $controls = $this->visibilityControls($request);
        $this->ensureReportsVisible($controls);
                $hiddenProductIds = $this->hiddenProductIds($request);
        $hiddenCustomerIds = DashboardVisibilityService::hiddenCustomerIdsForUser($request->user());

        [$from, $to] = $this->dateRange($request);
                $customers = Customer::query()
                    ->when(!empty($hiddenCustomerIds), fn ($q) => $q->whereNotIn('id', $hiddenCustomerIds))
                    ->with(['sales' => function ($q) use ($from, $to, $hiddenProductIds, $request) {
            $q->where('due_amount', '>', 0)
              ->when($from, fn($qq) => $qq->whereDate('sale_date', '>=', $from))
                            ->when($to, fn($qq) => $qq->whereDate('sale_date', '<=', $to));
                        $this->applyHiddenRecordsToSalesQuery($q, $request);
                        SecretPos::excludeHiddenSaleRanges($q, 'total_amount');
                        if (!empty($hiddenProductIds)) {
                                $q->whereDoesntHave('items', fn ($sq) => $sq->whereIn('product_id', $hiddenProductIds));
                        }
        }])->get();
        $items = $customers->map(function ($c) {
            $due = $c->sales->sum('due_amount');
            return [
                'name' => $c->name,
                'phone' => $c->phone,
                'due' => $due,
                'invoices' => $c->sales->count(),
            ];
        })->filter(fn($row) => $row['due'] > 0)->sortByDesc('due');

        $summary = [ 'total_due' => $items->sum('due'), 'customers' => $items->count() ];
        $pdf = Pdf::loadView('reports.pdf.customer-due', compact('items','summary','from','to','controls'))->setPaper('a4');
        return $pdf->download('customer-due-report.pdf');
    }

    public function customerDueCsv(Request $request)
    {
        $controls = $this->visibilityControls($request);
        $this->ensureReportsVisible($controls);
                $hiddenProductIds = $this->hiddenProductIds($request);
        $hiddenCustomerIds = DashboardVisibilityService::hiddenCustomerIdsForUser($request->user());

        [$from, $to] = $this->dateRange($request);
                $customers = Customer::query()
                    ->when(!empty($hiddenCustomerIds), fn ($q) => $q->whereNotIn('id', $hiddenCustomerIds))
                    ->with(['sales' => function ($q) use ($from, $to, $hiddenProductIds, $request) {
            $q->where('due_amount', '>', 0)
              ->when($from, fn($qq) => $qq->whereDate('sale_date', '>=', $from))
                            ->when($to, fn($qq) => $qq->whereDate('sale_date', '<=', $to));
                        $this->applyHiddenRecordsToSalesQuery($q, $request);
                        SecretPos::excludeHiddenSaleRanges($q, 'total_amount');
                        if (!empty($hiddenProductIds)) {
                                $q->whereDoesntHave('items', fn ($sq) => $sq->whereIn('product_id', $hiddenProductIds));
                        }
        }])->get();
        $items = $customers->map(function ($c) use ($controls) {
            return [
                !empty($controls['hide_supplier_names']) ? 'Hidden' : $c->name,
                $c->phone,
                $c->sales->count(),
                $this->maskCurrencyForControls(
                    (float) $c->sales->sum('due_amount'),
                    $controls,
                    !empty($controls['hide_supplier_payments']) || !empty($controls['hide_invoice_details'])
                ),
            ];
        });
        $rows = [['Customer','Phone','Invoices','Due']];
        foreach ($items as $r) { $rows[] = $r; }
        $csv = fopen('php://temp','r+'); foreach ($rows as $r) { fputcsv($csv,$r); } rewind($csv);
        return response(stream_get_contents($csv), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="customer-due-report.csv"'
        ]);
    }

    /**
     * Due Bills (invoices with outstanding balance).
     */
    public function dueBills(Request $request)
    {
        $controls = $this->visibilityControls($request);
        $this->ensureReportsVisible($controls);
        $hiddenProductIds = $this->hiddenProductIds($request);

        [$from, $to] = $this->dateRange($request);
        $sales = Sale::with(['customer'])
            ->where('due_amount', '>', 0)
            ->when($from, fn($q) => $q->whereDate('sale_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('sale_date', '<=', $to))
            ->when(!empty($hiddenProductIds), fn ($q) => $q->whereDoesntHave('items', fn ($sq) => $sq->whereIn('product_id', $hiddenProductIds)))
            ->orderBy('sale_date', 'desc');

        $sales = SecretPos::excludeHiddenSaleRanges($sales, 'total_amount')->get();

        $visible = $sales;
        $summary = [
            'total_due' => $visible->sum('due_amount'),
            'total_paid' => $visible->sum('paid_amount'),
            'total_amount' => $visible->sum('total_amount'),
            'count' => $visible->count(),
        ];

        $sales = $visible->values();
        if (PrivacyModeService::isActiveForUser($request->user()) && PrivacyModeService::shouldMaskForCurrentPage()) {
            PrivacyModeService::applyDailyInvoiceLabels($sales);
        }
        return view('reports.due-bills', compact('sales','summary','from','to','controls'));
    }

    public function dueBillsPdf(Request $request)
    {
        $controls = $this->visibilityControls($request);
        $this->ensureReportsVisible($controls);
        $hiddenProductIds = $this->hiddenProductIds($request);

        [$from, $to] = $this->dateRange($request);
        $sales = Sale::with(['customer'])
            ->where('due_amount', '>', 0)
            ->when($from, fn($q) => $q->whereDate('sale_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('sale_date', '<=', $to))
            ->when(!empty($hiddenProductIds), fn ($q) => $q->whereDoesntHave('items', fn ($sq) => $sq->whereIn('product_id', $hiddenProductIds)))
            ->orderBy('sale_date', 'desc');

        $sales = SecretPos::excludeHiddenSaleRanges($sales, 'total_amount')->get();

        $visible = $sales;
        $summary = [
            'total_due' => $visible->sum('due_amount'),
            'total_paid' => $visible->sum('paid_amount'),
            'total_amount' => $visible->sum('total_amount'),
            'count' => $visible->count(),
        ];

        $sales = $visible->values();
        if (PrivacyModeService::isActiveForUser($request->user()) && PrivacyModeService::shouldMaskForCurrentPage()) {
            PrivacyModeService::applyDailyInvoiceLabels($sales);
        }
        $pdf = Pdf::loadView('reports.pdf.due-bills', compact('sales','summary','from','to','controls'))
            ->setPaper('a4', 'portrait');
        return $pdf->download('due-bills-report.pdf');
    }

    public function dueBillsCsv(Request $request)
    {
        $controls = $this->visibilityControls($request);
        $this->ensureReportsVisible($controls);
        $hiddenProductIds = $this->hiddenProductIds($request);

        [$from, $to] = $this->dateRange($request);
        $sales = Sale::with(['customer'])
            ->where('due_amount', '>', 0)
            ->when($from, fn($q) => $q->whereDate('sale_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('sale_date', '<=', $to))
            ->when(!empty($hiddenProductIds), fn ($q) => $q->whereDoesntHave('items', fn ($sq) => $sq->whereIn('product_id', $hiddenProductIds)))
            ->orderBy('sale_date', 'desc');

        $sales = SecretPos::excludeHiddenSaleRanges($sales, 'total_amount')->get();

        if (PrivacyModeService::isActiveForUser($request->user()) && PrivacyModeService::shouldMaskForCurrentPage()) {
            PrivacyModeService::applyDailyInvoiceLabels($sales);
        }

        $rows = [['Date','Invoice','Customer','Total','Paid','Due','Status']];
        foreach ($sales as $s) {
            $hideInvoice = !empty($controls['hide_invoice_details']);
            $hidePayments = !empty($controls['hide_supplier_payments']) || $hideInvoice;
            $rows[] = [
                optional($s->sale_date)->toDateString(),
                $hideInvoice ? 'HIDDEN' : PrivacyModeService::displayInvoiceNumber($s),
                !empty($controls['hide_supplier_names']) ? 'Hidden' : ($s->customer?->name ?? 'Walk-in'),
                $this->maskCurrencyForControls((float) $s->total_amount, $controls, !empty($controls['hide_total_sales']) || $hideInvoice),
                $this->maskCurrencyForControls((float) $s->paid_amount, $controls, $hidePayments),
                $this->maskCurrencyForControls((float) $s->due_amount, $controls, $hidePayments),
                $s->payment_status,
            ];
        }

        $csv = fopen('php://temp','r+'); foreach ($rows as $r) { fputcsv($csv,$r); } rewind($csv);
        return response(stream_get_contents($csv), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="due-bills-report.csv"'
        ]);
    }

    /**
     * Live Rate Conversion page.
     */
    public function rates(Request $request)
    {
        $base = strtoupper($request->input('base', 'USD'));
        $targets = $request->input('targets');
        $defaultTargets = ['LKR','USD','EUR','GBP','INR','AED','AUD','CAD','JPY','CNY'];
        $symbols = $targets ? array_filter(array_map('strtoupper', explode(',', $targets))) : $defaultTargets;
        $symbols = array_values(array_unique($symbols));
        if (!in_array($base, $symbols, true)) { $symbols[] = $base; }
        $cacheKey = 'fx_rates_'.md5($base.'|'.implode(',', $symbols));
        $fetch = function () use ($base, $symbols) {
            $providers = [
                // exchangerate.host
                function () use ($base, $symbols) {
                    try {
                        $resp = Http::timeout(10)->retry(2, 500)->withOptions(['verify' => false])
                            ->get('https://api.exchangerate.host/latest', [ 'base' => $base, 'symbols' => implode(',', $symbols) ]);
                        if ($resp->failed()) return null;
                        $json = $resp->json();
                        return [ 'rates' => $json['rates'] ?? [], 'date' => $json['date'] ?? null ];
                    } catch (\Throwable $e) { return null; }
                },
                // open.er-api.com
                function () use ($base, $symbols) {
                    try {
                        $resp = Http::timeout(10)->retry(2, 500)->withOptions(['verify' => false])
                            ->get("https://open.er-api.com/v6/latest/{$base}");
                        if ($resp->failed()) return null;
                        $json = $resp->json();
                        $rates = $json['rates'] ?? [];
                        // filter to our symbols
                        $filtered = [];
                        foreach ($symbols as $s) { if (isset($rates[$s])) { $filtered[$s] = $rates[$s]; } }
                        return [ 'rates' => $filtered, 'date' => $json['time_last_update_utc'] ?? null ];
                    } catch (\Throwable $e) { return null; }
                },
                // frankfurter.app
                function () use ($base, $symbols) {
                    try {
                        $resp = Http::timeout(10)->retry(2, 500)->withOptions(['verify' => false])
                            ->get('https://api.frankfurter.app/latest', [ 'from' => $base, 'to' => implode(',', $symbols) ]);
                        if ($resp->failed()) return null;
                        $json = $resp->json();
                        return [ 'rates' => $json['rates'] ?? [], 'date' => $json['date'] ?? null ];
                    } catch (\Throwable $e) { return null; }
                },
                // exchangerate.host over HTTP (no TLS) — helpful for localhost cert issues
                function () use ($base, $symbols) {
                    try {
                        $resp = Http::timeout(10)->retry(2, 500)->withOptions(['verify' => false])
                            ->get('http://api.exchangerate.host/latest', [ 'base' => $base, 'symbols' => implode(',', $symbols) ]);
                        if ($resp->failed()) return null;
                        $json = $resp->json();
                        return [ 'rates' => $json['rates'] ?? [], 'date' => $json['date'] ?? null ];
                    } catch (\Throwable $e) { return null; }
                },
            ];

            foreach ($providers as $provider) {
                $res = $provider();
                if (is_array($res) && !empty($res['rates'])) {
                    // Ensure base currency rate is 1.0
                    $res['rates'][$base] = 1.0;
                    return [ 'success' => true, 'rates' => $res['rates'], 'date' => $res['date'], 'error' => null ];
                }
            }
            return [ 'success' => false, 'rates' => [$base => 1.0], 'date' => null, 'error' => 'Offline or blocked network. Showing base=1.0.' ];
        };

        if ($request->boolean('refresh')) {
            Cache::forget($cacheKey);
            $data = $fetch();
        } else {
            $data = Cache::remember($cacheKey, 600, $fetch);
        }

        $amount = (float) ($request->input('amount', 1));
        $convertTo = strtoupper($request->input('convert_to', $symbols[0] ?? 'LKR'));
        $converted = null;
        if (!empty($data['rates'][$convertTo])) {
            $converted = $amount * (float) $data['rates'][$convertTo];
        }

        $currencies = [
            'USD' => 'US Dollar', 'LKR' => 'Sri Lankan Rupee', 'EUR' => 'Euro', 'GBP' => 'British Pound',
            'INR' => 'Indian Rupee', 'AED' => 'UAE Dirham', 'AUD' => 'Australian Dollar', 'CAD' => 'Canadian Dollar',
            'JPY' => 'Japanese Yen', 'CNY' => 'Chinese Yuan'
        ];
        // Generic quick pair check (like Google): 1 FROM = X TO
        $pairFrom = strtoupper($request->input('pair_from', 'USD'));
        $pairTo = strtoupper($request->input('pair_to', 'LKR'));
        $pairAmount = (float) ($request->input('pair_amount', 1));
        $pairRate = null;
        if ($data['success']) {
            $rates = $data['rates'] ?? [];
            $rFrom = $rates[$pairFrom] ?? null;
            $rTo = $rates[$pairTo] ?? null;
            if ($pairFrom === $pairTo) {
                $pairRate = 1.0;
            } elseif ($pairFrom === $base && $rTo) {
                $pairRate = (float) $rTo; // 1 base -> rTo
            } elseif ($pairTo === $base && $rFrom) {
                $pairRate = $rFrom ? (1.0 / (float) $rFrom) : null; // 1 from -> base
            } elseif ($rFrom && $rTo) {
                // cross: 1 from -> rTo/rFrom
                $pairRate = (float) $rTo / (float) $rFrom;
            }
        }
        $pairResult = !is_null($pairRate) ? ($pairAmount * $pairRate) : null;
        $pairInverse = !is_null($pairRate) && $pairRate > 0 ? (1.0 / $pairRate) : null;

        return view('reports.rates', compact('base','symbols','data','amount','convertTo','converted','currencies','pairFrom','pairTo','pairAmount','pairRate','pairResult','pairInverse'));
    }

    /** Save manual fallback USD->LKR rate */
    public function saveManualRate(Request $request)
    {
        $val = (float) $request->input('usd_lkr');
        if ($val <= 0) {
            return back()->with('error', 'Please enter a valid USD→LKR rate (> 0).');
        }
        Setting::set('fx_usd_lkr', (string) $val, 'text', 'general');
        return back()->with('success', 'Manual USD→LKR rate saved.');
    }
}
