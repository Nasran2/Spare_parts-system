@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
<div class="space-y-6">
    @php
        // Secret POS: ranges to hide amounts
        $secretRanges = \App\Models\Setting::get('secretpos.hidden_ranges', []);
        $maskAmount = function($amount) use ($secretRanges) {
            foreach ((array)$secretRanges as $r) {
                $min = (int)($r['min'] ?? 0);
                $max = (int)($r['max'] ?? PHP_INT_MAX);
                $hide = (bool)($r['hide'] ?? false);
                if ($hide && $amount >= $min && $amount <= $max) {
                    return '—';
                }
            }
            return number_format($amount, 2);
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
                $isLastWeek = (\Carbon\Carbon::parse($end)->diffInDays(\Carbon\Carbon::parse($start)) === 6);
                $isLastMonth = (\Carbon\Carbon::parse($start)->isSameDay(\Carbon\Carbon::parse($end)->startOfMonth()));
            @endphp
            <div class="flex flex-wrap gap-2">
                <button type="button" data-range="today" class="px-4 py-2 {{ $isToday ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }} rounded-lg text-sm hover:bg-blue-700 transition">
                    <i class="fas fa-calendar-day mr-1"></i> Today
                </button>
                <button type="button" data-range="yesterday" class="px-4 py-2 {{ $isYesterday ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }} rounded-lg text-sm hover:bg-blue-700 transition">
                    <i class="fas fa-history mr-1"></i> Yesterday
                </button>
                <button type="button" data-range="last_week" class="px-4 py-2 {{ $isLastWeek ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }} rounded-lg text-sm hover:bg-blue-700 transition">
                    <i class="fas fa-calendar-week mr-1"></i> Last Week
                </button>
                <button type="button" data-range="last_month" class="px-4 py-2 {{ $isLastMonth ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }} rounded-lg text-sm hover:bg-blue-700 transition">
                    <i class="fas fa-calendar-alt mr-1"></i> Last Month
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
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6">
        
        <!-- Total Sales -->
        <div class="stat-card bg-white rounded-xl shadow-md p-6 border-l-4 border-blue-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Total Sales</p>
                    <h3 class="text-2xl md:text-3xl font-bold text-gray-800">{{ $currency }} {{ number_format($totalSales ?? 0, 2) }}</h3>
                    <p class="text-xs {{ $salesChangePercent >= 0 ? 'text-green-600' : 'text-red-600' }} mt-2">
                        <i class="fas fa-arrow-{{ $salesChangePercent >= 0 ? 'up' : 'down' }} mr-1"></i> {{ abs($salesChangePercent) }}% {{ $salesChangePercent >= 0 ? 'increase' : 'decrease' }} from last period
                    </p>
                </div>
                <div class="w-14 h-14 gradient-blue rounded-full flex items-center justify-center shadow-lg">
                    <i class="fas fa-dollar-sign text-white text-2xl"></i>
                </div>
            </div>
        </div>

        <!-- Total Purchase -->
        <div class="stat-card bg-white rounded-xl shadow-md p-6 border-l-4 border-orange-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Total Purchase</p>
                    <h3 class="text-2xl md:text-3xl font-bold text-gray-800">{{ $currency }} {{ number_format($totalPurchase ?? 0, 2) }}</h3>
                    <p class="text-xs {{ $purchaseChangePercent >= 0 ? 'text-orange-600' : 'text-green-600' }} mt-2">
                        <i class="fas fa-arrow-{{ $purchaseChangePercent >= 0 ? 'up' : 'down' }} mr-1"></i> {{ abs($purchaseChangePercent) }}% {{ $purchaseChangePercent >= 0 ? 'increase' : 'decrease' }} from last period
                    </p>
                </div>
                <div class="w-14 h-14 gradient-orange rounded-full flex items-center justify-center shadow-lg">
                    <i class="fas fa-shopping-cart text-white text-2xl"></i>
                </div>
            </div>
        </div>

        <!-- Net Profit -->
        <div class="stat-card bg-white rounded-xl shadow-md p-6 border-l-4 border-green-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Net Profit</p>
                    <h3 class="text-2xl md:text-3xl font-bold text-gray-800">{{ $currency }} {{ number_format($netProfit ?? 0, 2) }}</h3>
                    <p class="text-xs {{ $profitChangePercent >= 0 ? 'text-green-600' : 'text-red-600' }} mt-2">
                        <i class="fas fa-arrow-{{ $profitChangePercent >= 0 ? 'up' : 'down' }} mr-1"></i> {{ abs($profitChangePercent) }}% {{ $profitChangePercent >= 0 ? 'increase' : 'decrease' }} from last period
                    </p>
                </div>
                <div class="w-14 h-14 gradient-green rounded-full flex items-center justify-center shadow-lg">
                    <i class="fas fa-chart-line text-white text-2xl"></i>
                </div>
            </div>
        </div>

        <!-- Invoice Due -->
        <div class="stat-card bg-white rounded-xl shadow-md p-6 border-l-4 border-red-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Invoice Due</p>
                    <h3 class="text-2xl md:text-3xl font-bold text-gray-800">{{ $currency }} {{ number_format($invoiceDue ?? 0, 2) }}</h3>
                    <p class="text-xs text-red-600 mt-2">
                        <i class="fas fa-exclamation-triangle mr-1"></i> {{ $dueInvoiceCount ?? 0 }} invoices
                    </p>
                </div>
                <div class="w-14 h-14 gradient-red rounded-full flex items-center justify-center shadow-lg">
                    <i class="fas fa-file-invoice-dollar text-white text-2xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Secondary Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6">
        
        <!-- Total Expenses -->
        <div class="stat-card bg-white rounded-xl shadow-md p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Total Expenses</p>
                    <h3 class="text-2xl font-bold text-gray-800">{{ $currency }} {{ number_format($totalExpenses ?? 0, 2) }}</h3>
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
                    <h3 class="text-2xl font-bold text-gray-800">{{ number_format($lowStockItems ?? 0) }}</h3>
                </div>
                <div class="w-12 h-12 bg-red-500 rounded-full flex items-center justify-center">
                    <i class="fas fa-exclamation-triangle text-white text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
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
                        <p class="font-bold text-gray-800">{{ $product->sold_quantity }} sold</p>
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

    <!-- Recent Transactions & Low Stock Alerts -->
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
                            <td class="py-3 px-2 text-gray-600">{{ $sale->customer->name ?? 'Walk-in' }}</td>
                            <td class="py-3 px-2 text-right font-semibold text-gray-800">{{ $currency }} {{ $maskAmount($sale->total_amount) }}</td>
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
                <a href="{{ route('reports.stock') }}" class="text-blue-600 hover:text-blue-700 text-sm font-medium">
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
                            <p class="font-semibold text-gray-800">{{ $product->name }}</p>
                            <p class="text-xs text-gray-500">{{ $product->categories->pluck('name')->join(', ') ?: 'Uncategorized' }}</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="font-bold text-red-600">{{ $product->stock_quantity }} left</p>
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

    <!-- Quick Actions -->
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
        const weekBtn = document.querySelector('[data-range="last_week"]');
        const monthBtn = document.querySelector('[data-range="last_month"]');
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
            const start = new Date();
            start.setDate(end.getDate()-6);
            setActive('last_week');
            goWithRange(formatDate(start), formatDate(end));
        });
        if(monthBtn) monthBtn.addEventListener('click', ()=>{
            const end = new Date();
            const start = new Date(end.getFullYear(), end.getMonth(), 1);
            setActive('last_month');
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
                const sD = new Date(sd);
                const eD = new Date(ed);
                const diffDays = Math.round((eD - sD)/(1000*60*60*24));
                if(diffDays===6){ setActive('last_week'); }
                else if(new Date(sd).getDate()===1){ setActive('last_month'); }
                else { setActive('custom'); }
            }
        } else {
            setActive('today');
        }
    })();
</script>
@endsection
