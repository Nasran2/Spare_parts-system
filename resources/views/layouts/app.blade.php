<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') - Vehicle POS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        
        * {
            font-family: 'Poppins', sans-serif;
        }
        html, body { margin:0; padding:0; }
        
        /* Loading Spinner */
        .page-loader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f4f6;
            border-top: 5px solid #3b82f6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .loaded .page-loader {
            display: none;
        }
        
        /* Floating POS Button */
        .floating-pos-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            cursor: pointer;
            z-index: 1000;
            transition: all 0.3s ease;
        }
        
        .floating-pos-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
        }
        
        .floating-pos-btn:active {
            transform: scale(0.95);
        }
        
        @media (max-width: 768px) {
            .floating-pos-btn {
                bottom: 20px;
                right: 20px;
                width: 50px;
                height: 50px;
                font-size: 20px;
            }
        }
        
        .sidebar {
            transition: transform 0.3s ease-in-out, width 0.3s ease-in-out;
        }
        
        .sidebar-mobile-open {
            transform: translateX(0);
        }
        
        .sidebar-mobile-closed {
            transform: translateX(-100%);
        }
        
        @media (min-width: 768px) {
            .sidebar {
                transform: translateX(0) !important;
            }
        }
        
        .nav-item {
            transition: all 0.3s ease;
        }
        
        .nav-item:hover {
            background: rgba(59, 130, 246, 0.1);
            border-left: 4px solid #3b82f6;
            padding-left: 1.25rem;
        }
        
        .nav-item.active {
            background: linear-gradient(90deg, rgba(59, 130, 246, 0.15) 0%, transparent 100%);
            border-left: 4px solid #3b82f6;
            color: #3b82f6;
            font-weight: 600;
        }
        
        .dropdown-menu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease-in-out;
        }
        
        .dropdown-menu.open {
            max-height: 1500px;
        }
        
        .stat-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .gradient-blue {
            background: linear-gradient(135deg, #3b82f6 0%, #60a5fa 100%);
        }
        
        .gradient-green {
            background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
        }
        
        .gradient-orange {
            background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%);
        }
        
        .gradient-red {
            background: linear-gradient(135deg, #ef4444 0%, #f87171 100%);
        }
        
        .gradient-purple {
            background: linear-gradient(135deg, #8b5cf6 0%, #a78bfa 100%);
        }
        
        .gradient-indigo {
            background: linear-gradient(135deg, #6366f1 0%, #818cf8 100%);
        }
    </style>
</head>
<body class="bg-gray-50 loaded">
    
    <!-- Page Loader -->
    <div class="page-loader">
        <div class="text-center">
            <div class="spinner mx-auto mb-4"></div>
            <p class="text-gray-600 font-semibold">Loading...</p>
        </div>
    </div>
    
    <!-- Mobile Menu Overlay -->
    <div 
        id="sidebar-overlay" 
        class="fixed inset-0 bg-black bg-opacity-50 z-40 md:hidden hidden"
        onclick="toggleSidebar()"
    ></div>

    @php
        $sidebarName = \App\Models\Setting::get('shop_name', config('app.name', 'Vehicle POS'));
        $sidebarTagline = \App\Models\Setting::get('shop_tagline', 'Auto Parts System');

        $navUser = auth()->user();
        $canDashboard = $navUser?->hasPermission('dashboard.view');
        $canUsersMenu = $navUser?->hasPermission('users.view') || $navUser?->hasPermission('roles.view');
        $canSuppliers = $navUser?->hasPermission('suppliers.view');
        $canProductsMenu = $navUser?->hasPermission('products.view')
            || $navUser?->hasPermission('products.create')
            || $navUser?->hasPermission('products.edit')
            || $navUser?->hasPermission('products.delete')
            || $navUser?->hasPermission('products.update-price')
            || $navUser?->hasPermission('categories.view')
            || $navUser?->hasPermission('brands.view')
            || $navUser?->hasPermission('units.view')
            || $navUser?->hasPermission('barcode.print')
            || $navUser?->hasPermission('barcode.settings');
        $canPurchaseMenu = $navUser?->hasPermission('purchases.view')
            || $navUser?->hasPermission('purchases.create')
            || $navUser?->hasPermission('purchases.edit');
        $canSalesMenu = $navUser?->hasPermission('sales.view')
            || $navUser?->hasPermission('sales.create')
            || $navUser?->hasPermission('sales.edit')
            || $navUser?->hasPermission('quotations.view')
            || $navUser?->hasPermission('pos.access')
            || $navUser?->hasPermission('cheque_payments.view');
        $canCustomers = $navUser?->hasPermission('customers.view');
        $canExpensesMenu = $navUser?->hasPermission('expenses.view')
            || $navUser?->hasPermission('expenses.create')
            || $navUser?->hasPermission('expenses.edit');
        $canAccountingMenu = $navUser?->hasPermission('accounting.view')
            || $navUser?->hasPermission('accounting.accounts')
            || $navUser?->hasPermission('accounting.transactions')
            || $navUser?->hasPermission('accounting.cash-book')
            || $navUser?->hasPermission('accounting.bank-book')
            || $navUser?->hasPermission('accounting.banks')
            || $navUser?->hasPermission('accounting.petty-cash')
            || $navUser?->hasPermission('accounting.ledger')
            || $navUser?->hasPermission('accounting.t-accounts')
            || $navUser?->hasPermission('accounting.trial-balance')
            || $navUser?->hasPermission('accounting.balance-sheet')
            || $navUser?->hasPermission('accounting.owner-equity.view')
            || $navUser?->hasPermission('cheque_payments.view');
        $canStoresMenu = $navUser?->hasPermission('stores.view')
            || $navUser?->hasPermission('stores.stores')
            || $navUser?->hasPermission('stores.allocations')
            || $navUser?->hasPermission('stores.transfers')
            || $navUser?->hasPermission('stores.transfer-report')
            || $navUser?->hasPermission('stores.report');
        $canReportsMenu = $navUser?->hasPermission('reports.sales')
            || $navUser?->hasPermission('reports.purchase')
            || $navUser?->hasPermission('reports.profit-loss')
            || $navUser?->hasPermission('reports.stock')
            || $navUser?->hasPermission('reports.expense')
            || $navUser?->hasPermission('reports.trending')
            || $navUser?->hasPermission('reports.vat')
            || $navUser?->hasPermission('reports.receive')
            || $navUser?->hasPermission('reports.debit')
            || $navUser?->hasPermission('reports.rate-conversion')
            || $navUser?->hasPermission('reports.due-bills')
            || $navUser?->hasPermission('reports.customer-due')
            || $navUser?->hasPermission('reports.never-sold')
            || $navUser?->hasPermission('reports.unsold-recently');
        $canNotificationsMenu = $navUser?->hasPermission('notifications.view')
            || $navUser?->hasPermission('notifications.configure');
        $canSettings = $navUser?->hasPermission('settings.view');
        $canActivityLog = $navUser?->hasPermission('activity-log.view');
        $canPos = $navUser?->hasPermission('pos.access');
        $isSuperAdmin = $navUser?->isSuperAdmin();
    @endphp

    <!-- Sidebar -->
    <aside 
        id="sidebar" 
        class="sidebar sidebar-mobile-closed md:sidebar-mobile-open fixed top-0 left-0 h-screen w-64 bg-white shadow-xl z-50 overflow-y-auto"
    >
        <!-- Logo Section -->
        <div class="p-6 border-b border-gray-200 bg-gradient-blue">
            <div class="flex items-center space-x-3">
                <div class="w-12 h-12 bg-white rounded-lg flex items-center justify-center shadow-md">
                    <i class="fas fa-car-side text-2xl text-blue-600"></i>
                </div>
                <div class="text-gray-900">
                    <h1 class="text-xl font-bold">{{ $sidebarName }}</h1>
                    <p class="text-xs font-semibold text-black">{{ $sidebarTagline }}</p>
                </div>
            </div>
        </div>

        <!-- Navigation Menu -->
        <nav class="p-4 space-y-2">
            <!-- Dashboard -->
            @if($canDashboard)
            <a href="{{ route('dashboard') }}" class="nav-item flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-700 {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <i class="fas fa-home w-5"></i>
                <span>Dashboard</span>
            </a>
            @endif

            @if($isSuperAdmin)
            <div class="nav-group">
                <button onclick="toggleDropdown('secret-dashboard-menu')" class="nav-item flex items-center justify-between w-full px-4 py-3 rounded-lg text-gray-700 {{ request()->routeIs('fun.dashboard*') ? 'active' : '' }}">
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-user-secret w-5"></i>
                        <span>Secret Dashboard</span>
                    </div>
                    <i class="fas fa-chevron-down transition-transform {{ request()->routeIs('fun.dashboard*') ? 'rotate-180' : '' }}" id="secret-dashboard-menu-icon"></i>
                </button>
                <div id="secret-dashboard-menu" class="dropdown-menu ml-4 mt-1 space-y-1 {{ request()->routeIs('fun.dashboard*') ? 'open' : '' }}">
                    <a href="{{ route('fun.dashboard') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('fun.dashboard') ? 'active' : '' }}">
                        <i class="fas fa-gauge-high w-4"></i>
                        <span>Overview</span>
                    </a>
                    <a href="{{ route('fun.dashboard.section', 'dashboard') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->is('fun/dashboard/dashboard') ? 'active' : '' }}">
                        <i class="fas fa-chart-pie w-4"></i>
                        <span>Dashboard Controls</span>
                    </a>
                    <a href="{{ route('fun.dashboard.section', 'inventory') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->is('fun/dashboard/inventory') ? 'active' : '' }}">
                        <i class="fas fa-boxes-stacked w-4"></i>
                        <span>Product & Stock</span>
                    </a>
                    <a href="{{ route('fun.dashboard.section', 'records') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->is('fun/dashboard/records') ? 'active' : '' }}">
                        <i class="fas fa-eye-slash w-4"></i>
                        <span>Hide Records</span>
                    </a>
                    <a href="{{ route('fun.dashboard.section', 'privacy') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->is('fun/dashboard/privacy') ? 'active' : '' }}">
                        <i class="fas fa-keyboard w-4"></i>
                        <span>Privacy Mode</span>
                    </a>
                </div>
            </div>
            @endif

            <!-- User Management -->
            @if($canUsersMenu)
            <div class="nav-group">
                <button onclick="toggleDropdown('user-menu')" class="nav-item flex items-center justify-between w-full px-4 py-3 rounded-lg text-gray-700 {{ request()->routeIs('users.*') || request()->routeIs('roles.*') ? 'active' : '' }}">
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-users w-5"></i>
                        <span class="text-sm">User Management</span>
                    </div>
                    <i class="fas fa-chevron-down transition-transform {{ request()->routeIs('users.*') || request()->routeIs('roles.*') ? 'rotate-180' : '' }}" id="user-menu-icon"></i>
                </button>
                <div id="user-menu" class="dropdown-menu ml-4 mt-1 space-y-1 {{ request()->routeIs('users.*') || request()->routeIs('roles.*') ? 'open' : '' }}">
                    @if($navUser?->hasPermission('users.view'))
                    <a href="{{ route('users.index') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('users.*') ? 'active' : '' }}">
                        <i class="fas fa-user-plus w-4"></i>
                        <span>Create User</span>
                    </a>
                    @endif
                    @if($navUser?->hasPermission('roles.view'))
                    <a href="{{ route('roles.index') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('roles.*') ? 'active' : '' }}">
                        <i class="fas fa-user-shield w-4"></i>
                        <span>User Roles</span>
                    </a>
                    @endif
                </div>
            </div>
            @endif

            <!-- Supplier Management -->
            @if($canSuppliers)
            <a href="{{ route('suppliers.index') }}" class="nav-item flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-700 {{ request()->routeIs('suppliers.*') ? 'active' : '' }}">
                <i class="fas fa-truck w-5"></i>
                <span>Suppliers</span>
            </a>
            @endif

            <!-- Product Management -->
            @if($canProductsMenu)
            <div class="nav-group">
                <button onclick="toggleDropdown('product-menu')" class="nav-item flex items-center justify-between w-full px-4 py-3 rounded-lg text-gray-700 {{ request()->routeIs('products.*') || request()->routeIs('products.import*') || request()->routeIs('categories.*') || request()->routeIs('brands.*') || request()->routeIs('units.*') ? 'active' : '' }}">
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-box w-5"></i>
                        <span>Products</span>
                    </div>
                    <i class="fas fa-chevron-down transition-transform {{ request()->routeIs('products.*') || request()->routeIs('products.import*') || request()->routeIs('categories.*') || request()->routeIs('brands.*') || request()->routeIs('units.*') ? 'rotate-180' : '' }}" id="product-menu-icon"></i>
                </button>
                <div id="product-menu" class="dropdown-menu ml-4 mt-1 space-y-1 {{ request()->routeIs('products.*') || request()->routeIs('products.import*') || request()->routeIs('categories.*') || request()->routeIs('brands.*') || request()->routeIs('units.*') ? 'open' : '' }}">
                    @if($navUser?->hasPermission('products.view'))
                    <a href="{{ route('products.index') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('products.*') ? 'active' : '' }}">
                        <i class="fas fa-list w-4"></i>
                        <span>Product List</span>
                    </a>
                    @endif
                    @if($navUser?->hasPermission('products.create'))
                    <a href="{{ route('products.create') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600">
                        <i class="fas fa-plus-circle w-4"></i>
                        <span>Add Product</span>
                    </a>
                    @endif
                    @if($navUser?->hasPermission('products.create'))
                    <a href="{{ route('products.import') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('products.import*') ? 'active' : '' }}">
                        <i class="fas fa-file-import w-4"></i>
                        <span>Import Products</span>
                    </a>
                    @endif
                    @if($navUser?->hasPermission('barcode.print'))
                    <a href="{{ route('products.barcode.print') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('products.barcode.*') ? 'active' : '' }}">
                        <i class="fas fa-barcode w-4"></i>
                        <span>Barcode Print</span>
                    </a>
                    @endif
                    @if($navUser?->hasPermission('categories.view'))
                    <a href="{{ route('categories.index') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('categories.*') ? 'active' : '' }}">
                        <i class="fas fa-tags w-4"></i>
                        <span>Categories</span>
                    </a>
                    @endif
                    @if($navUser?->hasPermission('brands.view'))
                    <a href="{{ route('brands.index') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('brands.*') ? 'active' : '' }}">
                        <i class="fas fa-copyright w-4"></i>
                        <span>Brands</span>
                    </a>
                    @endif
                    @if($navUser?->hasPermission('units.view'))
                    <a href="{{ route('units.index') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('units.*') ? 'active' : '' }}">
                        <i class="fas fa-balance-scale w-4"></i>
                        <span>Units</span>
                    </a>
                    @endif
                    @if($navUser?->hasPermission('products.edit'))
                    <a href="{{ route('products.write-off.index') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('products.write-off.*') ? 'active' : '' }}">
                        <i class="fas fa-trash-alt w-4"></i>
                        <span>Write-off / Damage</span>
                    </a>
                    @endif
                </div>
            </div>
            @endif

            <!-- Purchase -->
            @if($canPurchaseMenu)
            <div class="nav-group">
                <button onclick="toggleDropdown('purchase-menu')" class="nav-item flex items-center justify-between w-full px-4 py-3 rounded-lg text-gray-700 {{ request()->routeIs('purchases.*') ? 'active' : '' }}">
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-shopping-cart w-5"></i>
                        <span>Purchase</span>
                    </div>
                    <i class="fas fa-chevron-down transition-transform {{ request()->routeIs('purchases.*') ? 'rotate-180' : '' }}" id="purchase-menu-icon"></i>
                </button>
                <div id="purchase-menu" class="dropdown-menu ml-4 mt-1 space-y-1 {{ request()->routeIs('purchases.*') ? 'open' : '' }}">
                    @if($navUser?->hasPermission('purchases.view'))
                    <a href="{{ route('purchases.index') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('purchases.index') || request()->routeIs('purchases.show') || request()->routeIs('purchases.edit') ? 'active' : '' }}">
                        <i class="fas fa-list-alt w-4"></i>
                        <span>Purchase List</span>
                    </a>
                    @endif
                    @if($navUser?->hasPermission('purchases.create'))
                    <a href="{{ route('purchases.create') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('purchases.create') ? 'active' : '' }}">
                        <i class="fas fa-cart-plus w-4"></i>
                        <span>Add Purchase</span>
                    </a>
                    @endif
                    @if($navUser?->hasPermission('purchases.edit'))
                    <a href="{{ route('purchase-returns.index') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('purchase-returns.*') ? 'active' : '' }}">
                        <i class="fas fa-undo w-4"></i>
                        <span>Purchase Returns</span>
                    </a>
                    @endif
                </div>
            </div>
            @endif

            <!-- Sales -->
            @if($canSalesMenu)
            <div class="nav-group">
                <button onclick="toggleDropdown('sale-menu')" class="nav-item flex items-center justify-between w-full px-4 py-3 rounded-lg text-gray-700 {{ request()->routeIs('pos.*') || request()->routeIs('sales.*') || request()->routeIs('quotations.*') || request()->routeIs('sale-returns.*') || request()->routeIs('cheque-payments.*') ? 'active' : '' }}">
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-cash-register w-5"></i>
                        <span>Sales</span>
                    </div>
                    <i class="fas fa-chevron-down transition-transform {{ request()->routeIs('pos.*') || request()->routeIs('sales.*') || request()->routeIs('quotations.*') || request()->routeIs('sale-returns.*') || request()->routeIs('cheque-payments.*') ? 'rotate-180' : '' }}" id="sale-menu-icon"></i>
                </button>
                <div id="sale-menu" class="dropdown-menu ml-4 mt-1 space-y-1 {{ request()->routeIs('pos.*') || request()->routeIs('sales.*') || request()->routeIs('quotations.*') || request()->routeIs('sale-returns.*') || request()->routeIs('cheque-payments.*') ? 'open' : '' }}">
                    @if($canPos)
                    <a href="{{ route('pos.index') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('pos.*') ? 'active' : '' }}">
                        <i class="fas fa-desktop w-4"></i>
                        <span>POS</span>
                    </a>
                    @endif
                    @if($navUser?->hasPermission('sales.view'))
                    <a href="{{ route('sales.index') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('sales.*') ? 'active' : '' }}">
                        <i class="fas fa-list-alt w-4"></i>
                        <span>Sales List</span>
                    </a>
                    @endif
                    @if($navUser?->hasPermission('cheque_payments.view'))
                    <a href="{{ route('cheque-payments.index') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('cheque-payments.*') ? 'active' : '' }}">
                        <i class="fas fa-money-check-alt w-4"></i>
                        <span>Cheque Details</span>
                    </a>
                    @endif
                    @if($navUser?->hasPermission('quotations.view'))
                    <a href="{{ route('quotations.index') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('quotations.*') ? 'active' : '' }}">
                        <i class="fas fa-file-invoice w-4"></i>
                        <span>Quotations</span>
                    </a>
                    @endif
                    @if($navUser?->hasPermission('sales.edit'))
                    <a href="{{ route('sale-returns.index') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('sale-returns.*') ? 'active' : '' }}">
                        <i class="fas fa-undo w-4"></i>
                        <span>Sale Returns</span>
                    </a>
                    @endif
                </div>
            </div>
            @endif

            <!-- Customers -->
            @if($canCustomers)
            <a href="{{ route('customers.index') }}" class="nav-item flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-700 {{ request()->routeIs('customers.*') ? 'active' : '' }}">
                <i class="fas fa-user-tie w-5"></i>
                <span>Customers</span>
            </a>
            @endif

            <!-- Accounting -->
            @if($canAccountingMenu)
            <div class="nav-group">
                <button onclick="toggleDropdown('accounting-menu')" class="nav-item flex items-center justify-between w-full px-4 py-3 rounded-lg text-gray-700 {{ request()->routeIs('accounting.*') || request()->routeIs('cheque-payments.*') ? 'active' : '' }}">
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-book-open w-5"></i>
                        <span>Accounting</span>
                    </div>
                    <i class="fas fa-chevron-down transition-transform {{ request()->routeIs('accounting.*') || request()->routeIs('cheque-payments.*') ? 'rotate-180' : '' }}" id="accounting-menu-icon"></i>
                </button>
                <div id="accounting-menu" class="dropdown-menu ml-4 mt-1 space-y-1 {{ request()->routeIs('accounting.*') || request()->routeIs('cheque-payments.*') ? 'open' : '' }}">
                    @if($navUser?->hasPermission('accounting.view'))
                    <a href="{{ route('accounting.index') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('accounting.index') ? 'active' : '' }}">
                        <i class="fas fa-gauge-high w-4"></i>
                        <span>Overview</span>
                    </a>
                    @endif
                    @if($navUser?->hasPermission('accounting.accounts'))
                    <a href="{{ route('accounting.accounts') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('accounting.accounts') ? 'active' : '' }}">
                        <i class="fas fa-list w-4"></i>
                        <span>Chart Accounting</span>
                    </a>
                    @endif
                    @if($navUser?->hasPermission('accounting.transactions'))
                    <a href="{{ route('accounting.transactions') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('accounting.transactions') ? 'active' : '' }}">
                        <i class="fas fa-money-bill-transfer w-4"></i>
                        <span>Transactions</span>
                    </a>
                    @endif
                    @if($navUser?->hasPermission('accounting.cash-book'))
                    <a href="{{ route('accounting.cash-book') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('accounting.cash-book') ? 'active' : '' }}">
                        <i class="fas fa-cash-register w-4"></i>
                        <span>Cash Book</span>
                    </a>
                    @endif
                    @if($navUser?->hasPermission('accounting.bank-book'))
                    <a href="{{ route('accounting.bank-book') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('accounting.bank-book') ? 'active' : '' }}">
                        <i class="fas fa-building-columns w-4"></i>
                        <span>Bank Book</span>
                    </a>
                    @endif
                    @if($navUser?->hasPermission('accounting.banks'))
                    <a href="{{ route('accounting.banks') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('accounting.banks') ? 'active' : '' }}">
                        <i class="fas fa-building-columns w-4"></i>
                        <span>Bank Reconcile</span>
                    </a>
                    @endif
                    @if($navUser?->hasPermission('accounting.petty-cash'))
                    <a href="{{ route('accounting.petty-cash') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('accounting.petty-cash') ? 'active' : '' }}">
                        <i class="fas fa-wallet w-4"></i>
                        <span>Petty Cash</span>
                    </a>
                    @endif
                    @if($navUser?->hasPermission('accounting.ledger'))
                    <a href="{{ route('accounting.ledger') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('accounting.ledger') ? 'active' : '' }}">
                        <i class="fas fa-book w-4"></i>
                        <span>Ledger</span>
                    </a>
                    @endif
                    @if($navUser?->hasPermission('accounting.t-accounts'))
                    <a href="{{ route('accounting.t-accounts') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('accounting.t-accounts') ? 'active' : '' }}">
                        <i class="fas fa-table-columns w-4"></i>
                        <span>T Accounts</span>
                    </a>
                    @endif
                    @if($navUser?->hasPermission('accounting.trial-balance'))
                    <a href="{{ route('accounting.trial-balance') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('accounting.trial-balance') ? 'active' : '' }}">
                        <i class="fas fa-scale-balanced w-4"></i>
                        <span>Trial Balance</span>
                    </a>
                    @endif
                    @if($navUser?->hasPermission('accounting.balance-sheet'))
                    <a href="{{ route('accounting.balance-sheet') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('accounting.balance-sheet') ? 'active' : '' }}">
                        <i class="fas fa-file-invoice-dollar w-4"></i>
                        <span>Balance Sheet</span>
                    </a>
                    @endif
                    @if($navUser?->hasPermission('accounting.owner-equity.view'))
                    <a href="{{ route('accounting.owner-equity') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('accounting.owner-equity') ? 'active' : '' }}">
                        <i class="fas fa-user-tie w-4"></i>
                        <span>Owner Capital / Drawings</span>
                    </a>
                    @endif
                    @if($navUser?->hasPermission('cheque_payments.view'))
                    <a href="{{ route('cheque-payments.index') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('cheque-payments.*') ? 'active' : '' }}">
                        <i class="fas fa-money-check-alt w-4"></i>
                        <span>Cheque Management</span>
                    </a>
                    @endif
                </div>
            </div>
            @endif

            <!-- Store Stock -->
            @if($canStoresMenu)
            <div class="nav-group">
                <button onclick="toggleDropdown('store-stock-menu')" class="nav-item flex items-center justify-between w-full px-4 py-3 rounded-lg text-gray-700 {{ request()->routeIs('stores.*') ? 'active' : '' }}">
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-store w-5"></i>
                        <span>Store Stock</span>
                    </div>
                    <i class="fas fa-chevron-down transition-transform {{ request()->routeIs('stores.*') ? 'rotate-180' : '' }}" id="store-stock-menu-icon"></i>
                </button>
                <div id="store-stock-menu" class="dropdown-menu ml-4 mt-1 space-y-1 {{ request()->routeIs('stores.*') ? 'open' : '' }}">
                    @if($navUser?->hasPermission('stores.view'))
                    <a href="{{ route('stores.index') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('stores.index') ? 'active' : '' }}">
                        <i class="fas fa-gauge-high w-4"></i>
                        <span>Overview</span>
                    </a>
                    @endif
                    @if($navUser?->hasPermission('stores.stores'))
                    <a href="{{ route('stores.stores') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('stores.stores') ? 'active' : '' }}">
                        <i class="fas fa-shop w-4"></i>
                        <span>Stores</span>
                    </a>
                    @endif
                    @if($navUser?->hasPermission('stores.allocations'))
                    <a href="{{ route('stores.allocations') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('stores.allocations') ? 'active' : '' }}">
                        <i class="fas fa-boxes-stacked w-4"></i>
                        <span>Allocations</span>
                    </a>
                    @endif
                    @if($navUser?->hasPermission('stores.transfers'))
                    <a href="{{ route('stores.transfers') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('stores.transfers') ? 'active' : '' }}">
                        <i class="fas fa-right-left w-4"></i>
                        <span>Transfers</span>
                    </a>
                    @endif
                    @if($navUser?->hasPermission('stores.transfer-report'))
                    <a href="{{ route('stores.transfer-report') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('stores.transfer-report') ? 'active' : '' }}">
                        <i class="fas fa-clock-rotate-left w-4"></i>
                        <span>Transfer History</span>
                    </a>
                    @endif
                    @if($navUser?->hasPermission('stores.report'))
                    <a href="{{ route('stores.report') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('stores.report') ? 'active' : '' }}">
                        <i class="fas fa-chart-column w-4"></i>
                        <span>Shipment Report</span>
                    </a>
                    @endif
                </div>
            </div>
            @endif

            <!-- Expenses -->
            @if($canExpensesMenu)
            <div class="nav-group">
                <button onclick="toggleDropdown('expense-menu')" class="nav-item flex items-center justify-between w-full px-4 py-3 rounded-lg text-gray-700 {{ request()->routeIs('expenses.*') || request()->routeIs('expense-categories.*') ? 'active' : '' }}">
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-wallet w-5"></i>
                        <span>Expenses</span>
                    </div>
                    <i class="fas fa-chevron-down transition-transform {{ request()->routeIs('expenses.*') || request()->routeIs('expense-categories.*') ? 'rotate-180' : '' }}" id="expense-menu-icon"></i>
                </button>
                <div id="expense-menu" class="dropdown-menu ml-4 mt-1 space-y-1 {{ request()->routeIs('expenses.*') || request()->routeIs('expense-categories.*') ? 'open' : '' }}">
                    @if($navUser?->hasPermission('expenses.view'))
                    <a href="{{ route('expenses.index') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('expenses.index') || request()->routeIs('expenses.show') || request()->routeIs('expenses.edit') ? 'active' : '' }}">
                        <i class="fas fa-list-ul w-4"></i>
                        <span>All Expenses</span>
                    </a>
                    @endif
                    @if($navUser?->hasPermission('expenses.create'))
                    <a href="{{ route('expenses.create') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('expenses.create') ? 'active' : '' }}">
                        <i class="fas fa-plus-circle w-4"></i>
                        <span>Add Expense</span>
                    </a>
                    @endif
                    @if($navUser?->hasPermission('expenses.view'))
                    <a href="{{ route('expense-categories.index') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('expense-categories.*') ? 'active' : '' }}">
                        <i class="fas fa-folder-open w-4"></i>
                        <span>Categories</span>
                    </a>
                    @endif
                </div>
            </div>
            @endif

            <!-- Reports -->
            @if($canReportsMenu)
            <div class="nav-group">
                <button onclick="toggleDropdown('report-menu')" class="nav-item flex items-center justify-between w-full px-4 py-3 rounded-lg text-gray-700 {{ request()->routeIs('reports.*') ? 'active' : '' }}">
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-chart-bar w-5"></i>
                        <span>Reports</span>
                    </div>
                    <i class="fas fa-chevron-down transition-transform {{ request()->routeIs('reports.*') ? 'rotate-180' : '' }}" id="report-menu-icon"></i>
                </button>
                <div id="report-menu" class="dropdown-menu ml-4 mt-1 space-y-1 {{ request()->routeIs('reports.*') ? 'open' : '' }}">
                    @if($navUser?->hasPermission('reports.sales'))
                    <a href="{{ route('reports.sales') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('reports.sales') ? 'active' : '' }}">
                        <i class="fas fa-chart-line w-4"></i>
                        <span>Sales Report</span>
                    </a>
                    @endif
                    @if($navUser?->hasPermission('reports.purchase'))
                    <a href="{{ route('reports.purchase') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('reports.purchase') ? 'active' : '' }}">
                        <i class="fas fa-shopping-bag w-4"></i>
                        <span>Purchase Report</span>
                    </a>
                    @endif
                    @if($navUser?->hasPermission('reports.profit-loss'))
                    <a href="{{ route('reports.profit-loss') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('reports.profit-loss') ? 'active' : '' }}">
                        <i class="fas fa-coins w-4"></i>
                        <span>Profit & Loss</span>
                    </a>
                    @endif
                    @if($navUser?->hasPermission('reports.stock'))
                    <a href="{{ route('reports.stock') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('reports.stock') ? 'active' : '' }}">
                        <i class="fas fa-warehouse w-4"></i>
                        <span>Stock Report</span>
                    </a>
                    @endif
                    @if($navUser?->hasPermission('reports.expense'))
                    <a href="{{ route('reports.expense') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('reports.expense') ? 'active' : '' }}">
                        <i class="fas fa-money-bill-wave w-4"></i>
                        <span>Expense Report</span>
                    </a>
                    @endif
                    @if($navUser?->hasPermission('reports.vat'))
                    <a href="{{ route('reports.vat') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('reports.vat') ? 'active' : '' }}">
                        <i class="fas fa-receipt w-4"></i>
                        <span>VAT Report</span>
                    </a>
                    @endif
                    @if($navUser?->hasPermission('reports.rate-conversion'))
                    <a href="{{ route('reports.rates') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('reports.rates') ? 'active' : '' }}">
                        <i class="fas fa-exchange-alt w-4"></i>
                        <span>Rate Conversion</span>
                    </a>
                    @endif
                    @if($navUser?->hasPermission('reports.receive'))
                    <a href="{{ route('reports.receive') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('reports.receive') ? 'active' : '' }}">
                        <i class="fas fa-arrow-down w-4"></i>
                        <span>Receive Report</span>
                    </a>
                    @endif
                    @if($navUser?->hasPermission('reports.debit'))
                    <a href="{{ route('reports.debit') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('reports.debit') ? 'active' : '' }}">
                        <i class="fas fa-arrow-up w-4"></i>
                        <span>Debit Report</span>
                    </a>
                    @endif
                    @if($navUser?->hasPermission('reports.due-bills'))
                    <a href="{{ route('reports.due-bills') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('reports.due-bills') ? 'active' : '' }}">
                        <i class="fas fa-file-invoice-dollar w-4"></i>
                        <span>Due Bills Report</span>
                    </a>
                    @endif
                    @if($navUser?->hasPermission('reports.customer-due'))
                    <a href="{{ route('reports.customer-due') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('reports.customer-due') ? 'active' : '' }}">
                        <i class="fas fa-user-clock w-4"></i>
                        <span>Customer Due Report</span>
                    </a>
                    @endif
                    @if($navUser?->hasPermission('reports.never-sold'))
                    <a href="{{ route('reports.never-sold') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('reports.never-sold') ? 'active' : '' }}">
                        <i class="fas fa-ban w-4"></i>
                        <span>Never Sold Products</span>
                    </a>
                    @endif
                    @if($navUser?->hasPermission('reports.unsold-recently'))
                    <a href="{{ route('reports.unsold-recently') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('reports.unsold-recently') ? 'active' : '' }}">
                        <i class="fas fa-calendar-times w-4"></i>
                        <span>Unsold Products (Time)</span>
                    </a>
                    @endif
                </div>
            </div>
            @endif

            <!-- Notifications -->
            @if($canNotificationsMenu)
            <div class="nav-group">
                <button onclick="toggleSubmenu('notifications-menu')" class="nav-item w-full flex items-center justify-between px-4 py-3 rounded-lg text-gray-700 {{ request()->routeIs('notifications.*') ? 'active' : '' }}">
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-bell w-5"></i>
                        <span>Notifications</span>
                    </div>
                    <i class="fas fa-chevron-down text-xs transition-transform {{ request()->routeIs('notifications.*') ? 'rotate-180' : '' }}" id="notifications-menu-icon"></i>
                </button>
                <div id="notifications-menu" class="submenu {{ request()->routeIs('notifications.*') ? '' : 'hidden' }} ml-8 mt-1 space-y-1">
                    @if($navUser?->hasPermission('notifications.view'))
                    <a href="{{ route('notifications.index') }}" class="flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-50 {{ request()->routeIs('notifications.index') ? 'active' : '' }}">
                        <i class="fas fa-bell w-4"></i>
                        <span>Notifications</span>
                    </a>
                    <a href="{{ route('notifications.history') }}" class="flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-50 {{ request()->routeIs('notifications.history') ? 'active' : '' }}">
                        <i class="fas fa-history w-4"></i>
                        <span>Message History</span>
                    </a>
                    @endif
                    @if($navUser?->hasPermission('notifications.configure'))
                    <a href="{{ route('notifications.send') }}" class="flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-50 {{ request()->routeIs('notifications.send') ? 'active' : '' }}">
                        <i class="fas fa-paper-plane w-4"></i>
                        <span>Send Message</span>
                    </a>
                    <a href="{{ route('notifications.settings') }}" class="flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-50 {{ request()->routeIs('notifications.settings') ? 'active' : '' }}">
                        <i class="fas fa-cog w-4"></i>
                        <span>Notification Settings</span>
                    </a>
                    @endif
                </div>
            </div>
            @endif

            <!-- Settings -->
            @if($canSettings)
            <a href="{{ route('settings.index') }}" class="nav-item flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-700 {{ request()->routeIs('settings.*') ? 'active' : '' }}">
                <i class="fas fa-cog w-5"></i>
                <span>Settings</span>
            </a>
            @endif

            <!-- Activity Log -->
            @if($canActivityLog)
            <a href="{{ route('activity-log.index') }}" class="nav-item flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-700 {{ request()->routeIs('activity-log.*') ? 'active' : '' }}">
                <i class="fas fa-history w-5"></i>
                <span>Activity Log</span>
            </a>
            @endif
        </nav>

        <!-- User Profile Section -->
        <div class="p-4 border-t border-gray-200 mt-4">
            <div class="flex items-center space-x-3 px-3 py-2">
                <div class="w-10 h-10 bg-gradient-blue rounded-full flex items-center justify-center text-white font-semibold">
                    {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-800 truncate">{{ auth()->user()->name ?? 'User' }}</p>
                    <p class="text-xs text-gray-500 truncate">{{ auth()->user()->role->name ?? 'User' }}</p>
                </div>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="mt-2">
                @csrf
                <button type="submit" class="w-full flex items-center space-x-3 px-4 py-2 rounded-lg text-red-600 hover:bg-red-50 transition">
                    <i class="fas fa-sign-out-alt w-5"></i>
                    <span>Logout</span>
                </button>
            </form>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="md:ml-64 min-h-screen flex flex-col">
        
        <!-- Top Header -->
        <header class="bg-white shadow-sm sticky top-0 z-30">
            <div class="flex items-center justify-between px-4 py-2 md:py-3">
                <!-- Mobile Menu Button -->
                <button 
                    onclick="toggleSidebar()" 
                    class="md:hidden text-gray-600 hover:text-gray-800 focus:outline-none"
                >
                    <i class="fas fa-bars text-2xl"></i>
                </button>

                <!-- Page Title -->
                <div class="">
                    <h2 class="text-lg md:text-xl font-bold text-gray-800 leading-tight">
                        @yield('page-title', 'Dashboard')
                    </h2>
                </div>

                <!-- Header Actions -->
                <div class="flex items-center space-x-3">
                    <!-- Search -->
                    <div class="hidden lg:block">
                        <div class="relative">
                            <input 
                                type="text" 
                                placeholder="Search products, sales..." 
                                class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent w-64"
                            >
                            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>
                    </div>

                    <!-- Notifications -->
                    @php
                        if (auth()->check()) {
                            $headerUnreadCount = \App\Models\SystemNotification::where('user_id', auth()->id())
                                ->whereNull('read_at')->count();
                            $headerNotifications = \App\Models\SystemNotification::where('user_id', auth()->id())
                                ->orderByDesc('created_at')->limit(5)->get();
                        } else {
                            $headerUnreadCount = 0;
                            $headerNotifications = collect();
                        }
                    @endphp
                    <div class="relative" id="headerNotif">
                        <button type="button" onclick="toggleHeaderNotif()" class="relative p-2 text-gray-600 hover:text-gray-800 focus:outline-none">
                            <i class="fas fa-bell text-xl"></i>
                            @if(($headerUnreadCount ?? 0) > 0)
                                <span class="absolute -top-0.5 -right-0.5 flex items-center justify-center min-w-[18px] h-[18px] bg-red-600 text-white text-[10px] rounded-full px-1">{{ $headerUnreadCount ?? 0 }}</span>
                            @endif
                        </button>
                        <!-- Dropdown -->
                            <div id="headerNotifMenu" class="hidden absolute right-0 mt-2 w-80 bg-white border border-gray-200 rounded-lg shadow-lg z-50">
                            <div class="px-4 py-2 border-b flex items-center justify-between">
                                <span class="font-semibold text-gray-800 text-sm">Notifications</span>
                                <a href="{{ route('notifications.index') }}" class="text-xs text-blue-600 hover:text-blue-800">View all</a>
                            </div>
                            <div class="max-h-80 overflow-y-auto">
                                @forelse(($headerNotifications ?? collect()) as $n)
                                    <div class="px-4 py-3 text-sm border-b last:border-0 {{ $n->read_at ? 'bg-white' : 'bg-yellow-50' }}">
                                        <p class="font-medium text-gray-800">{{ $n->title }}</p>
                                        <p class="text-gray-600 mt-0.5">{{ \Illuminate\Support\Str::limit($n->message, 90) }}</p>
                                        <p class="text-xs text-gray-400 mt-1">{{ $n->created_at->diffForHumans() }}</p>
                                    </div>
                                @empty
                                    <div class="px-4 py-6 text-center text-gray-400">
                                        <i class="fas fa-bell-slash text-2xl mb-1"></i>
                                        <div class="text-sm">No notifications</div>
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <!-- Quick POS Access -->
                    @if($canPos)
                    <a href="{{ route('pos.index') }}" class="hidden md:flex items-center space-x-2 px-4 py-2 bg-gradient-blue text-white rounded-lg hover:opacity-90 transition">
                        <i class="fas fa-cash-register"></i>
                        <span>Open POS</span>
                    </a>
                    @endif
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <main class="flex-1 p-4 md:p-6 lg:p-8">
            @if (session('success') || session('error'))
                <div class="fixed top-4 right-4 z-[9999] space-y-3 w-[92vw] max-w-md">
                    @if (session('success'))
                        <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded-lg shadow-lg">
                            <div class="flex items-start">
                                <i class="fas fa-check-circle text-green-500 mr-3 text-xl mt-0.5"></i>
                                <p class="text-green-700">{{ session('success') }}</p>
                            </div>
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-lg shadow-lg">
                            <div class="flex items-start">
                                <i class="fas fa-exclamation-circle text-red-500 mr-3 text-xl mt-0.5"></i>
                                <p class="text-red-700">{{ session('error') }}</p>
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            @yield('content')
        </main>

        <!-- Footer -->
<footer class="bg-white border-t border-gray-200 py-4 px-6">
            <div class="flex flex-col md:flex-row items-center justify-between text-sm text-gray-600">
                <p>&copy; 2025 Vehicle POS System. All rights reserved.</p>
                @php($dev = config('services.developer'))
                @php($phoneDigits = preg_replace('/\D+/', '', $dev['phone'] ?? ''))
                <p class="flex items-center mt-2 md:mt-0">
                    Powered by
                    @if(!empty($dev['website']))
                        <a href="https://{{ $dev['website'] }}" target="_blank" class="text-blue-600 hover:text-blue-800 font-semibold mx-1">{{ $dev['website'] }}</a>
                    @elseif(!empty($phoneDigits))
                        <a href="https://wa.me/{{ $phoneDigits }}" target="_blank" class="text-green-600 hover:text-green-800 font-semibold mx-1">{{ $dev['name'] ?? $phoneDigits }}</a>
                    @else
                        <span class="font-semibold mx-1">{{ $dev['name'] ?? 'Developer' }}</span>
                    @endif
                </p>
            </div>
        </footer>
    </div>

    <!-- Floating POS Button -->
    @if(!request()->routeIs('pos.index') && $canPos)
    <a href="{{ route('pos.index') }}" class="floating-pos-btn" title="Open POS">
        <i class="fas fa-cash-register"></i>
    </a>
    @endif

    <script>
        let skipPageLoader = false;

        document.querySelectorAll('[data-skip-page-loader]').forEach(link => {
            link.addEventListener('click', () => {
                skipPageLoader = true;
                setTimeout(() => {
                    skipPageLoader = false;
                    document.body.classList.add('loaded');
                }, 2000);
            });
        });

        // Page loaded - hide loader
        window.addEventListener('load', function() {
            document.body.classList.add('loaded');
        });

        // Fix for back-button (BFcache)
        window.addEventListener('pageshow', function(event) {
            if (event.persisted || (typeof window.performance != "undefined" && window.performance.navigation.type === 2)) {
                document.body.classList.add('loaded');
            }
        });
        
        // Show loader on page navigation
        window.addEventListener('beforeunload', function() {
            if (skipPageLoader) {
                return;
            }
            document.body.classList.remove('loaded');
        });
        
        // Toggle Sidebar on Mobile
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            
            sidebar.classList.toggle('sidebar-mobile-closed');
            sidebar.classList.toggle('sidebar-mobile-open');
            overlay.classList.toggle('hidden');
        }

        // Toggle Dropdown Menu
        function toggleDropdown(menuId) {
            const menu = document.getElementById(menuId);
            const icon = document.getElementById(menuId + '-icon');
            
            menu.classList.toggle('open');
            icon.classList.toggle('rotate-180');
        }

        // Toggle Submenu
        function toggleSubmenu(menuId) {
            const menu = document.getElementById(menuId);
            const icon = document.getElementById(menuId + '-icon');
            
            menu.classList.toggle('hidden');
            icon.classList.toggle('rotate-180');
        }

        // Auto-close sidebar on mobile when clicking a link
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', function() {
                if (window.innerWidth < 768) {
                    toggleSidebar();
                }
            });
        });

        // Notifications dropdown toggle
        function toggleHeaderNotif() {
            const menu = document.getElementById('headerNotifMenu');
            if (!menu) return;
            menu.classList.toggle('hidden');
        }

        // Close notifications if clicked outside
        document.addEventListener('click', (e) => {
            const container = document.getElementById('headerNotif');
            const menu = document.getElementById('headerNotifMenu');
            if (!container || !menu) return;
            if (!container.contains(e.target)) {
                menu.classList.add('hidden');
            }
        });

        // Safety: ensure loader removed if JS fires late
        setTimeout(() => document.body.classList.add('loaded'), 1500);
    </script>

    @yield('extra-modals')

    @if(request()->routeIs('pos.index') && isset($privacySettings) && $privacySettings->is_enabled && ($privacySettings->apply_to_pos ?? false) && \App\Services\PrivacyModeService::canToggle(auth()->user()))
    <!-- Keyboard listener and toggle logic for Privacy Mode -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const shortcutSetting = (isMacPlatform()
                ? @json($privacySettings->shortcut_key_mac ?? 'Cmd+X')
                : @json($privacySettings->shortcut_key ?? 'Alt+S')) || '';
            const shortcut = parseShortcut(shortcutSetting);

            if (!shortcut.key) {
                return;
            }
            
            document.addEventListener('keydown', function(e) {
                const matchesShortcut =
                    e.ctrlKey === shortcut.ctrl &&
                    e.metaKey === shortcut.meta &&
                    e.shiftKey === shortcut.shift &&
                    e.altKey === shortcut.alt &&
                    normalizeKey(e.key) === shortcut.key;

                if (!matchesShortcut) {
                    return;
                }

                const activeEl = document.activeElement;
                const isEditing = activeEl && (
                    activeEl.tagName === 'INPUT' ||
                    activeEl.tagName === 'TEXTAREA' ||
                    activeEl.tagName === 'SELECT' ||
                    activeEl.isContentEditable
                );

                if (isEditing && !(shortcut.ctrl || shortcut.meta || shortcut.alt)) {
                    return;
                }

                e.preventDefault();
                togglePrivacyMode();
            });

            function isMacPlatform() {
                const platform = navigator.platform || '';
                const userAgent = navigator.userAgent || '';
                return platform.toUpperCase().includes('MAC') || userAgent.toUpperCase().includes('MAC');
            }

            function parseShortcut(value) {
                const modifiers = ['ctrl', 'control', 'shift', 'alt', 'option', 'meta', 'cmd', 'command', 'win'];
                const parts = String(value)
                    .toLowerCase()
                    .replace(/⌘/g, 'cmd')
                    .replace(/⌥/g, 'alt')
                    .replace(/⇧/g, 'shift')
                    .replace(/control/g, 'ctrl')
                    .split('+')
                    .map(part => part.trim())
                    .filter(Boolean);

                return {
                    ctrl: parts.includes('ctrl'),
                    shift: parts.includes('shift'),
                    alt: parts.includes('alt') || parts.includes('option'),
                    meta: parts.includes('meta') || parts.includes('cmd') || parts.includes('command') || parts.includes('win'),
                    key: normalizeKey(parts.find(part => !modifiers.includes(part)) || '')
                };
            }

            function normalizeKey(key) {
                const normalized = String(key).toLowerCase().trim();
                if (normalized === ' ') return 'space';
                if (normalized === 'esc') return 'escape';
                if (normalized === 'arrowup') return 'up';
                if (normalized === 'arrowdown') return 'down';
                if (normalized === 'arrowleft') return 'left';
                if (normalized === 'arrowright') return 'right';
                return normalized;
            }

            function togglePrivacyMode() {
                fetch("{{ route('privacy-mode.toggle') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({ page: window.location.pathname })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showPrivacyModeToast(data.message || (data.active ? 'Activated.' : 'Deactivated.'), false);
                        setTimeout(() => window.location.reload(), 1000);
                    } else {
                        showPrivacyModeToast(data.error || 'Unable to change mode.', true);
                    }
                })
                .catch(err => {
                    console.error('Error toggling privacy mode:', err);
                    showPrivacyModeToast('Unable to change mode.', true);
                });
            }

            function showPrivacyModeToast(message, isError) {
                const existing = document.getElementById('privacy-mode-toggle-toast');
                if (existing) existing.remove();

                const toast = document.createElement('div');
                toast.id = 'privacy-mode-toggle-toast';
                toast.className = 'fixed top-4 right-4 z-[10000] px-4 py-2 rounded-lg shadow-lg text-sm font-semibold text-white ' + (isError ? 'bg-rose-600' : 'bg-emerald-600');
                toast.textContent = message;
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 1000);
            }
        });
    </script>
    @endif

    @stack('scripts')
</body>
</html>
