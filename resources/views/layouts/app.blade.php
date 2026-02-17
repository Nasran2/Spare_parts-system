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
            transition: max-height 0.3s ease-out;
        }
        
        .dropdown-menu.open {
            max-height: 500px;
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
            <a href="{{ route('dashboard') }}" class="nav-item flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-700 {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <i class="fas fa-home w-5"></i>
                <span>Dashboard</span>
            </a>

            <!-- User Management -->
            <div class="nav-group">
                <button onclick="toggleDropdown('user-menu')" class="nav-item flex items-center justify-between w-full px-4 py-3 rounded-lg text-gray-700 {{ request()->routeIs('users.*') || request()->routeIs('roles.*') ? 'active' : '' }}">
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-users w-5"></i>
                        <span class="text-sm">User Management</span>
                    </div>
                    <i class="fas fa-chevron-down transition-transform {{ request()->routeIs('users.*') || request()->routeIs('roles.*') ? 'rotate-180' : '' }}" id="user-menu-icon"></i>
                </button>
                <div id="user-menu" class="dropdown-menu ml-4 mt-1 space-y-1 {{ request()->routeIs('users.*') || request()->routeIs('roles.*') ? 'open' : '' }}">
                    <a href="{{ route('users.index') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('users.*') ? 'active' : '' }}">
                        <i class="fas fa-user-plus w-4"></i>
                        <span>Create User</span>
                    </a>
                    <a href="{{ route('roles.index') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('roles.*') ? 'active' : '' }}">
                        <i class="fas fa-user-shield w-4"></i>
                        <span>User Roles</span>
                    </a>
                </div>
            </div>

            <!-- Supplier Management -->
            <a href="{{ route('suppliers.index') }}" class="nav-item flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-700 {{ request()->routeIs('suppliers.*') ? 'active' : '' }}">
                <i class="fas fa-truck w-5"></i>
                <span>Suppliers</span>
            </a>

            <!-- Product Management -->
            <div class="nav-group">
                <button onclick="toggleDropdown('product-menu')" class="nav-item flex items-center justify-between w-full px-4 py-3 rounded-lg text-gray-700 {{ request()->routeIs('products.*') || request()->routeIs('products.import*') || request()->routeIs('categories.*') || request()->routeIs('brands.*') || request()->routeIs('units.*') ? 'active' : '' }}">
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-box w-5"></i>
                        <span>Products</span>
                    </div>
                    <i class="fas fa-chevron-down transition-transform {{ request()->routeIs('products.*') || request()->routeIs('products.import*') || request()->routeIs('categories.*') || request()->routeIs('brands.*') || request()->routeIs('units.*') ? 'rotate-180' : '' }}" id="product-menu-icon"></i>
                </button>
                <div id="product-menu" class="dropdown-menu ml-4 mt-1 space-y-1 {{ request()->routeIs('products.*') || request()->routeIs('products.import*') || request()->routeIs('categories.*') || request()->routeIs('brands.*') || request()->routeIs('units.*') ? 'open' : '' }}">
                    <a href="{{ route('products.index') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('products.*') ? 'active' : '' }}">
                        <i class="fas fa-list w-4"></i>
                        <span>Product List</span>
                    </a>
                    <a href="{{ route('products.create') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600">
                        <i class="fas fa-plus-circle w-4"></i>
                        <span>Add Product</span>
                    </a>
                    <a href="{{ route('products.import') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('products.import*') ? 'active' : '' }}">
                        <i class="fas fa-file-import w-4"></i>
                        <span>Import Products</span>
                    </a>
                    <a href="{{ route('products.barcode.print') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('products.barcode.*') ? 'active' : '' }}">
                        <i class="fas fa-barcode w-4"></i>
                        <span>Barcode Print</span>
                    </a>
                    <a href="{{ route('categories.index') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('categories.*') ? 'active' : '' }}">
                        <i class="fas fa-tags w-4"></i>
                        <span>Categories</span>
                    </a>
                    <a href="{{ route('brands.index') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('brands.*') ? 'active' : '' }}">
                        <i class="fas fa-copyright w-4"></i>
                        <span>Brands</span>
                    </a>
                    <a href="{{ route('units.index') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('units.*') ? 'active' : '' }}">
                        <i class="fas fa-balance-scale w-4"></i>
                        <span>Units</span>
                    </a>
                    <a href="{{ route('products.write-off.index') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('products.write-off.*') ? 'active' : '' }}">
                        <i class="fas fa-trash-alt w-4"></i>
                        <span>Write-off / Damage</span>
                    </a>
                </div>
            </div>

            <!-- Purchase -->
            <div class="nav-group">
                <button onclick="toggleDropdown('purchase-menu')" class="nav-item flex items-center justify-between w-full px-4 py-3 rounded-lg text-gray-700 {{ request()->routeIs('purchases.*') ? 'active' : '' }}">
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-shopping-cart w-5"></i>
                        <span>Purchase</span>
                    </div>
                    <i class="fas fa-chevron-down transition-transform {{ request()->routeIs('purchases.*') ? 'rotate-180' : '' }}" id="purchase-menu-icon"></i>
                </button>
                <div id="purchase-menu" class="dropdown-menu ml-4 mt-1 space-y-1 {{ request()->routeIs('purchases.*') ? 'open' : '' }}">
                    <a href="{{ route('purchases.index') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('purchases.index') || request()->routeIs('purchases.show') || request()->routeIs('purchases.edit') ? 'active' : '' }}">
                        <i class="fas fa-list-alt w-4"></i>
                        <span>Purchase List</span>
                    </a>
                    <a href="{{ route('purchases.create') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('purchases.create') ? 'active' : '' }}">
                        <i class="fas fa-cart-plus w-4"></i>
                        <span>Add Purchase</span>
                    </a>
                    <a href="{{ route('purchase-returns.index') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('purchase-returns.*') ? 'active' : '' }}">
                        <i class="fas fa-undo w-4"></i>
                        <span>Purchase Returns</span>
                    </a>
                </div>
            </div>

            <!-- Sales -->
            <div class="nav-group">
                <button onclick="toggleDropdown('sale-menu')" class="nav-item flex items-center justify-between w-full px-4 py-3 rounded-lg text-gray-700 {{ request()->routeIs('pos.*') || request()->routeIs('sales.*') || request()->routeIs('quotations.*') || request()->routeIs('sale-returns.*') ? 'active' : '' }}">
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-cash-register w-5"></i>
                        <span>Sales</span>
                    </div>
                    <i class="fas fa-chevron-down transition-transform {{ request()->routeIs('pos.*') || request()->routeIs('sales.*') || request()->routeIs('quotations.*') || request()->routeIs('sale-returns.*') ? 'rotate-180' : '' }}" id="sale-menu-icon"></i>
                </button>
                <div id="sale-menu" class="dropdown-menu ml-4 mt-1 space-y-1 {{ request()->routeIs('pos.*') || request()->routeIs('sales.*') || request()->routeIs('quotations.*') || request()->routeIs('sale-returns.*') ? 'open' : '' }}">
                    <a href="{{ route('pos.index') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('pos.*') ? 'active' : '' }}">
                        <i class="fas fa-desktop w-4"></i>
                        <span>POS</span>
                    </a>
                    <a href="{{ route('sales.index') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('sales.*') ? 'active' : '' }}">
                        <i class="fas fa-list-alt w-4"></i>
                        <span>Sales List</span>
                    </a>
                    <a href="{{ route('quotations.index') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('quotations.*') ? 'active' : '' }}">
                        <i class="fas fa-file-invoice w-4"></i>
                        <span>Quotations</span>
                    </a>
                    <a href="{{ route('sale-returns.index') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('sale-returns.*') ? 'active' : '' }}">
                        <i class="fas fa-undo w-4"></i>
                        <span>Sale Returns</span>
                    </a>
                </div>
            </div>

            <!-- Customers -->
            <a href="{{ route('customers.index') }}" class="nav-item flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-700 {{ request()->routeIs('customers.*') ? 'active' : '' }}">
                <i class="fas fa-user-tie w-5"></i>
                <span>Customers</span>
            </a>

            <!-- Expenses -->
            <div class="nav-group">
                <button onclick="toggleDropdown('expense-menu')" class="nav-item flex items-center justify-between w-full px-4 py-3 rounded-lg text-gray-700 {{ request()->routeIs('expenses.*') || request()->routeIs('expense-categories.*') ? 'active' : '' }}">
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-wallet w-5"></i>
                        <span>Expenses</span>
                    </div>
                    <i class="fas fa-chevron-down transition-transform {{ request()->routeIs('expenses.*') || request()->routeIs('expense-categories.*') ? 'rotate-180' : '' }}" id="expense-menu-icon"></i>
                </button>
                <div id="expense-menu" class="dropdown-menu ml-4 mt-1 space-y-1 {{ request()->routeIs('expenses.*') || request()->routeIs('expense-categories.*') ? 'open' : '' }}">
                    <a href="{{ route('expenses.index') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('expenses.index') || request()->routeIs('expenses.show') || request()->routeIs('expenses.edit') ? 'active' : '' }}">
                        <i class="fas fa-list-ul w-4"></i>
                        <span>All Expenses</span>
                    </a>
                    <a href="{{ route('expenses.create') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('expenses.create') ? 'active' : '' }}">
                        <i class="fas fa-plus-circle w-4"></i>
                        <span>Add Expense</span>
                    </a>
                    <a href="{{ route('expense-categories.index') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('expense-categories.*') ? 'active' : '' }}">
                        <i class="fas fa-folder-open w-4"></i>
                        <span>Categories</span>
                    </a>
                </div>
            </div>

            <!-- Reports -->
            <div class="nav-group">
                <button onclick="toggleDropdown('report-menu')" class="nav-item flex items-center justify-between w-full px-4 py-3 rounded-lg text-gray-700 {{ request()->routeIs('reports.*') ? 'active' : '' }}">
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-chart-bar w-5"></i>
                        <span>Reports</span>
                    </div>
                    <i class="fas fa-chevron-down transition-transform {{ request()->routeIs('reports.*') ? 'rotate-180' : '' }}" id="report-menu-icon"></i>
                </button>
                <div id="report-menu" class="dropdown-menu ml-4 mt-1 space-y-1 {{ request()->routeIs('reports.*') ? 'open' : '' }}">
                    <a href="{{ route('reports.sales') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('reports.sales') ? 'active' : '' }}">
                        <i class="fas fa-chart-line w-4"></i>
                        <span>Sales Report</span>
                    </a>
                    <a href="{{ route('reports.purchase') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('reports.purchase') ? 'active' : '' }}">
                        <i class="fas fa-shopping-bag w-4"></i>
                        <span>Purchase Report</span>
                    </a>
                    <a href="{{ route('reports.profit-loss') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('reports.profit-loss') ? 'active' : '' }}">
                        <i class="fas fa-coins w-4"></i>
                        <span>Profit & Loss</span>
                    </a>
                    <a href="{{ route('reports.stock') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('reports.stock') ? 'active' : '' }}">
                        <i class="fas fa-warehouse w-4"></i>
                        <span>Stock Report</span>
                    </a>
                    <a href="{{ route('reports.expense') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('reports.expense') ? 'active' : '' }}">
                        <i class="fas fa-money-bill-wave w-4"></i>
                        <span>Expense Report</span>
                    </a>
                    <a href="{{ route('reports.vat') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('reports.vat') ? 'active' : '' }}">
                        <i class="fas fa-receipt w-4"></i>
                        <span>VAT Report</span>
                    </a>
                    <a href="{{ route('reports.rates') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('reports.rates') ? 'active' : '' }}">
                        <i class="fas fa-exchange-alt w-4"></i>
                        <span>Rate Conversion</span>
                    </a>
                    <a href="{{ route('reports.receive') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('reports.receive') ? 'active' : '' }}">
                        <i class="fas fa-arrow-down w-4"></i>
                        <span>Receive Report</span>
                    </a>
                    <a href="{{ route('reports.debit') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('reports.debit') ? 'active' : '' }}">
                        <i class="fas fa-arrow-up w-4"></i>
                        <span>Debit Report</span>
                    </a>
                    <a href="{{ route('reports.due-bills') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('reports.due-bills') ? 'active' : '' }}">
                        <i class="fas fa-file-invoice-dollar w-4"></i>
                        <span>Due Bills Report</span>
                    </a>
                    <a href="{{ route('reports.customer-due') }}" class="nav-item flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 {{ request()->routeIs('reports.customer-due') ? 'active' : '' }}">
                        <i class="fas fa-user-clock w-4"></i>
                        <span>Customer Due Report</span>
                    </a>
                </div>
            </div>

            <!-- Notifications -->
            <div class="nav-group">
                <button onclick="toggleSubmenu('notifications-menu')" class="nav-item w-full flex items-center justify-between px-4 py-3 rounded-lg text-gray-700 {{ request()->routeIs('notifications.*') ? 'active' : '' }}">
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-bell w-5"></i>
                        <span>Notifications</span>
                    </div>
                    <i class="fas fa-chevron-down text-xs transition-transform {{ request()->routeIs('notifications.*') ? 'rotate-180' : '' }}" id="notifications-menu-icon"></i>
                </button>
                <div id="notifications-menu" class="submenu {{ request()->routeIs('notifications.*') ? '' : 'hidden' }} ml-8 mt-1 space-y-1">
                    <a href="{{ route('notifications.index') }}" class="flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-50 {{ request()->routeIs('notifications.index') ? 'active' : '' }}">
                        <i class="fas fa-bell w-4"></i>
                        <span>Notifications</span>
                    </a>
                    <a href="{{ route('notifications.history') }}" class="flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-50 {{ request()->routeIs('notifications.history') ? 'active' : '' }}">
                        <i class="fas fa-history w-4"></i>
                        <span>Message History</span>
                    </a>
                    <a href="{{ route('notifications.send') }}" class="flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-50 {{ request()->routeIs('notifications.send') ? 'active' : '' }}">
                        <i class="fas fa-paper-plane w-4"></i>
                        <span>Send Message</span>
                    </a>
                    <a href="{{ route('notifications.settings') }}" class="flex items-center space-x-3 px-4 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-50 {{ request()->routeIs('notifications.settings') ? 'active' : '' }}">
                        <i class="fas fa-cog w-4"></i>
                        <span>Notification Settings</span>
                    </a>
                </div>
            </div>

            <!-- Settings -->
            <a href="{{ route('settings.index') }}" class="nav-item flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-700 {{ request()->routeIs('settings.*') ? 'active' : '' }}">
                <i class="fas fa-cog w-5"></i>
                <span>Settings</span>
            </a>

            <!-- Activity Log -->
            <a href="{{ route('activity-log.index') }}" class="nav-item flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-700 {{ request()->routeIs('activity-log.*') ? 'active' : '' }}">
                <i class="fas fa-history w-5"></i>
                <span>Activity Log</span>
            </a>
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
                    <a href="{{ route('pos.index') }}" class="hidden md:flex items-center space-x-2 px-4 py-2 bg-gradient-blue text-white rounded-lg hover:opacity-90 transition">
                        <i class="fas fa-cash-register"></i>
                        <span>Open POS</span>
                    </a>
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <main class="flex-1 p-4 md:p-6 lg:p-8">
            @if (session('success'))
                <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded-lg shadow-sm">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-500 mr-3 text-xl"></i>
                        <p class="text-green-700">{{ session('success') }}</p>
                    </div>
                </div>
            @endif

            @if (session('error'))
                <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded-lg shadow-sm">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle text-red-500 mr-3 text-xl"></i>
                        <p class="text-red-700">{{ session('error') }}</p>
                    </div>
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
    @if(!request()->routeIs('pos.index'))
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
    @stack('scripts')
</body>
</html>
