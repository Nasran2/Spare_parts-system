@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
<div class="space-y-6">
    @php
        $dashboardControls = is_array($dashboardControls ?? null) ? $dashboardControls : [];
        $priceVisiblePct = (float) ($dashboardControls['price_visible_percentage'] ?? 100);
        $profitVisiblePct = (float) ($dashboardControls['profit_visible_percentage'] ?? 100);
        $qtyVisiblePct = (float) ($dashboardControls['qty_visible_percentage'] ?? 100);
        $stockVisiblePct = (float) ($dashboardControls['stock_visible_percentage'] ?? 100);

        $applyPct = function ($value, $pct) {
            $pct = max(0, min(100, (float) $pct));
            return (float) $value * ($pct / 100);
        };

        // Secret POS: ranges to hide amounts
        $secretRanges = \App\Models\Setting::get('secretpos.hidden_ranges', []);
        $maskAmount = function($amount) use ($secretRanges, $dashboardControls, $priceVisiblePct, $applyPct) {
            if (\App\Services\PrivacyModeService::isActiveForUser(auth()->user()) && \App\Services\PrivacyModeService::shouldMaskForCurrentPage()) {
                return \App\Services\PrivacyModeService::maskAmount((float) $amount);
            }

            if (!empty($dashboardControls['hide_price_wise_data'])) {
                return '—';
            }

            foreach ((array)$secretRanges as $r) {
                $min = (int)($r['min'] ?? 0);
                $max = (int)($r['max'] ?? PHP_INT_MAX);
                $hide = (bool)($r['hide'] ?? false);
                if ($hide && $amount >= $min && $amount <= $max) {
                    return '—';
                }
            }

            $masked = $applyPct((float) $amount, $priceVisiblePct);
            $roundToWhole = $priceVisiblePct < 100;

            return number_format($roundToWhole ? round($masked) : $masked, $roundToWhole ? 0 : 2);
        };
        $maskProfitAmount = function($amount) use ($secretRanges, $dashboardControls, $profitVisiblePct, $applyPct) {
            if (\App\Services\PrivacyModeService::isActiveForUser(auth()->user()) && \App\Services\PrivacyModeService::shouldMaskForCurrentPage()) {
                return \App\Services\PrivacyModeService::maskAmount((float) $amount);
            }

            if (!empty($dashboardControls['hide_profit_loss'])) {
                return '—';
            }

            foreach ((array)$secretRanges as $r) {
                $min = (int)($r['min'] ?? 0);
                $max = (int)($r['max'] ?? PHP_INT_MAX);
                $hide = (bool)($r['hide'] ?? false);
                if ($hide && $amount >= $min && $amount <= $max) {
                    return '—';
                }
            }

            $masked = $applyPct((float) $amount, $profitVisiblePct);
            $roundToWhole = $profitVisiblePct < 100;

            return number_format($roundToWhole ? round($masked) : $masked, $roundToWhole ? 0 : 2);
        };
    @endphp
    
    <!-- Date Filter -->
    <div class="bg-white rounded-lg shadow-sm p-4">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-3 md:space-y-0">
            <div class="flex items-center space-x-2">
                <i class="fas fa-calendar-alt text-blue-600"></i>
                <span class="font-semibold text-gray-700">Filter Period:</span>
            </div>
            @php
                $qs = [ 'start' => request('start_date'), 'end' => request('end_date') ];
                $today = \Carbon\Carbon::today()->format('Y-m-d');
                $yesterday = \Carbon\Carbon::yesterday()->format('Y-m-d');
                $end = $qs['end'] ?: $today;
                $start = $qs['start'] ?: $today;
                $isToday = ($start === $today && $end === $today);
                $isYesterday = ($start === $yesterday && $end === $yesterday);
                $endC = \Carbon\Carbon::parse($end);
                $startC = \Carbon\Carbon::parse($start);
                $isThisWeek = ($endC->isSameDay(\Carbon\Carbon::today()) && $startC->isSameDay($endC->copy()->startOfWeek()));
                $isThisMonth = ($endC->isSameDay(\Carbon\Carbon::today()) && $startC->isSameDay($endC->copy()->startOfMonth()));
            @endphp
            <div class="flex flex-wrap gap-2">
                <button type="button" data-range="today" class="px-4 py-2 {{ $isToday ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }} rounded-lg text-sm hover:bg-blue-700 transition">
                    <i class="fas fa-calendar-day mr-1"></i> Today
                </button>
                <button type="button" data-range="yesterday" class="px-4 py-2 {{ $isYesterday ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }} rounded-lg text-sm hover:bg-blue-700 transition">
                    <i class="fas fa-history mr-1"></i> Yesterday
                </button>
                <button type="button" data-range="this_week" class="px-4 py-2 {{ $isThisWeek ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }} rounded-lg text-sm hover:bg-blue-700 transition">
                    <i class="fas fa-calendar-week mr-1"></i> This Week
                </button>
                <button type="button" data-range="this_month" class="px-4 py-2 {{ $isThisMonth ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }} rounded-lg text-sm hover:bg-blue-700 transition">
                    <i class="fas fa-calendar-alt mr-1"></i> This Month
                </button>
                <div class="flex items-center gap-2">
                    <input type="date" id="custom_start" class="px-3 py-2 border rounded text-sm" />
                    <input type="date" id="custom_end" class="px-3 py-2 border rounded text-sm" />
                    <button type="button" data-range="custom" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm hover:bg-blue-700 transition">
                        <i class="fas fa-calendar mr-1"></i> Custom Range
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- KPI Cards -->
    @if(empty($dashboardControls['hide_dashboard_cards']))
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6">
        
        <!-- Total Sales -->
        @if(empty($dashboardControls['hide_total_sales']))
        <div class="stat-card bg-white rounded-xl shadow-md p-6 border-l-4 border-blue-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Total Sales</p>
                    <h3 class="text-2xl md:text-3xl font-bold text-gray-800">{{ $currency }} {{ $maskAmount($totalSales ?? 0) }}</h3>
                    <p class="text-xs {{ $salesChangePercent >= 0 ? 'text-green-600' : 'text-red-600' }} mt-2">
                        <i class="fas fa-arrow-{{ $salesChangePercent >= 0 ? 'up' : 'down' }} mr-1"></i> {{ abs($salesChangePercent) }}% {{ $salesChangePercent >= 0 ? 'increase' : 'decrease' }} from last period
                    </p>
                </div>
                <div class="w-14 h-14 gradient-blue rounded-full flex items-center justify-center shadow-lg">
                    <i class="fas fa-dollar-sign text-white text-2xl"></i>
                </div>
            </div>
        </div>
        @endif

        <!-- Total Purchase -->
        @if(empty($dashboardControls['hide_total_purchase']))
        <div class="stat-card bg-white rounded-xl shadow-md p-6 border-l-4 border-orange-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Total Purchase</p>
                    <h3 class="text-2xl md:text-3xl font-bold text-gray-800">{{ $currency }} {{ $maskAmount($totalPurchase ?? 0) }}</h3>
                    <p class="text-xs {{ $purchaseChangePercent >= 0 ? 'text-orange-600' : 'text-green-600' }} mt-2">
                        <i class="fas fa-arrow-{{ $purchaseChangePercent >= 0 ? 'up' : 'down' }} mr-1"></i> {{ abs($purchaseChangePercent) }}% {{ $purchaseChangePercent >= 0 ? 'increase' : 'decrease' }} from last period
                    </p>
                </div>
                <div class="w-14 h-14 gradient-orange rounded-full flex items-center justify-center shadow-lg">
                    <i class="fas fa-shopping-cart text-white text-2xl"></i>
                </div>
            </div>
        </div>
        @endif

        <!-- Net Profit -->
        @if(empty($dashboardControls['hide_profit_loss']))
        <div class="stat-card bg-white rounded-xl shadow-md p-6 border-l-4 border-green-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Net Profit</p>
                    <h3 class="text-2xl md:text-3xl font-bold text-gray-800">{{ $currency }} {{ $maskProfitAmount($netProfit ?? 0) }}</h3>
                    <p class="text-xs {{ $profitChangePercent >= 0 ? 'text-green-600' : 'text-red-600' }} mt-2">
                        <i class="fas fa-arrow-{{ $profitChangePercent >= 0 ? 'up' : 'down' }} mr-1"></i> {{ abs($profitChangePercent) }}% {{ $profitChangePercent >= 0 ? 'increase' : 'decrease' }} from last period
                    </p>
                </div>
                <div class="w-14 h-14 gradient-green rounded-full flex items-center justify-center shadow-lg">
                    <i class="fas fa-chart-line text-white text-2xl"></i>
                </div>
            </div>
        </div>
        @endif

        <!-- Invoice Due -->
        @if(empty($dashboardControls['hide_invoice_details']))
        <div class="stat-card bg-white rounded-xl shadow-md p-6 border-l-4 border-red-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Invoice Due</p>
                    <h3 class="text-2xl md:text-3xl font-bold text-gray-800">{{ $currency }} {{ $maskAmount($invoiceDue ?? 0) }}</h3>
                    <p class="text-xs text-red-600 mt-2">
                        <i class="fas fa-exclamation-triangle mr-1"></i> {{ $dueInvoiceCount ?? 0 }} invoices
                    </p>
                </div>
                <div class="w-14 h-14 gradient-red rounded-full flex items-center justify-center shadow-lg">
                    <i class="fas fa-file-invoice-dollar text-white text-2xl"></i>
                </div>
            </div>
        </div>
        @endif
    </div>
    @endif

    <!-- Secondary Stats -->
    @if(empty($dashboardControls['hide_widgets']))
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6">
        
        <!-- Total Expenses -->
        <div class="stat-card bg-white rounded-xl shadow-md p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Total Expenses</p>
                    <h3 class="text-2xl font-bold text-gray-800">{{ $currency }} {{ $maskAmount($totalExpenses ?? 0) }}</h3>
                </div>
                <div class="w-12 h-12 gradient-purple rounded-full flex items-center justify-center">
                    <i class="fas fa-wallet text-white text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Total Products -->
        <div class="stat-card bg-white rounded-xl shadow-md p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Total Products</p>
                    <h3 class="text-2xl font-bold text-gray-800">{{ number_format($totalProducts ?? 0) }}</h3>
                </div>
                <div class="w-12 h-12 gradient-indigo rounded-full flex items-center justify-center">
                    <i class="fas fa-box text-white text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Low Stock Items -->
        <div class="stat-card bg-white rounded-xl shadow-md p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Low Stock Items</p>
                    <h3 class="text-2xl font-bold text-gray-800">{{ !empty($dashboardControls['hide_actual_stock_count']) ? '—' : number_format($applyPct(($lowStockItems ?? 0), $qtyVisiblePct)) }}</h3>
                </div>
                <div class="w-12 h-12 bg-red-500 rounded-full flex items-center justify-center">
                    <i class="fas fa-exclamation-triangle text-white text-xl"></i>
                </div>
            </div>
        </div>
    </div>
    @endif

    @if(($chequeReminders ?? collect())->count() > 0)
    <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-indigo-500">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-800">
                <i class="fas fa-money-check-alt text-indigo-600 mr-2"></i>
                Cheque Payment Reminders
            </h3>
            <span class="text-xs font-semibold text-indigo-700 bg-indigo-50 px-3 py-1 rounded-full">{{ ($chequeReminders ?? collect())->count() }} pending</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="text-left py-3 px-2 text-gray-600 font-semibold">Date</th>
                        <th class="text-left py-3 px-2 text-gray-600 font-semibold">Cheque No</th>
                        <th class="text-left py-3 px-2 text-gray-600 font-semibold">Customer</th>
                        <th class="text-right py-3 px-2 text-gray-600 font-semibold">Amount</th>
                        <th class="text-right py-3 px-2 text-gray-600 font-semibold">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($chequeReminders as $cheque)
                    <tr class="border-b border-gray-100 hover:bg-gray-50">
                        <td class="py-3 px-2">
                            <div class="font-semibold text-gray-800">{{ $cheque->cheque_date?->format('Y-m-d') }}</div>
                            <div class="text-xs {{ $cheque->cheque_date?->isPast() ? 'text-red-600' : 'text-gray-500' }}">
                                {{ $cheque->cheque_date?->diffForHumans() }}
                            </div>
                        </td>
                        <td class="py-3 px-2 text-gray-700">
                            <div class="font-semibold">{{ $cheque->cheque_number }}</div>
                            <div class="text-xs text-gray-500">{{ $cheque->bank_name ?: 'Bank not set' }}</div>
                        </td>
                        <td class="py-3 px-2 text-gray-600">{{ $cheque->customer->name ?? 'Customer' }}</td>
                        <td class="py-3 px-2 text-right font-semibold text-gray-800">{{ $currency }} {{ $maskAmount($cheque->amount) }}</td>
                        <td class="py-3 px-2">
                            @if($canManageChequePayments ?? false)
                            <div class="flex items-center justify-end gap-2">
                                <form method="POST" action="{{ route('cheque-payments.pass', $cheque) }}" class="js-cheque-action-form" data-action-label="pass" data-cheque-date="{{ $cheque->cheque_date?->format('Y-m-d') }}">
                                    @csrf
                                    <button class="px-3 py-1.5 bg-green-600 text-white rounded-lg hover:bg-green-700 text-xs font-semibold">
                                        <i class="fas fa-check mr-1"></i>Pass
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('cheque-payments.return', $cheque) }}" class="js-cheque-action-form" data-action-label="return" data-cheque-date="{{ $cheque->cheque_date?->format('Y-m-d') }}">
                                    @csrf
                                    <button class="px-3 py-1.5 bg-red-600 text-white rounded-lg hover:bg-red-700 text-xs font-semibold">
                                        <i class="fas fa-undo mr-1"></i>Return
                                    </button>
                                </form>
                            </div>
                            @else
                            <span class="block text-right text-xs text-gray-500">View only</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <!-- Charts Row -->
    @if(empty($dashboardControls['hide_charts']))
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        
        <!-- Top Selling Products -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-bold text-gray-800">
                    <i class="fas fa-fire text-orange-600 mr-2"></i>
                    Top Selling Products
                </h3>
                <a href="{{ route('reports.trending') }}" class="text-blue-600 hover:text-blue-700 text-sm font-medium">
                    View All <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
            <div class="space-y-4">
                @php $safeTopProducts = collect($topProducts ?? [])->filter(fn($p) => is_object($p)); @endphp
                @forelse($safeTopProducts as $product)
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-cog text-blue-600"></i>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-800">{{ $product->name }}</p>
                            <p class="text-xs text-gray-500">SKU: {{ $product->sku }}</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="font-bold text-gray-800">{{ !empty($dashboardControls['hide_qty_wise_data']) ? '—' : number_format(round($applyPct($product->sold_quantity, $qtyVisiblePct)), 0) }} sold</p>
                        <p class="text-xs text-green-600">{{ $currency }} {{ $maskAmount($product->total_sales) }}</p>
                    </div>
                </div>
                @empty
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-box-open text-4xl mb-2"></i>
                    <p>No sales data available</p>
                </div>
                @endforelse
            </div>
        </div>
    </div>
    @endif

    <!-- Recent Transactions & Low Stock Alerts -->
    @if(empty($dashboardControls['hide_tables']))
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        
        <!-- Recent Sales -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-bold text-gray-800">
                    <i class="fas fa-receipt text-green-600 mr-2"></i>
                    Recent Sales
                </h3>
                <a href="{{ route('sales.index') }}" class="text-blue-600 hover:text-blue-700 text-sm font-medium">
                    View All <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="text-left py-3 px-2 text-gray-600 font-semibold">Invoice</th>
                            <th class="text-left py-3 px-2 text-gray-600 font-semibold">Customer</th>
                            <th class="text-right py-3 px-2 text-gray-600 font-semibold">Amount</th>
                            <th class="text-center py-3 px-2 text-gray-600 font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $safeRecentSales = collect($recentSales ?? [])->filter(fn($s) => is_object($s)); @endphp
                        @forelse($safeRecentSales as $sale)
                        <tr class="border-b border-gray-100 hover:bg-gray-50">
                            <td class="py-3 px-2 font-medium text-gray-800">{{ $sale->sale_no }}</td>
                            <td class="py-3 px-2 text-gray-600">{{ !empty($dashboardControls['hide_supplier_names']) ? 'Hidden' : ($sale->customer->name ?? 'Walk-in') }}</td>
                            <td class="py-3 px-2 text-right font-semibold text-gray-800">{{ !empty($dashboardControls['hide_invoice_details']) ? '—' : ($currency . ' ' . $maskAmount($sale->total_amount)) }}</td>
                            <td class="py-3 px-2 text-center">
                                @if($sale->payment_status === 'paid')
                                    <span class="px-2 py-1 bg-green-100 text-green-700 rounded-full text-xs font-semibold">Paid</span>
                                @elseif($sale->payment_status === 'partial')
                                    <span class="px-2 py-1 bg-yellow-100 text-yellow-700 rounded-full text-xs font-semibold">Partial</span>
                                @else
                                    <span class="px-2 py-1 bg-red-100 text-red-700 rounded-full text-xs font-semibold">Unpaid</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center py-8 text-gray-500">
                                <i class="fas fa-inbox text-3xl mb-2 block"></i>
                                No recent sales
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Low Stock Alerts -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-bold text-gray-800">
                    <i class="fas fa-exclamation-triangle text-red-600 mr-2"></i>
                    Low Stock Alerts
                </h3>
                <a href="{{ route('reports.stock', ['low_stock' => 1]) }}" class="text-blue-600 hover:text-blue-700 text-sm font-medium">
                    View All <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
            <div class="space-y-3">
                @php $safeLowStock = collect($lowStockProducts ?? [])->filter(fn($p) => is_object($p)); @endphp
                @forelse($safeLowStock as $product)
                <div class="flex items-center justify-between p-3 bg-red-50 border border-red-200 rounded-lg">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-box text-red-600"></i>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-800">{{ !empty($dashboardControls['hide_product_wise_data']) ? 'Hidden Product' : $product->name }}</p>
                            <p class="text-xs text-gray-500">{{ $product->categories->pluck('name')->join(', ') ?: 'Uncategorized' }}</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="font-bold text-red-600">{{ !empty($dashboardControls['hide_actual_stock_quantity']) ? '—' : number_format(round($applyPct($product->stock_quantity, $stockVisiblePct)), 0) }} left</p>
                        <p class="text-xs text-gray-500">Alert: {{ $product->alert_quantity }}</p>
                    </div>
                </div>
                @empty
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-check-circle text-4xl text-green-500 mb-2"></i>
                    <p>All products are well stocked!</p>
                </div>
                @endforelse
            </div>
        </div>
    </div>
    @endif

    <!-- Quick Actions -->
    @if(empty($dashboardControls['hide_widgets']))
    <div class="bg-white rounded-xl shadow-md p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4">
            <i class="fas fa-bolt text-yellow-500 mr-2"></i>
            Quick Actions
        </h3>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
            <a href="{{ route('pos.index') }}" class="flex flex-col items-center p-4 bg-gradient-blue text-white rounded-lg hover:opacity-90 transition">
                <i class="fas fa-cash-register text-3xl mb-2"></i>
                <span class="text-sm font-medium">New Sale</span>
            </a>
            <a href="{{ route('purchases.create') }}" class="flex flex-col items-center p-4 bg-gradient-orange text-white rounded-lg hover:opacity-90 transition">
                <i class="fas fa-cart-plus text-3xl mb-2"></i>
                <span class="text-sm font-medium">Add Purchase</span>
            </a>
            <a href="{{ route('products.create') }}" class="flex flex-col items-center p-4 bg-gradient-green text-white rounded-lg hover:opacity-90 transition">
                <i class="fas fa-plus-circle text-3xl mb-2"></i>
                <span class="text-sm font-medium">Add Product</span>
            </a>
            <a href="{{ route('expenses.create') }}" class="flex flex-col items-center p-4 bg-gradient-purple text-white rounded-lg hover:opacity-90 transition">
                <i class="fas fa-wallet text-3xl mb-2"></i>
                <span class="text-sm font-medium">Add Expense</span>
            </a>
            <a href="{{ route('customers.create') }}" class="flex flex-col items-center p-4 bg-gradient-indigo text-white rounded-lg hover:opacity-90 transition">
                <i class="fas fa-user-plus text-3xl mb-2"></i>
                <span class="text-sm font-medium">Add Customer</span>
            </a>
            <a href="{{ route('suppliers.create') }}" class="flex flex-col items-center p-4 bg-gray-600 text-white rounded-lg hover:opacity-90 transition">
                <i class="fas fa-truck text-3xl mb-2"></i>
                <span class="text-sm font-medium">Add Supplier</span>
            </a>
        </div>
    </div>
    @endif
</div>

<div id="cheque-confirm-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 px-4">
    <div class="w-full max-w-md rounded-xl bg-white shadow-2xl">
        <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4">
            <div>
                <h3 class="text-lg font-bold text-gray-900">Confirm Early Cheque Action</h3>
                <p class="text-xs text-gray-500 mt-1">The cheque date has not arrived yet.</p>
            </div>
            <button type="button" id="cheque-confirm-close" class="h-9 w-9 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="px-5 py-4">
            <div class="flex items-start gap-3 rounded-lg bg-amber-50 border border-amber-200 p-3">
                <div class="mt-0.5 h-9 w-9 shrink-0 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <p id="cheque-confirm-message" class="text-sm leading-6 text-amber-900"></p>
            </div>
        </div>
        <div class="flex items-center justify-end gap-2 border-t border-gray-200 px-5 py-4">
            <button type="button" id="cheque-confirm-cancel" class="px-4 py-2 rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200 font-semibold">Cancel</button>
            <button type="button" id="cheque-confirm-submit" class="px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 font-semibold">Confirm</button>
        </div>
    </div>
</div>

{{-- Sales chart removed as requested --}}
<script>
    (function(){
        function formatDate(d){
            const pad = n => (n<10? '0'+n : ''+n);
            return d.getFullYear()+'-'+pad(d.getMonth()+1)+'-'+pad(d.getDate());
        }
        function setActive(range){
            const btns = document.querySelectorAll('[data-range]');
            btns.forEach(b=>{
                b.classList.remove('bg-blue-600','text-white');
                b.classList.add('bg-gray-100','text-gray-700');
            });
            const active = document.querySelector(`[data-range="${range}"]`);
            if(active){
                active.classList.remove('bg-gray-100','text-gray-700');
                active.classList.add('bg-blue-600','text-white');
            }
        }
        function goWithRange(start,end){
            const url = new URL(window.location.href);
            url.searchParams.set('start_date', start);
            url.searchParams.set('end_date', end);
            window.location.href = url.toString();
        }
        const todayBtn = document.querySelector('[data-range="today"]');
        const yestBtn = document.querySelector('[data-range="yesterday"]');
        const weekBtn = document.querySelector('[data-range="this_week"]');
        const monthBtn = document.querySelector('[data-range="this_month"]');
        const customBtn = document.querySelector('[data-range="custom"]');
        const cs = document.getElementById('custom_start');
        const ce = document.getElementById('custom_end');

        if(todayBtn) todayBtn.addEventListener('click', ()=>{
            const t = new Date();
            const s = formatDate(t);
            setActive('today');
            goWithRange(s,s);
        });
        if(yestBtn) yestBtn.addEventListener('click', ()=>{
            const d = new Date();
            d.setDate(d.getDate()-1);
            const s = formatDate(d);
            setActive('yesterday');
            goWithRange(s,s);
        });
        if(weekBtn) weekBtn.addEventListener('click', ()=>{
            const end = new Date();
            const start = new Date(end.getFullYear(), end.getMonth(), end.getDate());
            const day = start.getDay(); // 0=Sun,1=Mon...
            const diff = (day + 6) % 7; // since Monday
            start.setDate(start.getDate() - diff);
            setActive('this_week');
            goWithRange(formatDate(start), formatDate(end));
        });
        if(monthBtn) monthBtn.addEventListener('click', ()=>{
            const end = new Date();
            const start = new Date(end.getFullYear(), end.getMonth(), 1);
            setActive('this_month');
            goWithRange(formatDate(start), formatDate(end));
        });
        if(customBtn) customBtn.addEventListener('click', ()=>{
            if(cs.value && ce.value){
                setActive('custom');
                goWithRange(cs.value, ce.value);
            } else {
                alert('Select start and end dates');
            }
        });

        // On load: highlight based on current query
        const params = new URLSearchParams(window.location.search);
        const sd = params.get('start_date');
        const ed = params.get('end_date');
        const today = formatDate(new Date());
        const yd = formatDate(new Date(new Date().setDate(new Date().getDate()-1)));
        if(sd && ed){
            if(sd===today && ed===today){ setActive('today'); }
            else if(sd===yd && ed===yd){ setActive('yesterday'); }
            else {
                // Check span length
                const eD = new Date(ed);
                const startWeek = new Date(eD.getFullYear(), eD.getMonth(), eD.getDate());
                const day = startWeek.getDay();
                const diff = (day + 6) % 7;
                startWeek.setDate(startWeek.getDate() - diff);
                if(sd===formatDate(startWeek) && ed===today){ setActive('this_week'); }
                else if(new Date(sd).getDate()===1 && ed===today){ setActive('this_month'); }
                else { setActive('custom'); }
            }
        } else {
            setActive('today');
        }

        const chequeConfirmModal = document.getElementById('cheque-confirm-modal');
        const chequeConfirmMessage = document.getElementById('cheque-confirm-message');
        const chequeConfirmSubmit = document.getElementById('cheque-confirm-submit');
        const chequeConfirmClose = document.getElementById('cheque-confirm-close');
        const chequeConfirmCancel = document.getElementById('cheque-confirm-cancel');
        let pendingChequeForm = null;

        function closeChequeConfirmModal() {
            chequeConfirmModal?.classList.add('hidden');
            chequeConfirmModal?.classList.remove('flex');
            pendingChequeForm = null;
        }

        [chequeConfirmClose, chequeConfirmCancel].forEach((button) => {
            button?.addEventListener('click', closeChequeConfirmModal);
        });

        chequeConfirmModal?.addEventListener('click', (event) => {
            if (event.target === chequeConfirmModal) {
                closeChequeConfirmModal();
            }
        });

        chequeConfirmSubmit?.addEventListener('click', () => {
            if (!pendingChequeForm) return;
            pendingChequeForm.dataset.confirmed = '1';
            pendingChequeForm.submit();
        });

        document.querySelectorAll('.js-cheque-action-form').forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (form.dataset.confirmed === '1') return;

                const chequeDate = form.dataset.chequeDate || '';
                const action = form.dataset.actionLabel || 'process';
                if (!chequeDate) return;

                const todayDate = new Date();
                todayDate.setHours(0, 0, 0, 0);
                const chequeDateObj = new Date(chequeDate + 'T00:00:00');

                if (chequeDateObj > todayDate) {
                    event.preventDefault();
                    pendingChequeForm = form;
                    if (chequeConfirmMessage) {
                        chequeConfirmMessage.textContent = `This cheque date is ${chequeDate}. Do you really want to ${action} it before the cheque date?`;
                    }
                    chequeConfirmSubmit.textContent = action === 'return' ? 'Confirm Return' : 'Confirm Pass';
                    chequeConfirmSubmit.classList.toggle('bg-red-600', action === 'return');
                    chequeConfirmSubmit.classList.toggle('hover:bg-red-700', action === 'return');
                    chequeConfirmSubmit.classList.toggle('bg-indigo-600', action !== 'return');
                    chequeConfirmSubmit.classList.toggle('hover:bg-indigo-700', action !== 'return');
                    chequeConfirmModal?.classList.remove('hidden');
                    chequeConfirmModal?.classList.add('flex');
                }
            });
        });
    })();
</script>
@endsection
