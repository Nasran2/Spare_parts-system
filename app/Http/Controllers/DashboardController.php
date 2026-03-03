<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Purchase;
use App\Models\Expense;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        try {
            // Get filter dates (default to today)
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            $todayStr = now()->timezone(config('app.timezone', 'Asia/Colombo'))->toDateString();
            $useToday = empty($startDate) || empty($endDate);
            if ($useToday) {
                $startDate = $todayStr;
                $endDate = $todayStr;
            }

            // Prepare helper to exclude hidden ranges based on settings
            $ranges = (array) \App\Models\Setting::get('secretpos.hidden_ranges', []);
            $applyExclude = function ($query, $column = 'total_amount') use ($ranges) {
                if (empty($ranges)) { return $query; }
                return $query->where(function ($q) use ($ranges, $column) {
                    foreach ($ranges as $r) {
                        if (!($r['hide'] ?? false)) { continue; }
                        $min = (int) ($r['min'] ?? 0);
                        $max = (int) ($r['max'] ?? PHP_INT_MAX);
                        $q->whereNotBetween($column, [$min, $max]);
                    }
                });
            };

            // Total Sales (exclude hidden ranges)
            $salesQuery = $applyExclude(Sale::where('sale_type', 'sale'));
            $salesQuery = $useToday
                ? $salesQuery->whereDate('sale_date', $todayStr)
                : $salesQuery->whereDate('sale_date', '>=', $startDate)->whereDate('sale_date', '<=', $endDate);
            $salesRevenue = $salesQuery->sum('total_amount') ?? 0;
            $totalSales = $salesRevenue;
            // Secret POS: override total sales amount if configured
            $overrideTotal = (float) \App\Models\Setting::get('secretpos.override_total_sales_amount', 0);
            if ($overrideTotal > 0) {
                $totalSales = $overrideTotal;
            }

            // Total Purchase
            $purchaseQuery = Purchase::query();
            $purchaseQuery = $useToday
                ? $purchaseQuery->whereDate('purchase_date', $todayStr)
                : $purchaseQuery->whereDate('purchase_date', '>=', $startDate)->whereDate('purchase_date', '<=', $endDate);
            $totalPurchase = $purchaseQuery->sum('total_amount') ?? 0;

            // Total Expenses
            $expenseQuery = Expense::query();
            $expenseQuery = $useToday
                ? $expenseQuery->whereDate('expense_date', $todayStr)
                : $expenseQuery->whereDate('expense_date', '>=', $startDate)->whereDate('expense_date', '<=', $endDate);
            $totalExpenses = $expenseQuery->sum('amount') ?? 0;

            // Sales Profit = Sales Revenue - COGS
            // Important: use sales.total_amount (after discount) for revenue,
            // not sale_items.total (pre-discount), otherwise dashboard profit is overstated.
            $returnAgg = DB::table('sale_return_items')
                ->select(
                    'sale_item_id',
                    DB::raw('SUM(quantity) as returned_qty'),
                    DB::raw('SUM(total) as returned_total')
                )
                ->groupBy('sale_item_id');

            $cogsQuery = DB::table('sale_items')
                ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
                ->join('products', 'sale_items.product_id', '=', 'products.id')
                ->leftJoinSub($returnAgg, 'r', function ($join) {
                    $join->on('sale_items.id', '=', 'r.sale_item_id');
                })
                ->where('sales.sale_type', 'sale');

            $cogsQuery = $applyExclude($cogsQuery, 'sales.total_amount');
            $cogsQuery = $useToday
                ? $cogsQuery->whereDate('sales.sale_date', $todayStr)
                : $cogsQuery->whereDate('sales.sale_date', '>=', $startDate)->whereDate('sales.sale_date', '<=', $endDate);

            $cogs = (float) ($cogsQuery
                ->selectRaw('COALESCE(SUM(COALESCE(products.cost_price, 0) * (CASE WHEN (sale_items.quantity - COALESCE(r.returned_qty, 0)) > 0 THEN (sale_items.quantity - COALESCE(r.returned_qty, 0)) ELSE 0 END)), 0) as cogs')
                ->value('cogs') ?? 0);

            $salesProfit = (float) $salesRevenue - $cogs;

            // Net Profit (Sales Profit - Expenses)
            $netProfit = $salesProfit - $totalExpenses;

            // Calculate percentage changes (compare with previous period)
            $calculatePercentageChange = function ($currentValue, $previousValue) {
                if ($previousValue == 0) {
                    return $currentValue > 0 ? 100 : 0;
                }
                return round((($currentValue - $previousValue) / abs($previousValue)) * 100, 2);
            };

            // Previous period dates (same length as current period)
            $currentPeriodLength = Carbon::parse($endDate)->diffInDays(Carbon::parse($startDate)) + 1;
            $previousEndDate = Carbon::parse($startDate)->subDay()->format('Y-m-d');
            $previousStartDate = Carbon::parse($previousEndDate)->subDays($currentPeriodLength - 1)->format('Y-m-d');

            // Previous Period Sales
            $previousSalesQuery = $applyExclude(Sale::where('sale_type', 'sale'));
            $previousSalesQuery = $previousSalesQuery->whereDate('sale_date', '>=', $previousStartDate)->whereDate('sale_date', '<=', $previousEndDate);
            $previousSales = $previousSalesQuery->sum('total_amount') ?? 0;
            $salesChangePercent = $calculatePercentageChange($totalSales, $previousSales);

            // Previous Period Purchase
            $previousPurchaseQuery = Purchase::query();
            $previousPurchaseQuery = $previousPurchaseQuery->whereDate('purchase_date', '>=', $previousStartDate)->whereDate('purchase_date', '<=', $previousEndDate);
            $previousPurchase = $previousPurchaseQuery->sum('total_amount') ?? 0;
            $purchaseChangePercent = $calculatePercentageChange($totalPurchase, $previousPurchase);

            // Previous Period Expenses
            $previousExpenseQuery = Expense::query();
            $previousExpenseQuery = $previousExpenseQuery->whereDate('expense_date', '>=', $previousStartDate)->whereDate('expense_date', '<=', $previousEndDate);
            $previousExpenses = $previousExpenseQuery->sum('amount') ?? 0;
            $expenseChangePercent = $calculatePercentageChange($totalExpenses, $previousExpenses);

            // Previous Period Sales Profit = Previous Sales Revenue - Previous COGS
            $previousCogsQuery = DB::table('sale_items')
                ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
                ->join('products', 'sale_items.product_id', '=', 'products.id')
                ->leftJoinSub($returnAgg, 'r', function ($join) {
                    $join->on('sale_items.id', '=', 'r.sale_item_id');
                })
                ->where('sales.sale_type', 'sale');

            $previousCogsQuery = $applyExclude($previousCogsQuery, 'sales.total_amount');
            $previousCogs = (float) ($previousCogsQuery
                ->whereDate('sales.sale_date', '>=', $previousStartDate)->whereDate('sales.sale_date', '<=', $previousEndDate)
                ->selectRaw('COALESCE(SUM(COALESCE(products.cost_price, 0) * (CASE WHEN (sale_items.quantity - COALESCE(r.returned_qty, 0)) > 0 THEN (sale_items.quantity - COALESCE(r.returned_qty, 0)) ELSE 0 END)), 0) as cogs')
                ->value('cogs') ?? 0);

            $previousSalesProfit = (float) $previousSales - $previousCogs;

            // Previous Period Net Profit (Sales Profit - Expenses)
            $previousNetProfit = $previousSalesProfit - $previousExpenses;
            $profitChangePercent = $calculatePercentageChange($netProfit, $previousNetProfit);

            // Invoice Due within selected period (exclude hidden sales)
            $dueQuery = $applyExclude(Sale::where('payment_status', '!=', 'paid'));
            $dueQuery = $useToday
                ? $dueQuery->whereDate('sale_date', $todayStr)
                : $dueQuery->whereDate('sale_date', '>=', $startDate)->whereDate('sale_date', '<=', $endDate);
            $invoiceDue = $dueQuery->sum('due_amount') ?? 0;

            // Due Invoice Count within selected period
            $dueCountQuery = $applyExclude(Sale::where('payment_status', '!=', 'paid'));
            $dueCountQuery = $useToday
                ? $dueCountQuery->whereDate('sale_date', $todayStr)
                : $dueCountQuery->whereDate('sale_date', '>=', $startDate)->whereDate('sale_date', '<=', $endDate);
            $dueInvoiceCount = $dueCountQuery->count();

            // Total Products
            $totalProducts = Product::where('is_active', true)->count();

            // Low Stock Items
            $lowStockItems = Product::whereColumn('stock_quantity', '<=', 'alert_quantity')
                ->where('is_active', true)
                ->count();

            // Recent Sales (last 5 within period)
            $recentQuery = Sale::with('customer')->where('sale_type', 'sale');
            $recentQuery = $useToday
                ? $recentQuery->whereDate('sale_date', $todayStr)
                : $recentQuery->whereDate('sale_date', '>=', $startDate)->whereDate('sale_date', '<=', $endDate);
            $recentSales = $recentQuery->orderBy('created_at', 'desc')->limit(5)->get();

            // Low Stock Products (top 5)
            $lowStockProducts = Product::with('category')
                ->whereColumn('stock_quantity', '<=', 'alert_quantity')
                ->where('is_active', true)
                ->orderBy('stock_quantity', 'asc')
                ->limit(5)
                ->get();

            // Top Selling Products (last 30 days)
            $topProducts = collect([]); // Empty collection for now
            
            // Check if we have sales data
            if (DB::table('sale_items')->exists()) {
                $topProducts = DB::table('sale_items')
                    ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
                    ->join('products', 'sale_items.product_id', '=', 'products.id')
                    ->leftJoinSub($returnAgg, 'r', function ($join) {
                        $join->on('sale_items.id', '=', 'r.sale_item_id');
                    })
                    ->where('sales.sale_date', '>=', Carbon::now()->subDays(30))
                    ->where('sales.sale_type', 'sale')
                    ->select(
                        'products.id',
                        'products.name',
                        'products.sku',
                        DB::raw('SUM(CASE WHEN (sale_items.quantity - COALESCE(r.returned_qty, 0)) > 0 THEN (sale_items.quantity - COALESCE(r.returned_qty, 0)) ELSE 0 END) as sold_quantity'),
                        DB::raw('SUM(CASE WHEN (sale_items.total - COALESCE(r.returned_total, 0)) > 0 THEN (sale_items.total - COALESCE(r.returned_total, 0)) ELSE 0 END) as total_sales')
                    )
                    ->groupBy('products.id', 'products.name', 'products.sku')
                    ->orderBy('sold_quantity', 'desc')
                    ->limit(5)
                    ->get();
            }

            // Sales Chart Data (last 30 days) excluding hidden ranges
            $chartLabels = [];
            $chartData = [];

            // Build arrays for the last 30 days (oldest first)
            for ($i = 29; $i >= 0; $i--) {
                $date = Carbon::today()->subDays($i);
                $chartLabels[] = $date->format('M d');

                $dailySales = $applyExclude(Sale::where('sale_type', 'sale'))
                    ->whereDate('sale_date', $date->toDateString())
                    ->sum('total_amount') ?? 0;

                $chartData[] = (float) $dailySales;
            }

            return view('dashboard', compact(
                'totalSales',
                'totalPurchase',
                'totalExpenses',
                'netProfit',
                'invoiceDue',
                'dueInvoiceCount',
                'totalProducts',
                'lowStockItems',
                'recentSales',
                'lowStockProducts',
                'topProducts',
                'chartLabels',
                'chartData',
                'salesChangePercent',
                'purchaseChangePercent',
                'expenseChangePercent',
                'profitChangePercent'
            ));
        } catch (\Exception $e) {
            // Log the error and show a user-friendly message
            Log::error('Dashboard Error: ' . $e->getMessage());
            return view('dashboard', [
                'totalSales' => 0,
                'totalPurchase' => 0,
                'totalExpenses' => 0,
                'netProfit' => 0,
                'invoiceDue' => 0,
                'dueInvoiceCount' => 0,
                'totalProducts' => 0,
                'lowStockItems' => 0,
                'recentSales' => collect([]),
                'lowStockProducts' => collect([]),
                'topProducts' => collect([]),
                'chartLabels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                'chartData' => [0, 0, 0, 0, 0, 0, 0],
                'salesChangePercent' => 0,
                'purchaseChangePercent' => 0,
                'expenseChangePercent' => 0,
                'profitChangePercent' => 0
            ]);
        }
    }
}
