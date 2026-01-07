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
                : $salesQuery->whereBetween('sale_date', [$startDate, $endDate]);
            $totalSales = $salesQuery->sum('total_amount') ?? 0;
            // Secret POS: override total sales amount if configured
            $overrideTotal = (float) \App\Models\Setting::get('secretpos.override_total_sales_amount', 0);
            if ($overrideTotal > 0) {
                $totalSales = $overrideTotal;
            }

            // Total Purchase
            $purchaseQuery = Purchase::query();
            $purchaseQuery = $useToday
                ? $purchaseQuery->whereDate('purchase_date', $todayStr)
                : $purchaseQuery->whereBetween('purchase_date', [$startDate, $endDate]);
            $totalPurchase = $purchaseQuery->sum('total_amount') ?? 0;

            // Total Expenses
            $expenseQuery = Expense::query();
            $expenseQuery = $useToday
                ? $expenseQuery->whereDate('expense_date', $todayStr)
                : $expenseQuery->whereBetween('expense_date', [$startDate, $endDate]);
            $totalExpenses = $expenseQuery->sum('amount') ?? 0;

            // Net Profit (Sales - Purchase - Expenses)
            $netProfit = $totalSales - $totalPurchase - $totalExpenses;

            // Invoice Due within selected period (exclude hidden sales)
            $dueQuery = $applyExclude(Sale::where('payment_status', '!=', 'paid'));
            $dueQuery = $useToday
                ? $dueQuery->whereDate('sale_date', $todayStr)
                : $dueQuery->whereBetween('sale_date', [$startDate, $endDate]);
            $invoiceDue = $dueQuery->sum('due_amount') ?? 0;

            // Due Invoice Count within selected period
            $dueCountQuery = $applyExclude(Sale::where('payment_status', '!=', 'paid'));
            $dueCountQuery = $useToday
                ? $dueCountQuery->whereDate('sale_date', $todayStr)
                : $dueCountQuery->whereBetween('sale_date', [$startDate, $endDate]);
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
                : $recentQuery->whereBetween('sale_date', [$startDate, $endDate]);
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
                    ->where('sales.sale_date', '>=', Carbon::now()->subDays(30))
                    ->where('sales.sale_type', 'sale')
                    ->select(
                        'products.id',
                        'products.name',
                        'products.sku',
                        DB::raw('SUM(sale_items.quantity) as sold_quantity'),
                        DB::raw('SUM(sale_items.total) as total_sales')
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
                'chartData'
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
                'chartData' => [0, 0, 0, 0, 0, 0, 0]
            ]);
        }
    }
}
