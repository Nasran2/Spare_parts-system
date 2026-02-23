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
use App\Models\Setting;

class ReportController extends Controller
{
    /**
     * Sales report.
     */
    public function sales(Request $request)
    {
        [$from, $to] = $this->dateRange($request);
        $categoryId = $request->input('category_id');
        $query = Sale::with(['customer', 'items.product.category'])
            ->when($from, fn($q) => $q->whereDate('sale_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('sale_date', '<=', $to))
            ->when($categoryId, function ($q) use ($categoryId) {
                $q->whereHas('items.product', fn($pq) => $pq->where('category_id', $categoryId));
            })
            ->orderBy('sale_date', 'desc');

        $sales = $query->get();
        $visible = $sales->reject(fn($s) => \App\Support\SecretPos::isHidden((float) $s->total_amount));

        $summary = [
            'total_sales' => $visible->sum('total_amount'),
            'total_paid' => $visible->sum('paid_amount'),
            'total_due' => $visible->sum('due_amount'),
            'count' => $visible->count(),
            'filtered_category' => $categoryId ? optional(\App\Models\Category::find($categoryId))->name : null,
        ];

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

        $categories = \App\Models\Category::orderBy('name')->get();
        return view('reports.sales', compact('sales', 'summary', 'daily', 'from', 'to', 'categories', 'categoryId'));
    }

    public function salesPdf(Request $request)
    {
        [$from, $to] = $this->dateRange($request);
        $categoryId = $request->input('category_id');
        $sales = Sale::with(['customer'])
            ->when($from, fn($q) => $q->whereDate('sale_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('sale_date', '<=', $to))
            ->when($categoryId, function ($q) use ($categoryId) {
                $q->whereHas('items.product', fn($pq) => $pq->where('category_id', $categoryId));
            })
            ->orderBy('sale_date', 'desc')
            ->get();
        $visible = $sales->reject(fn($s) => \App\Support\SecretPos::isHidden((float) $s->total_amount));
        $summary = [
            'total_sales' => $visible->sum('total_amount'),
            'total_paid' => $visible->sum('paid_amount'),
            'total_due' => $visible->sum('due_amount'),
            'count' => $visible->count(),
        ];

        $pdf = Pdf::loadView('reports.pdf.sales', compact('sales','summary','from','to'))->setPaper('a4', 'portrait');
        return $pdf->download('sales-report.pdf');
    }

    public function salesCsv(Request $request)
    {
        [$from, $to] = $this->dateRange($request);
        $categoryId = $request->input('category_id');
        $sales = Sale::with(['customer'])
            ->when($from, fn($q) => $q->whereDate('sale_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('sale_date', '<=', $to))
            ->when($categoryId, function ($q) use ($categoryId) {
                $q->whereHas('items.product', fn($pq) => $pq->where('category_id', $categoryId));
            })
            ->orderBy('sale_date', 'desc')
            ->get();
        $rows = [['Date','Invoice','Customer','Total','Paid','Due','Status']];
        foreach ($sales as $s) {
            $rows[] = [
                optional($s->sale_date)->toDateString(),
                $s->sale_no,
                $s->customer?->name ?? 'Walk-in',
                \App\Support\SecretPos::maskForSale((float) $s->total_amount, (float) $s->total_amount),
                \App\Support\SecretPos::maskForSale((float) $s->total_amount, (float) $s->paid_amount),
                \App\Support\SecretPos::maskForSale((float) $s->total_amount, (float) $s->due_amount),
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
        [$from, $to] = $this->dateRange($request);
        $categoryId = $request->input('category_id');
        $query = Purchase::with(['supplier', 'items.product.category'])
            ->when($from, fn($q) => $q->whereDate('purchase_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('purchase_date', '<=', $to))
            ->when($categoryId, function ($q) use ($categoryId) {
                $q->whereHas('items.product', fn($pq) => $pq->where('category_id', $categoryId));
            })
            ->orderBy('purchase_date', 'desc');

        $purchases = $query->get();

        $summary = [
            'total_purchases' => $purchases->sum('total_amount'),
            'total_paid' => $purchases->sum('paid_amount'),
            'total_due' => $purchases->sum('due_amount'),
            'count' => $purchases->count(),
        ];

        $categories = \App\Models\Category::orderBy('name')->get();
        return view('reports.purchase', compact('purchases', 'summary', 'from', 'to', 'categories', 'categoryId'));
    }

    public function purchasePdf(Request $request)
    {
        [$from, $to] = $this->dateRange($request);
        $categoryId = $request->input('category_id');
        $purchases = Purchase::with(['supplier'])
            ->when($from, fn($q) => $q->whereDate('purchase_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('purchase_date', '<=', $to))
            ->when($categoryId, function ($q) use ($categoryId) {
                $q->whereHas('items.product', fn($pq) => $pq->where('category_id', $categoryId));
            })
            ->orderBy('purchase_date', 'desc')
            ->get();
        $summary = [
            'total_purchases' => $purchases->sum('total_amount'),
            'total_paid' => $purchases->sum('paid_amount'),
            'total_due' => $purchases->sum('due_amount'),
            'count' => $purchases->count(),
        ];
        $pdf = Pdf::loadView('reports.pdf.purchase', compact('purchases','summary','from','to'))->setPaper('a4', 'portrait');
        return $pdf->download('purchase-report.pdf');
    }

    public function purchaseCsv(Request $request)
    {
        [$from, $to] = $this->dateRange($request);
        $categoryId = $request->input('category_id');
        $purchases = Purchase::with(['supplier'])
            ->when($from, fn($q) => $q->whereDate('purchase_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('purchase_date', '<=', $to))
            ->when($categoryId, function ($q) use ($categoryId) {
                $q->whereHas('items.product', fn($pq) => $pq->where('category_id', $categoryId));
            })
            ->orderBy('purchase_date', 'desc')
            ->get();
        $rows = [['Date','PO #','Supplier','Total','Paid','Due','Status']];
        foreach ($purchases as $p) {
            $rows[] = [
                optional($p->purchase_date)->toDateString(),
                $p->purchase_no,
                $p->supplier?->name ?? 'N/A',
                number_format($p->total_amount,2),
                number_format($p->paid_amount,2),
                number_format($p->due_amount,2),
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
        [$from, $to] = $this->dateRange($request);
        $query = Expense::with(['category'])->when($from, fn($q) => $q->whereDate('expense_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('expense_date', '<=', $to))
            ->orderBy('expense_date', 'desc');

        $expenses = $query->get();

        $summary = [
            'total_expense' => $expenses->sum('amount'),
            'count' => $expenses->count(),
        ];

        $byCategory = $expenses->groupBy(fn($e) => $e->category?->name ?? 'Uncategorized')->map(function ($group) {
            return [
                'category' => $group->first()->category?->name ?? 'Uncategorized',
                'total' => $group->sum('amount'),
                'count' => $group->count(),
            ];
        })->sortByDesc('total')->values();

        return view('reports.expense', compact('expenses', 'summary', 'byCategory', 'from', 'to'));
    }

    public function expensePdf(Request $request)
    {
        [$from, $to] = $this->dateRange($request);
        $expenses = Expense::with('category')
            ->when($from, fn($q) => $q->whereDate('expense_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('expense_date', '<=', $to))
            ->orderBy('expense_date','desc')->get();
        $summary = [ 'total_expense' => $expenses->sum('amount'), 'count' => $expenses->count() ];
        $pdf = Pdf::loadView('reports.pdf.expense', compact('expenses','summary','from','to'))->setPaper('a4');
        return $pdf->download('expense-report.pdf');
    }

    public function expenseCsv(Request $request)
    {
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
                number_format((float) $e->amount,2),
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
        $selectedCategoryId = $request->filled('category_id') ? (int) $request->input('category_id') : null;
        $selectedBrandId = $request->filled('brand_id') ? (int) $request->input('brand_id') : null;
        $lowStockOnly = $request->boolean('low_stock');

        $products = $this->stockBaseQuery($selectedCategoryId, $selectedBrandId, $lowStockOnly)->get();
        $items = $this->mapStockItems($products);

        $summary = [
            'total_products' => $products->count(),
            'low_stock' => $items->where('low_stock', true)->count(),
            'total_stock' => $products->sum('stock_quantity'),
        ];

        $categories = \App\Models\Category::orderBy('name')->get(['id', 'name']);
        $brands = \App\Models\Brand::orderBy('name')->get(['id', 'name']);

        return view('reports.stock', compact(
            'items',
            'summary',
            'categories',
            'brands',
            'selectedCategoryId',
            'selectedBrandId',
            'lowStockOnly'
        ));
    }

    public function stockPdf(Request $request)
    {
        $selectedCategoryId = $request->filled('category_id') ? (int) $request->input('category_id') : null;
        $selectedBrandId = $request->filled('brand_id') ? (int) $request->input('brand_id') : null;
        $lowStockOnly = $request->boolean('low_stock');

        $products = $this->stockBaseQuery($selectedCategoryId, $selectedBrandId, $lowStockOnly)->get();
        $items = $products->map(function ($p) {
            $purchasedQty = $p->purchaseItems->sum('quantity');
            $soldQty = $p->saleItems->sum('quantity');
            $isLowStock = (float) ($p->stock_quantity ?? 0) <= (float) ($p->alert_quantity ?? 0);
            return [
                'name' => $p->name,
                'categories' => $p->categories->pluck('name')->join(', '),
                'purchased' => $purchasedQty,
                'sold' => $soldQty,
                'current_stock' => $p->stock_quantity,
                'low_stock' => $isLowStock,
            ];
        });
        $summary = [
            'total_products' => $products->count(),
            'low_stock' => $items->where('low_stock', true)->count(),
            'total_stock' => $products->sum('stock_quantity'),
        ];
        $pdf = Pdf::loadView('reports.pdf.stock', compact('items','summary'))->setPaper('a4', 'portrait');
        return $pdf->download('stock-report.pdf');
    }

    public function stockCsv(Request $request)
    {
        $selectedCategoryId = $request->filled('category_id') ? (int) $request->input('category_id') : null;
        $selectedBrandId = $request->filled('brand_id') ? (int) $request->input('brand_id') : null;
        $lowStockOnly = $request->boolean('low_stock');

        $products = $this->stockBaseQuery($selectedCategoryId, $selectedBrandId, $lowStockOnly)->get();
        $rows = [['Product','Category','Purchased Qty','Sold Qty','Current Stock','Status']];
        foreach ($products as $p) {
            $isLowStock = (float) ($p->stock_quantity ?? 0) <= (float) ($p->alert_quantity ?? 0);
            $rows[] = [
                $p->name,
                $p->categories->pluck('name')->join(', '),
                $p->purchaseItems->sum('quantity'),
                $p->saleItems->sum('quantity'),
                $p->stock_quantity,
                $isLowStock ? 'Low' : 'OK',
            ];
        }
        $csv = fopen('php://temp','r+'); foreach ($rows as $r) { fputcsv($csv,$r); } rewind($csv);
        return response(stream_get_contents($csv), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="stock-report.csv"'
        ]);
    }

    private function stockBaseQuery(?int $categoryId, ?int $brandId, bool $lowStockOnly)
    {
        $query = Product::with(['category', 'categories', 'brand', 'brands', 'unit', 'saleItems', 'purchaseItems']);

        if ($categoryId) {
            $query->where(function ($q) use ($categoryId) {
                $q->where('category_id', $categoryId)
                  ->orWhereHas('categories', fn($cq) => $cq->whereKey($categoryId));
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
        [$from, $to] = $this->dateRange($request);
        $sales = Sale::when($from, fn($q) => $q->whereDate('sale_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('sale_date', '<=', $to))->get();

        $visible = $sales->reject(fn($s) => \App\Support\SecretPos::isHidden((float) $s->total_amount));
        $salesRevenue = $visible->sum('total_amount');

        $saleItemIds = $visible->pluck('id');
        $saleItems = SaleItem::with('product')->whereIn('sale_id', $saleItemIds)->get();
        $cogs = $saleItems->sum(fn($i) => $i->quantity * ($i->product?->cost_price ?? 0));
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

        return view('reports.profit-loss', compact('summary', 'from', 'to'));
    }

    public function profitLossPdf(Request $request)
    {
        [$from, $to] = $this->dateRange($request);
        $sales = Sale::when($from, fn($q) => $q->whereDate('sale_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('sale_date', '<=', $to))->get();
        $visible = $sales->reject(fn($s) => \App\Support\SecretPos::isHidden((float) $s->total_amount));
        $salesRevenue = $visible->sum('total_amount');
        $saleItems = SaleItem::with('product')->whereIn('sale_id', $visible->pluck('id'))->get();
        $cogs = $saleItems->sum(fn($i) => $i->quantity * ($i->product?->cost_price ?? 0));
        $grossProfit = $salesRevenue - $cogs;
        $expenses = Expense::when($from, fn($q) => $q->whereDate('expense_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('expense_date', '<=', $to))->get();
        $expenseTotal = $expenses->sum('amount');
        $netProfit = $grossProfit - $expenseTotal;
        $summary = compact('salesRevenue','cogs','grossProfit','expenseTotal','netProfit');
        $pdf = Pdf::loadView('reports.pdf.profit-loss', compact('summary','from','to'))->setPaper('a4');
        return $pdf->download('profit-loss-report.pdf');
    }

    public function profitLossCsv(Request $request)
    {
        [$from, $to] = $this->dateRange($request);
        $sales = Sale::when($from, fn($q) => $q->whereDate('sale_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('sale_date', '<=', $to))->get();
        $visible = $sales->reject(fn($s) => \App\Support\SecretPos::isHidden((float) $s->total_amount));
        $salesRevenue = $visible->sum('total_amount');
        $saleItems = SaleItem::with('product')->whereIn('sale_id', $visible->pluck('id'))->get();
        $cogs = $saleItems->sum(fn($i) => $i->quantity * ($i->product?->cost_price ?? 0));
        $grossProfit = $salesRevenue - $cogs;
        $expenses = Expense::when($from, fn($q) => $q->whereDate('expense_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('expense_date', '<=', $to))->get();
        $expenseTotal = $expenses->sum('amount');
        $netProfit = $grossProfit - $expenseTotal;
        $rows = [
            ['Metric','Value'],
            ['Sales Revenue', number_format($salesRevenue,2)],
            ['COGS', number_format($cogs,2)],
            ['Gross Profit', number_format($grossProfit,2)],
            ['Expenses', number_format($expenseTotal,2)],
            ['Net Profit', number_format($netProfit,2)],
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
        [$from, $to] = $this->dateRange($request);
        $itemsQuery = SaleItem::with('product')
            ->when($from || $to, function ($q) use ($from, $to) {
                $q->whereHas('sale', function ($sq) use ($from, $to) {
                    $sq->when($from, fn($qq) => $qq->whereDate('sale_date', '>=', $from))
                        ->when($to, fn($qq) => $qq->whereDate('sale_date', '<=', $to));
                });
            });

        $items = $itemsQuery->get();
        $top = $items->groupBy('product_id')->map(function ($group) {
            $product = $group->first()->product;
            return [
                'product' => $product,
                'quantity' => $group->sum('quantity'),
                'revenue' => $group->sum(fn($i) => $i->quantity * $i->unit_price),
            ];
        })->sortByDesc('quantity')->values()->take(15);

        return view('reports.trending', compact('top', 'from', 'to'));
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
        [$from, $to] = $this->dateRange($request);
        $rate = (float) (Setting::get('vat_rate', 0));
        $enabled = (bool) (Setting::get('vat_enabled', false));

        $sales = Sale::when($from, fn($q) => $q->whereDate('sale_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('sale_date', '<=', $to))
            ->orderBy('sale_date', 'desc')
            ->get(['id','sale_date','total_amount']);
        $visible = $sales->reject(fn($s) => \App\Support\SecretPos::isHidden((float) $s->total_amount));
        $totalFinal = $visible->sum('total_amount');
        $vatExclusive = $enabled ? ($totalFinal * ($rate / 100)) : 0; // if price stored exclusive of VAT added
        $vatInclusive = $enabled ? ($totalFinal * ($rate / (100 + $rate))) : 0; // if price stored inclusive

        $dailyTemp = [];
        foreach ($visible as $s) {
            $key = $s->sale_date ? $s->sale_date->toDateString() : null;
            if (!$key) { continue; }
            if (!isset($dailyTemp[$key])) { $dailyTemp[$key] = 0.0; }
            $dailyTemp[$key] += (float) $s->total_amount;
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

        return view('reports.vat', compact('summary', 'daily', 'from', 'to'));
    }

    /**
     * Incoming payments (receipts from customers).
     */
    public function receive(Request $request)
    {
        [$from, $to] = $this->dateRange($request);
        $query = Payment::with(['customer', 'sale'])
            ->where(function ($q) {
                $q->whereNotNull('sale_id')->orWhereNotNull('customer_id');
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

        return view('reports.receive', compact('payments', 'summary', 'daily', 'from', 'to'));
    }

    /**
     * Outgoing payments (debits to suppliers).
     */
    public function debit(Request $request)
    {
        [$from, $to] = $this->dateRange($request);
        $query = Payment::with(['supplier', 'purchase'])
            ->where(function ($q) {
                $q->whereNotNull('purchase_id')->orWhereNotNull('supplier_id');
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

        return view('reports.debit', compact('payments', 'summary', 'daily', 'from', 'to'));
    }

    /** PDF exports */
    public function vatPdf(Request $request)
    {
        [$from, $to] = $this->dateRange($request);
        $rate = (float) (Setting::get('vat_rate', 0));
        $enabled = (bool) (Setting::get('vat_enabled', false));
        $sales = Sale::when($from, fn($q) => $q->whereDate('sale_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('sale_date', '<=', $to))
            ->get(['id','sale_date','total_amount']);
        $visible = $sales->reject(fn($s) => \App\Support\SecretPos::isHidden((float) $s->total_amount));
        $totalFinal = $visible->sum('total_amount');
        $vatExclusive = $enabled ? ($totalFinal * ($rate / 100)) : 0;
        $vatInclusive = $enabled ? ($totalFinal * ($rate / (100 + $rate))) : 0;
        $dailyTemp = [];
        foreach ($visible as $s) {
            $key = $s->sale_date ? $s->sale_date->toDateString() : null;
            if (!$key) { continue; }
            if (!isset($dailyTemp[$key])) { $dailyTemp[$key] = 0.0; }
            $dailyTemp[$key] += (float) $s->total_amount;
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
        $pdf = Pdf::loadView('reports.pdf.vat', compact('summary','daily','from','to'))->setPaper('a4');
        return $pdf->download('vat-report.pdf');
    }

    public function vatCsv(Request $request)
    {
        [$from, $to] = $this->dateRange($request);
        $rate = (float) (Setting::get('vat_rate', 0));
        $enabled = (bool) (Setting::get('vat_enabled', false));
        $sales = Sale::when($from, fn($q) => $q->whereDate('sale_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('sale_date', '<=', $to))
            ->get(['id','sale_date','total_amount']);
        $visible = $sales->reject(fn($s) => \App\Support\SecretPos::isHidden((float) $s->total_amount));
        $rows = [ ['Date','Sales Total','VAT (Exclusive)','VAT (Inclusive)'] ];
        $byDate = [];
        foreach ($visible as $s) {
            $key = $s->sale_date ? $s->sale_date->toDateString() : null;
            if (!$key) { continue; }
            if (!isset($byDate[$key])) { $byDate[$key] = 0.0; }
            $byDate[$key] += (float) $s->total_amount;
        }
        foreach ($byDate as $date => $final) {
            $ex = $enabled ? ($final * ($rate / 100)) : 0;
            $inc = $enabled ? ($final * ($rate / (100 + $rate))) : 0;
            $rows[] = [$date, number_format($final, 2), number_format($ex, 2), number_format($inc, 2)];
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
        $date = $request->query('date');
        if (!$date) { return response()->json(['error' => 'date required'], 422); }
        $rate = (float) (Setting::get('vat_rate', 0));
        $enabled = (bool) (Setting::get('vat_enabled', false));
        $items = SaleItem::with('product','sale')
            ->whereHas('sale', fn($q) => $q->whereDate('sale_date', $date))
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
        return response()->json([
            'date' => $date,
            'rate' => $rate,
            'enabled' => $enabled,
            'items' => $lines,
            'totals' => [
                'line_total' => $lines->sum('line_total'),
                'vat_exclusive' => $lines->sum('vat_exclusive'),
                'vat_inclusive' => $lines->sum('vat_inclusive'),
            ]
        ]);
    }

    public function vatDayPdf(Request $request)
    {
        $date = $request->query('date');
        if (!$date) { abort(422, 'date required'); }
        $rate = (float) (Setting::get('vat_rate', 0));
        $enabled = (bool) (Setting::get('vat_enabled', false));
        $items = SaleItem::with('product','sale')
            ->whereHas('sale', fn($q) => $q->whereDate('sale_date', $date))
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
        $pdf = Pdf::loadView('reports.pdf.vat-day', compact('date','rate','enabled','lines','totals'))
            ->setPaper('a4');
        return $pdf->download('vat-day-'.$date.'.pdf');
    }

    public function receivePdf(Request $request)
    {
        [$from, $to] = $this->dateRange($request);
        $payments = Payment::with(['customer','sale'])
            ->where(function ($q) { $q->whereNotNull('sale_id')->orWhereNotNull('customer_id'); })
            ->when($from, fn($q) => $q->whereDate('payment_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('payment_date', '<=', $to))
            ->orderBy('payment_date','desc')->get();
        $summary = [
            'total_received' => $payments->sum('amount'),
            'count' => $payments->count(),
            'by_method' => $payments->groupBy('payment_method')->map(fn($g) => $g->sum('amount')),
        ];
        $pdf = Pdf::loadView('reports.pdf.receive', compact('payments','summary','from','to'))->setPaper('a4');
        return $pdf->download('receive-report.pdf');
    }

    public function receiveCsv(Request $request)
    {
        [$from, $to] = $this->dateRange($request);
        $payments = Payment::with(['customer','sale'])
            ->where(function ($q) { $q->whereNotNull('sale_id')->orWhereNotNull('customer_id'); })
            ->when($from, fn($q) => $q->whereDate('payment_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('payment_date', '<=', $to))
            ->orderBy('payment_date','desc')->get();
        $rows = [['Date','Customer','Sale','Method','Amount']];
        foreach ($payments as $p) {
            $rows[] = [optional($p->payment_date)->toDateString(), $p->customer?->name, $p->sale?->invoice_no, $p->payment_method, number_format((float) $p->amount, 2)];
        }
        $csv = fopen('php://temp','r+'); foreach ($rows as $r) { fputcsv($csv,$r); } rewind($csv);
        return response(stream_get_contents($csv), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="receive-report.csv"'
        ]);
    }

    public function debitPdf(Request $request)
    {
        [$from, $to] = $this->dateRange($request);
        $payments = Payment::with(['supplier','purchase'])
            ->where(function ($q) { $q->whereNotNull('purchase_id')->orWhereNotNull('supplier_id'); })
            ->when($from, fn($q) => $q->whereDate('payment_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('payment_date', '<=', $to))
            ->orderBy('payment_date','desc')->get();
        $summary = [
            'total_debit' => $payments->sum('amount'),
            'count' => $payments->count(),
            'by_method' => $payments->groupBy('payment_method')->map(fn($g) => $g->sum('amount')),
        ];
        $pdf = Pdf::loadView('reports.pdf.debit', compact('payments','summary','from','to'))->setPaper('a4');
        return $pdf->download('debit-report.pdf');
    }

    public function debitCsv(Request $request)
    {
        [$from, $to] = $this->dateRange($request);
        $payments = Payment::with(['supplier','purchase'])
            ->where(function ($q) { $q->whereNotNull('purchase_id')->orWhereNotNull('supplier_id'); })
            ->when($from, fn($q) => $q->whereDate('payment_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('payment_date', '<=', $to))
            ->orderBy('payment_date','desc')->get();
        $rows = [['Date','Supplier','Purchase','Method','Amount']];
        foreach ($payments as $p) {
            $rows[] = [optional($p->payment_date)->toDateString(), $p->supplier?->name, $p->purchase?->reference_no, $p->payment_method, number_format((float) $p->amount, 2)];
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
        [$from, $to] = $this->dateRange($request);
        $customers = Customer::with(['sales' => function ($q) use ($from, $to) {
            $q->where('due_amount', '>', 0)
              ->when($from, fn($qq) => $qq->whereDate('sale_date', '>=', $from))
              ->when($to, fn($qq) => $qq->whereDate('sale_date', '<=', $to));
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

        return view('reports.customer-due', compact('items','summary','from','to'));
    }

    public function customerDuePdf(Request $request)
    {
        [$from, $to] = $this->dateRange($request);
        $customers = Customer::with(['sales' => function ($q) use ($from, $to) {
            $q->where('due_amount', '>', 0)
              ->when($from, fn($qq) => $qq->whereDate('sale_date', '>=', $from))
              ->when($to, fn($qq) => $qq->whereDate('sale_date', '<=', $to));
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
        $pdf = Pdf::loadView('reports.pdf.customer-due', compact('items','summary','from','to'))->setPaper('a4');
        return $pdf->download('customer-due-report.pdf');
    }

    public function customerDueCsv(Request $request)
    {
        [$from, $to] = $this->dateRange($request);
        $customers = Customer::with(['sales' => function ($q) use ($from, $to) {
            $q->where('due_amount', '>', 0)
              ->when($from, fn($qq) => $qq->whereDate('sale_date', '>=', $from))
              ->when($to, fn($qq) => $qq->whereDate('sale_date', '<=', $to));
        }])->get();
        $items = $customers->map(function ($c) {
            return [ $c->name, $c->phone, $c->sales->count(), number_format($c->sales->sum('due_amount'), 2) ];
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
        [$from, $to] = $this->dateRange($request);
        $sales = Sale::with(['customer'])
            ->where('due_amount', '>', 0)
            ->when($from, fn($q) => $q->whereDate('sale_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('sale_date', '<=', $to))
            ->orderBy('sale_date', 'desc')
            ->get();

        $visible = $sales->reject(fn($s) => \App\Support\SecretPos::isHidden((float) $s->total_amount));
        $summary = [
            'total_due' => $visible->sum('due_amount'),
            'total_paid' => $visible->sum('paid_amount'),
            'total_amount' => $visible->sum('total_amount'),
            'count' => $visible->count(),
        ];

        return view('reports.due-bills', compact('sales','summary','from','to'));
    }

    public function dueBillsPdf(Request $request)
    {
        [$from, $to] = $this->dateRange($request);
        $sales = Sale::with(['customer'])
            ->where('due_amount', '>', 0)
            ->when($from, fn($q) => $q->whereDate('sale_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('sale_date', '<=', $to))
            ->orderBy('sale_date', 'desc')
            ->get();

        $visible = $sales->reject(fn($s) => \App\Support\SecretPos::isHidden((float) $s->total_amount));
        $summary = [
            'total_due' => $visible->sum('due_amount'),
            'total_paid' => $visible->sum('paid_amount'),
            'total_amount' => $visible->sum('total_amount'),
            'count' => $visible->count(),
        ];

        $pdf = Pdf::loadView('reports.pdf.due-bills', compact('sales','summary','from','to'))
            ->setPaper('a4', 'portrait');
        return $pdf->download('due-bills-report.pdf');
    }

    public function dueBillsCsv(Request $request)
    {
        [$from, $to] = $this->dateRange($request);
        $sales = Sale::with(['customer'])
            ->where('due_amount', '>', 0)
            ->when($from, fn($q) => $q->whereDate('sale_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('sale_date', '<=', $to))
            ->orderBy('sale_date', 'desc')
            ->get();

        $rows = [['Date','Invoice','Customer','Total','Paid','Due','Status']];
        foreach ($sales as $s) {
            $rows[] = [
                optional($s->sale_date)->toDateString(),
                $s->sale_no,
                $s->customer?->name ?? 'Walk-in',
                \App\Support\SecretPos::maskForSale((float) $s->total_amount, (float) $s->total_amount),
                \App\Support\SecretPos::maskForSale((float) $s->total_amount, (float) $s->paid_amount),
                \App\Support\SecretPos::maskForSale((float) $s->total_amount, (float) $s->due_amount),
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
