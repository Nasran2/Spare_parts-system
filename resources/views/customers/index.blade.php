@extends('layouts.app')

@section('title', 'Customers')
@section('page-title', 'Customer Management')

@section('content')
@php
    $controls = is_array($controls ?? null) ? $controls : [];
    $customerVisiblePct = (float) ($controls['customer_visible_percentage'] ?? 100);
    $applyPct = function ($value, $pct) {
        $pct = max(0, min(100, (float) $pct));
        return (float) $value * ($pct / 100);
    };
    $maskMoney = function ($value, $forceHide = false) use ($controls, $customerVisiblePct, $applyPct) {
        if ($forceHide || !empty($controls['hide_price_wise_data'])) {
            return '—';
        }

        return number_format($applyPct((float) $value, $customerVisiblePct), (int) (\App\Models\Setting::get('decimal_places', 2)));
    };
@endphp
<div class="space-y-6">
    
    <!-- Success/Error Messages -->
    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline">{{ session('success') }}</span>
        </div>
    @endif
    @if(session('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline">{{ session('error') }}</span>
        </div>
    @endif

    <!-- Header Actions -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Customer Management</h3>
            <p class="text-sm text-gray-600">Manage your customers and track their purchases</p>
        </div>
        <button 
            onclick="openCreateModal()" 
            class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg hover:from-blue-700 hover:to-blue-800 transition shadow-lg"
        >
            <i class="fas fa-plus mr-2"></i>Add New Customer
        </button>
    </div>

    <!-- KPI Summary Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <!-- Total Customers -->
        <a href="{{ route('customers.index') }}" class="bg-white rounded-xl shadow-sm p-4 border border-gray-100 hover:shadow-md transition flex items-center justify-between group">
            <div>
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Customers</p>
                <h4 class="text-2xl font-bold text-gray-800 mt-1">{{ number_format($metrics['total'] ?? 0) }}</h4>
                <p class="text-xs text-blue-600 mt-1 flex items-center"><i class="fas fa-list mr-1"></i>All records</p>
            </div>
            <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center group-hover:bg-blue-600 group-hover:text-white transition">
                <i class="fas fa-users text-xl"></i>
            </div>
        </a>

        <!-- Active Customers -->
        <a href="{{ route('customers.index', array_merge(request()->query(), ['status' => 'active'])) }}" class="bg-white rounded-xl shadow-sm p-4 border border-gray-100 hover:shadow-md transition flex items-center justify-between group {{ request('status') === 'active' ? 'ring-2 ring-green-500' : '' }}">
            <div>
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Active</p>
                <h4 class="text-2xl font-bold text-green-600 mt-1">{{ number_format($metrics['active'] ?? 0) }}</h4>
                <p class="text-xs text-green-600 mt-1 flex items-center"><i class="fas fa-check-circle mr-1"></i>Active accounts</p>
            </div>
            <div class="w-12 h-12 bg-green-50 text-green-600 rounded-xl flex items-center justify-center group-hover:bg-green-600 group-hover:text-white transition">
                <i class="fas fa-user-check text-xl"></i>
            </div>
        </a>

        <!-- With Due Amount -->
        <a href="{{ route('customers.index', array_merge(request()->query(), ['balance_type' => 'due'])) }}" class="bg-white rounded-xl shadow-sm p-4 border border-gray-100 hover:shadow-md transition flex items-center justify-between group {{ request('balance_type') === 'due' ? 'ring-2 ring-red-500' : '' }}">
            <div>
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">With Sales Due</p>
                <h4 class="text-2xl font-bold text-red-600 mt-1">{{ number_format($metrics['with_due'] ?? 0) }}</h4>
                <p class="text-xs text-red-500 mt-1 flex items-center"><i class="fas fa-exclamation-circle mr-1"></i>Pending payment</p>
            </div>
            <div class="w-12 h-12 bg-red-50 text-red-600 rounded-xl flex items-center justify-center group-hover:bg-red-600 group-hover:text-white transition">
                <i class="fas fa-receipt text-xl"></i>
            </div>
        </a>

        <!-- With Advance Balance -->
        <a href="{{ route('customers.index', array_merge(request()->query(), ['balance_type' => 'advance'])) }}" class="bg-white rounded-xl shadow-sm p-4 border border-gray-100 hover:shadow-md transition flex items-center justify-between group {{ request('balance_type') === 'advance' ? 'ring-2 ring-purple-500' : '' }}">
            <div>
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">With Advance</p>
                <h4 class="text-2xl font-bold text-purple-600 mt-1">{{ number_format($metrics['with_advance'] ?? 0) }}</h4>
                <p class="text-xs text-purple-600 mt-1 flex items-center"><i class="fas fa-wallet mr-1"></i>Prepaid balance</p>
            </div>
            <div class="w-12 h-12 bg-purple-50 text-purple-600 rounded-xl flex items-center justify-center group-hover:bg-purple-600 group-hover:text-white transition">
                <i class="fas fa-wallet text-xl"></i>
            </div>
        </a>
    </div>

    <!-- Search and Filter Bar -->
    <div class="bg-white rounded-xl shadow-md p-5 border border-gray-100">
        <form method="GET" action="{{ route('customers.index') }}" id="customerFilterForm">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                <!-- Search Bar (5 cols) -->
                <div class="md:col-span-12 lg:col-span-4">
                    <label class="block text-xs font-semibold text-gray-600 mb-1">
                        <i class="fas fa-search text-blue-600 mr-1"></i>Search Customers
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                            <i class="fas fa-search"></i>
                        </div>
                        <input
                            type="text"
                            id="customerSearchInput"
                            name="search"
                            value="{{ request('search') }}"
                            placeholder="Search by name, phone, email, TIN, address, ID..."
                            class="w-full pl-10 pr-9 py-2.5 bg-gray-50 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:bg-white focus:border-blue-500 transition"
                            autocomplete="off"
                        >
                        @if(request('search'))
                            <a href="{{ route('customers.index', request()->except('search')) }}" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600" title="Clear search">
                                <i class="fas fa-times-circle"></i>
                            </a>
                        @else
                            <button type="button" id="clearSearchBtn" class="hidden absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600" title="Clear">
                                <i class="fas fa-times-circle"></i>
                            </button>
                        @endif
                    </div>
                </div>

                <!-- Status Filter (2 cols) -->
                <div class="md:col-span-4 lg:col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 mb-1">
                        <i class="fas fa-toggle-on text-gray-400 mr-1"></i>Status
                    </label>
                    <select
                        name="status"
                        id="statusFilterSelect"
                        class="w-full px-3 py-2.5 bg-gray-50 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:bg-white focus:border-blue-500 transition"
                    >
                        <option value="">All Status</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>

                <!-- Balance / Due Filter (2 cols) -->
                <div class="md:col-span-4 lg:col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 mb-1">
                        <i class="fas fa-coins text-gray-400 mr-1"></i>Balance
                    </label>
                    <select
                        name="balance_type"
                        id="balanceFilterSelect"
                        class="w-full px-3 py-2.5 bg-gray-50 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:bg-white focus:border-blue-500 transition"
                    >
                        <option value="">All Balances</option>
                        <option value="due" {{ request('balance_type') === 'due' ? 'selected' : '' }}>With Sales Due</option>
                        <option value="advance" {{ request('balance_type') === 'advance' ? 'selected' : '' }}>With Advance</option>
                        <option value="zero" {{ request('balance_type') === 'zero' ? 'selected' : '' }}>Zero Balance</option>
                    </select>
                </div>

                <!-- Sort By (2 cols) -->
                <div class="md:col-span-4 lg:col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 mb-1">
                        <i class="fas fa-sort text-gray-400 mr-1"></i>Sort By
                    </label>
                    <select
                        name="sort_by"
                        id="sortFilterSelect"
                        class="w-full px-3 py-2.5 bg-gray-50 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:bg-white focus:border-blue-500 transition"
                    >
                        <option value="name_asc" {{ request('sort_by', 'name_asc') === 'name_asc' ? 'selected' : '' }}>Name (A to Z)</option>
                        <option value="name_desc" {{ request('sort_by') === 'name_desc' ? 'selected' : '' }}>Name (Z to A)</option>
                        <option value="due_desc" {{ request('sort_by') === 'due_desc' ? 'selected' : '' }}>Highest Due</option>
                        <option value="due_asc" {{ request('sort_by') === 'due_asc' ? 'selected' : '' }}>Lowest Due</option>
                        <option value="advance_desc" {{ request('sort_by') === 'advance_desc' ? 'selected' : '' }}>Highest Advance</option>
                        <option value="newest" {{ request('sort_by') === 'newest' ? 'selected' : '' }}>Newest Added</option>
                        <option value="oldest" {{ request('sort_by') === 'oldest' ? 'selected' : '' }}>Oldest Added</option>
                    </select>
                </div>

                <!-- Actions: Filter & Reset Buttons (2 cols) -->
                <div class="md:col-span-12 lg:col-span-2 flex gap-2">
                    <button
                        type="submit"
                        class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium text-sm transition shadow-sm flex items-center justify-center gap-1.5"
                        title="Apply filters"
                    >
                        <i class="fas fa-filter"></i>
                        <span>Filter</span>
                    </button>
                    <a
                        href="{{ route('customers.index') }}"
                        class="px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm transition flex items-center justify-center gap-1"
                        title="Reset all filters"
                    >
                        <i class="fas fa-redo"></i>
                        <span class="md:hidden lg:inline text-xs">Reset</span>
                    </a>
                </div>
            </div>

            <!-- Rows per page and Active filter chips -->
            <div class="mt-3 pt-3 border-t border-gray-100 flex flex-wrap items-center justify-between text-xs text-gray-500 gap-2">
                <div class="flex items-center gap-2">
                    <span class="font-medium">Show:</span>
                    <select name="per_page" onchange="this.form.submit()" class="text-xs py-1 px-2 bg-gray-50 border border-gray-300 rounded focus:ring-blue-500">
                        <option value="15" {{ request('per_page') == '15' ? 'selected' : '' }}>15 per page</option>
                        <option value="25" {{ request('per_page', '25') == '25' ? 'selected' : '' }}>25 per page</option>
                        <option value="50" {{ request('per_page') == '50' ? 'selected' : '' }}>50 per page</option>
                        <option value="100" {{ request('per_page') == '100' ? 'selected' : '' }}>100 per page</option>
                        <option value="all" {{ request('per_page') == 'all' ? 'selected' : '' }}>All Customers</option>
                    </select>

                    <span class="text-gray-300">|</span>

                    <span id="resultsCount">
                        @if(method_exists($customers, 'total'))
                            Showing {{ $customers->firstItem() ?? 0 }} to {{ $customers->lastItem() ?? 0 }} of {{ $customers->total() }} customers
                        @else
                            Showing {{ count($customers) }} customers
                        @endif
                    </span>
                </div>

                <!-- Active Filter Tags -->
                @php
                    $hasActiveFilters = request('search') || request('status') || request('balance_type') || (request('sort_by') && request('sort_by') !== 'name_asc') || (request('per_page') && request('per_page') != '25');
                @endphp
                @if($hasActiveFilters)
                    <div class="flex items-center flex-wrap gap-1.5">
                        <span class="text-gray-400">Active filters:</span>
                        @if(request('search'))
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-blue-50 text-blue-700 border border-blue-200">
                                Keyword: "{{ request('search') }}"
                                <a href="{{ route('customers.index', request()->except('search')) }}" class="ml-1 text-blue-500 hover:text-blue-800"><i class="fas fa-times text-[10px]"></i></a>
                            </span>
                        @endif
                        @if(request('status'))
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-green-50 text-green-700 border border-green-200">
                                Status: {{ ucfirst(request('status')) }}
                                <a href="{{ route('customers.index', request()->except('status')) }}" class="ml-1 text-green-500 hover:text-green-800"><i class="fas fa-times text-[10px]"></i></a>
                            </span>
                        @endif
                        @if(request('balance_type'))
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-amber-50 text-amber-700 border border-amber-200">
                                Balance: {{ request('balance_type') === 'due' ? 'With Sales Due' : (request('balance_type') === 'advance' ? 'With Advance' : 'Zero Balance') }}
                                <a href="{{ route('customers.index', request()->except('balance_type')) }}" class="ml-1 text-amber-500 hover:text-amber-800"><i class="fas fa-times text-[10px]"></i></a>
                            </span>
                        @endif
                        @if(request('sort_by') && request('sort_by') !== 'name_asc')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-purple-50 text-purple-700 border border-purple-200">
                                Sort: {{ str_replace('_', ' ', request('sort_by')) }}
                                <a href="{{ route('customers.index', request()->except('sort_by')) }}" class="ml-1 text-purple-500 hover:text-purple-800"><i class="fas fa-times text-[10px]"></i></a>
                            </span>
                        @endif
                        <a href="{{ route('customers.index') }}" class="text-xs text-red-600 hover:text-red-800 font-semibold ml-1">
                            Clear all
                        </a>
                    </div>
                @endif
            </div>
        </form>
    </div>

    <!-- Customers Table -->
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table data-export-table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Customer</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Contact</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Address</th>
                        <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase">Total Due</th>
                        <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase">Status</th>
                        <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200" id="customerTableBody">
                    @forelse($customers as $customer)
                    <tr class="hover:bg-gray-50 transition customer-row"
                        data-search="{{ strtolower($customer->name . ' ' . $customer->id . ' ' . ($customer->phone ?? '') . ' ' . ($customer->email ?? '') . ' ' . ($customer->address ?? '') . ' ' . ($customer->city ?? '') . ' ' . ($customer->tin ?? '')) }}"
                        data-status="{{ $customer->is_active ? 'active' : 'inactive' }}"
                        data-has-due="{{ $customer->sales_due_amount > 0 ? 'yes' : 'no' }}"
                        data-has-advance="{{ $customer->advance_balance > 0 ? 'yes' : 'no' }}">
                        <td class="px-6 py-4">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-gradient-to-r from-green-500 to-green-600 rounded-full flex items-center justify-center text-white font-bold mr-3 flex-shrink-0">
                                    {{ strtoupper(substr($customer->name, 0, 1)) }}
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-800">{{ $customer->name }}</p>
                                    <p class="text-xs text-gray-500">ID: {{ $customer->id }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm">
                                <p class="text-gray-700"><i class="fas fa-phone text-blue-600 mr-1"></i> {{ $customer->phone ?? 'N/A' }}</p>
                                <p class="text-gray-500 text-xs"><i class="fas fa-envelope text-blue-600 mr-1"></i> {{ $customer->email ?? 'N/A' }}</p>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-sm text-gray-700">{{ $customer->address ?? 'No address' }}</span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            @php($position = \App\Models\Setting::get('currency_position', 'before'))
                            @php($hideAmounts = !empty($controls['hide_supplier_payments']) || !empty($controls['hide_invoice_details']))
                            <div class="flex flex-col items-center gap-1 text-sm">
                                <div class="text-xs text-gray-500 font-semibold w-full flex justify-between" title="Sales Due">
                                    <span>Sales:</span> 
                                    <span class="font-bold text-red-600 ml-2">
                                        @if($position === 'before')
                                            {{ $currency }} {{ $maskMoney($customer->sales_due_amount, $hideAmounts) }}
                                        @else
                                            {{ $maskMoney($customer->sales_due_amount, $hideAmounts) }} {{ $currency }}
                                        @endif
                                    </span>
                                </div>
                                @if($customer->advance_balance > 0)
                                <div class="text-xs text-green-700 font-semibold w-full flex justify-between" title="Advance Payment Available">
                                    <span>Advance:</span> 
                                    <span class="font-bold ml-2">
                                        @if($position === 'before')
                                            {{ $currency }} {{ $maskMoney($customer->advance_balance, $hideAmounts) }}
                                        @else
                                            {{ $maskMoney($customer->advance_balance, $hideAmounts) }} {{ $currency }}
                                        @endif
                                    </span>
                                </div>
                                @endif

                            </div>
                        </td>
                        <td class="px-6 py-4 text-center">
                            @if($customer->is_active)
                                <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-semibold">Active</span>
                            @else
                                <span class="px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-xs font-semibold">Inactive</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center">
                            <div class="flex items-center justify-center space-x-2">
                                @if(isset($customer->due_amount) && $customer->due_amount >= 1)
                                    <a href="#" onclick="openBulkPaymentModal('{{ route('customers.bulk-payment', $customer->id) }}'); return false;" class="p-2 text-green-600 hover:bg-green-50 rounded-lg transition" title="Make Payment">
                                        <i class="fas fa-money-bill-wave"></i>
                                    </a>
                                @endif
                                <button onclick="openAdvanceModal({{ $customer->id }}, '{{ addslashes($customer->name) }}')" class="p-2 text-purple-600 hover:bg-purple-50 rounded-lg transition" title="Add Advance Payment">
                                    <i class="fas fa-wallet"></i>
                                </button>
                                <button onclick="viewCustomer({{ $customer->id }})" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition" title="View">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button onclick="editCustomer({{ $customer->id }})" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button onclick="deleteCustomer({{ $customer->id }})" class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center">
                            <i class="fas fa-user-tie text-6xl text-gray-300 mb-4"></i>
                            @if(request('search') || request('status') || request('balance_type'))
                                <p class="text-gray-700 text-lg font-medium">No customers found matching your filter criteria</p>
                                <p class="text-gray-400 text-sm mt-1">Try adjusting your keyword or reset filters to see all customers.</p>
                                <div class="mt-4 flex items-center justify-center gap-3">
                                    <a href="{{ route('customers.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition text-sm">
                                        <i class="fas fa-redo mr-1.5"></i>Reset Filters
                                    </a>
                                    <button onclick="openCreateModal()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm">
                                        <i class="fas fa-plus mr-1.5"></i>Add Customer
                                    </button>
                                </div>
                            @else
                                <p class="text-gray-500 text-lg">No customers found</p>
                                <button onclick="openCreateModal()" class="mt-4 px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                                    <i class="fas fa-plus mr-2"></i>Add Customer
                                </button>
                            @endif
                        </td>
                    </tr>
                    @endforelse

                    <!-- Client-side live search empty state (hidden by default) -->
                    <tr id="clientNoMatchRow" class="hidden">
                        <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                            <i class="fas fa-search text-4xl text-gray-300 mb-3 block"></i>
                            <p class="font-medium text-gray-700">No customers on this page match your search term.</p>
                            <p class="text-xs text-gray-400 mt-1">Press <kbd class="px-1.5 py-0.5 bg-gray-100 border rounded text-gray-600 font-mono">Enter</kbd> or click <strong>Filter</strong> to search across all records in the database.</p>
                            <button type="button" onclick="document.getElementById('customerFilterForm').submit()" class="mt-3 px-4 py-1.5 bg-blue-600 text-white text-xs rounded-lg hover:bg-blue-700 transition">
                                <i class="fas fa-search mr-1"></i>Search Entire Database
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        @if(method_exists($customers, 'hasPages') && $customers->hasPages())
            <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                {{ $customers->links() }}
            </div>
        @endif
    </div>

</div>

<!-- Create Customer Modal -->
<div id="createModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-2/3 lg:w-1/2 shadow-lg rounded-md bg-white">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900">Add New Customer</h3>
            <button onclick="closeCreateModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="createForm" onsubmit="submitCreate(event)">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Phone</label>
                    <input type="text" name="phone" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Purchaser TIN</label>
                    <input type="text" name="tin" inputmode="numeric" pattern="[0-9]{9,12}" maxlength="12" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">City</label>
                    <input type="text" name="city" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Country</label>
                    <input type="text" name="country" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Opening Balance</label>
                    <input type="number" name="opening_balance" step="0.01" value="0" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Address</label>
                    <textarea name="address" rows="2" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
                <div class="md:col-span-2">
                    <label class="flex items-center">
                        <input type="checkbox" name="is_active" value="1" checked class="mr-2">
                        <span class="text-sm font-semibold text-gray-700">Active</span>
                    </label>
                </div>
            </div>
            <div class="flex justify-end space-x-2 mt-6">
                <button type="button" onclick="closeCreateModal()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Create Customer</button>
            </div>
        </form>
    </div>
</div>

<!-- View Customer Modal -->
<div id="viewModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-10 mx-auto p-6 border w-11/12 md:w-5/6 lg:w-4/5 shadow-lg rounded-lg bg-white max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-6 pb-4 border-b">
            <h3 class="text-xl font-bold text-gray-900">Customer Details</h3>
            <button onclick="closeViewModal()" class="text-gray-400 hover:text-gray-600 text-xl">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <!-- Customer Info Header -->
        <div id="customerHeader" class="bg-gradient-to-r from-blue-50 to-blue-100 p-4 rounded-lg mb-4">
            <!-- Loaded via AJAX -->
        </div>
        
        <!-- Payment Reminder Action Bar -->
        <div id="reminderActionBar" class="mb-4 bg-white border rounded-lg p-3 flex justify-between items-center">
            <div class="text-sm text-gray-600">
                <i class="fas fa-info-circle text-blue-600 mr-1"></i>
                <span>Send payment reminders via SMS or WhatsApp</span>
            </div>
            <button onclick="openPaymentReminderModal(window.currentViewingCustomerId)" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm font-medium transition flex items-center gap-2">
                <i class="fas fa-paper-plane"></i>
                Send Reminder
            </button>
        </div>
        
        <div class="mb-4">
            <div class="flex justify-between items-end border-b border-gray-200">
                <nav class="flex -mb-px space-x-4">
                    <button onclick="switchTab('ledger')" id="tab-ledger" class="tab-button active border-b-2 border-blue-600 py-3 px-4 text-sm font-semibold text-blue-600">
                        <i class="fas fa-book mr-2"></i>Ledger
                    </button>
                    <button onclick="switchTab('sales')" id="tab-sales" class="tab-button border-b-2 border-transparent py-3 px-4 text-sm font-semibold text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        <i class="fas fa-shopping-cart mr-2"></i>Sales
                    </button>
                    <button onclick="switchTab('payments')" id="tab-payments" class="tab-button border-b-2 border-transparent py-3 px-4 text-sm font-semibold text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        <i class="fas fa-money-bill-wave mr-2"></i>Payments
                    </button>
                </nav>
                <div class="pb-2 pr-2">
                    <button onclick="downloadCustomerPdf()" class="text-sm bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded-md shadow-sm flex items-center transition-colors">
                        <i class="fas fa-file-pdf mr-2"></i>Download PDF
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Tab Content -->
        <div id="viewContent">
            <!-- Ledger Tab -->
            <div id="content-ledger" class="tab-content">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
                    <!-- Account Summary Card -->
                    <div class="lg:col-span-1 bg-white border rounded-lg p-4 shadow-sm">
                        <h4 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                            <i class="fas fa-chart-line text-blue-600 mr-2"></i>Account Summary
                        </h4>
                        <div id="accountSummary" class="space-y-3">
                            <!-- Loaded via AJAX -->
                        </div>
                    </div>
                    
                    <!-- Ledger Table -->
                    <div class="lg:col-span-2">
                        <div class="bg-white border rounded-lg shadow-sm overflow-hidden">
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead class="bg-blue-600 text-white">
                                        <tr>
                                            <th class="px-4 py-3 text-left">Date</th>
                                            <th class="px-4 py-3 text-left">Reference No</th>
                                            <th class="px-4 py-3 text-left">Type</th>
                                            <th class="px-4 py-3 text-right">Debit</th>
                                            <th class="px-4 py-3 text-right">Credit</th>
                                            <th class="px-4 py-3 text-center">Payment Method</th>
                                        </tr>
                                    </thead>
                                    <tbody id="ledgerTable" class="divide-y divide-gray-200">
                                        <!-- Loaded via AJAX -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Sales Tab -->
            <div id="content-sales" class="tab-content hidden">
                <div class="bg-white border rounded-lg shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="px-4 py-3 text-left">Invoice No</th>
                                    <th class="px-4 py-3 text-left">Date</th>
                                    <th class="px-4 py-3 text-right">Amount</th>
                                    <th class="px-4 py-3 text-right">Paid</th>
                                    <th class="px-4 py-3 text-right">Due</th>
                                    <th class="px-4 py-3 text-center">Status</th>
                                    <th class="px-4 py-3 text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="salesTable" class="divide-y divide-gray-200">
                                <!-- Loaded via AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Payments Tab -->
            <div id="content-payments" class="tab-content hidden">
                <div class="bg-white border rounded-lg shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="px-4 py-3 text-left">Date</th>
                                    <th class="px-4 py-3 text-left">Reference</th>
                                    <th class="px-4 py-3 text-left">Invoice</th>
                                    <th class="px-4 py-3 text-right">Amount</th>
                                    <th class="px-4 py-3 text-center">Method</th>
                                    <th class="px-4 py-3 text-left">Note</th>
                                </tr>
                            </thead>
                            <tbody id="paymentsTable" class="divide-y divide-gray-200">
                                <!-- Loaded via AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Customer Modal -->
<div id="editModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-2/3 lg:w-1/2 shadow-lg rounded-md bg-white">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900">Edit Customer</h3>
            <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="editForm" onsubmit="submitEdit(event)">
            @csrf
            @method('PUT')
            <input type="hidden" id="edit_customer_id" name="customer_id">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                    <input type="text" id="edit_name" name="name" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Phone</label>
                    <input type="text" id="edit_phone" name="phone" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Purchaser TIN</label>
                    <input type="text" id="edit_tin" name="tin" inputmode="numeric" pattern="[0-9]{9,12}" maxlength="12" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Email</label>
                    <input type="email" id="edit_email" name="email" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">City</label>
                    <input type="text" id="edit_city" name="city" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Country</label>
                    <input type="text" id="edit_country" name="country" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Opening Balance</label>
                    <input type="number" id="edit_opening_balance" name="opening_balance" step="0.01" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Address</label>
                    <textarea id="edit_address" name="address" rows="2" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
                <div class="md:col-span-2">
                    <label class="flex items-center">
                        <input type="checkbox" id="edit_is_active" name="is_active" value="1" class="mr-2">
                        <span class="text-sm font-semibold text-gray-700">Active</span>
                    </label>
                </div>
            </div>
            <div class="flex justify-end space-x-2 mt-6">
                <button type="button" onclick="closeEditModal()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Update Customer</button>
            </div>
        </form>
    </div>
</div>

<script>
const CURRENCY = @json($currency . ' ');
const BASE_URL = '{{ url('/') }}';

function openCreateModal() {
    document.getElementById('createModal').classList.remove('hidden');
}

function closeCreateModal() {
    document.getElementById('createModal').classList.add('hidden');
    document.getElementById('createForm').reset();
}

function submitCreate(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    
    fetch('{{ route("customers.store") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeCreateModal();
            window.location.reload();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to create customer');
    });
}

function viewCustomer(id) {
    window.currentActiveTab = 'ledger';
    switchTab('ledger');
    fetch(`${BASE_URL}/customers/${id}`, { headers: { 'Accept': 'application/json' } })
    .then(r => r.json())
    .then(data => {
        if (!data.success) return;
        const { customer, period_totals, overall_totals, transactions, start_date, end_date } = data;
        
        window.currentCustomerStartDate = start_date;
        window.currentCustomerEndDate = end_date;

        renderCustomerHeader(customer);
        renderAccountSummary(period_totals, overall_totals, start_date, end_date);
        renderLedger(transactions);
        renderSales(transactions);
        renderPayments(transactions);

        // Default to Ledger tab
        switchTab('ledger');
        document.getElementById('viewModal').classList.remove('hidden');
    })
    .catch(err => {
        console.error(err);
        alert('Failed to load customer details');
    });
}

function renderCustomerHeader(c) {
    window.currentViewingCustomerId = c.id;
    const el = document.getElementById('customerHeader');
    el.innerHTML = `
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 text-sm">
            <div><span class="font-semibold text-gray-700">Name</span><div>${securityEscapeHtml(c.name)}</div></div>
            <div><span class="font-semibold text-gray-700">Phone</span><div>${securityEscapeHtml(c.phone || 'N/A')}</div></div>
            <div><span class="font-semibold text-gray-700">Email</span><div>${securityEscapeHtml(c.email || 'N/A')}</div></div>
            <div><span class="font-semibold text-gray-700">City</span><div>${securityEscapeHtml(c.city || 'N/A')}</div></div>
            <div><span class="font-semibold text-gray-700">Country</span><div>${securityEscapeHtml(c.country || 'N/A')}</div></div>
            <div><span class="font-semibold text-gray-700">Opening Balance</span><div>${CURRENCY}${Number(c.opening_balance||0).toFixed(2)}</div></div>
            <div class="sm:col-span-2 md:col-span-3"><span class="font-semibold text-gray-700">Address</span><div>${securityEscapeHtml(c.address || 'No address')}</div></div>
            <div><span class="font-semibold text-gray-700">Status</span><div>${c.is_active ? '<span class="px-2 py-1 bg-green-100 text-green-700 rounded-full text-xs">Active</span>' : '<span class="px-2 py-1 bg-gray-100 text-gray-700 rounded-full text-xs">Inactive</span>'}</div></div>
        </div>
    `;
}

function renderAccountSummary(periodTotals, overallTotals, start, end) {
    const wrap = document.getElementById('accountSummary');
    let duesHtml = '';
    const hasSalesDue = Number(overallTotals.sales_due) > 0;
    
    if (hasSalesDue) {
        duesHtml += `<div class="flex justify-between text-sm pl-2 border-l-2 border-orange-400"><span class="text-gray-600">Sales Due</span><span class="font-semibold text-orange-600">${CURRENCY}${fmt(overallTotals.sales_due)}</span></div>`;
    }

    wrap.innerHTML = `
        <div class="text-xs text-gray-600 flex items-center gap-2"><i class="fas fa-info-circle text-blue-500"></i>${securityEscapeHtml(formatDate(start))} To ${securityEscapeHtml(formatDate(end))}</div>
        <div class="flex justify-between text-sm"><span class="text-gray-600">Total invoice</span><span class="font-semibold">${CURRENCY}${fmt(periodTotals.invoice)}</span></div>
        <div class="flex justify-between text-sm"><span class="text-gray-600">Total paid</span><span class="font-semibold">${CURRENCY}${fmt(periodTotals.paid)}</span></div>
        <div class="border-t pt-2"></div>
        <div class="text-sm text-gray-600 mb-1">Overall Summary</div>
        <div class="flex justify-between text-sm"><span class="text-gray-600">Total invoice</span><span class="font-semibold">${CURRENCY}${fmt(overallTotals.invoice)}</span></div>
        <div class="flex justify-between text-sm mb-1"><span class="text-gray-600">Total paid</span><span class="font-semibold">${CURRENCY}${fmt(overallTotals.paid)}</span></div>
        ${duesHtml}
        <div class="flex justify-between text-sm mt-2 pt-2 border-t"><span class="text-gray-700 font-semibold">Balance due</span><span class="font-bold ${Number(overallTotals.balance)>0?'text-red-600':'text-green-600'}">${CURRENCY}${fmt(overallTotals.balance)}</span></div>
    `;
}

function renderLedger(trans) {
    const tbody = document.getElementById('ledgerTable');
    tbody.innerHTML = (trans || []).map(t => `
        <tr class="hover:bg-gray-50">
            <td class="px-4 py-2">${securityEscapeHtml(formatDate(t.date))}</td>
            <td class="px-4 py-2">${securityEscapeHtml(t.reference)}</td>
            <td class="px-4 py-2">${securityEscapeHtml(t.type)}</td>
            <td class="px-4 py-2">${securityEscapeHtml(t.location || '')}</td>
            <td class="px-4 py-2 text-center">${badge(t.payment_status)}</td>
            <td class="px-4 py-2 text-right">${t.debit? CURRENCY+fmt(t.debit): '—'}</td>
            <td class="px-4 py-2 text-right">${t.credit? CURRENCY+fmt(t.credit): '—'}</td>
            <td class="px-4 py-2 text-center">${securityEscapeHtml(t.payment_method || '—')}</td>
            <td class="px-4 py-2">${securityEscapeHtml(t.notes || '—')}</td>
        </tr>
    `).join('');
}

function renderSales(trans) {
    const sales = (trans || []).filter(t => t.type === 'Sell');
    const tbody = document.getElementById('salesTable');
    tbody.innerHTML = sales.map(s => `
        <tr class="hover:bg-gray-50">
            <td class="px-4 py-2">${securityEscapeHtml(s.invoice)}</td>
            <td class="px-4 py-2">${securityEscapeHtml(formatDate(s.sale_date || s.date))}</td>
            <td class="px-4 py-2 text-right">${CURRENCY}${fmt(s.debit)}</td>
            <td class="px-4 py-2 text-right">${CURRENCY}${fmt(s.paid || 0)}</td>
            <td class="px-4 py-2 text-right">${CURRENCY}${fmt(s.due || (s.debit - (s.paid||0)))}</td>
            <td class="px-4 py-2 text-center">${badge(s.payment_status)}</td>
            <td class="px-4 py-2 text-center">
                ${s.sale_id ? `<a class="text-blue-600 hover:underline" href="${BASE_URL}/sales/${s.sale_id}" target="_blank">View</a>` : ''}
            </td>
        </tr>
    `).join('');
}

function renderPayments(trans) {
    const pays = (trans || []).filter(t => t.type === 'Payment');
    const tbody = document.getElementById('paymentsTable');
    tbody.innerHTML = pays.map(p => `
        <tr class="hover:bg-gray-50">
            <td class="px-4 py-2">${securityEscapeHtml(formatDate(p.date))}</td>
            <td class="px-4 py-2">${securityEscapeHtml(p.reference)}</td>
            <td class="px-4 py-2">${securityEscapeHtml(p.invoice)}</td>
            <td class="px-4 py-2 text-right">${CURRENCY}${fmt(p.credit)}</td>
            <td class="px-4 py-2 text-center">${securityEscapeHtml(p.payment_method || '—')}</td>
            <td class="px-4 py-2">${securityEscapeHtml(p.notes || '—')}</td>
        </tr>
    `).join('');
}

function switchTab(tab) {
    window.currentActiveTab = tab;
    const tabs = ['ledger','sales','payments'];
    tabs.forEach(t => {
        document.getElementById(`content-${t}`).classList.toggle('hidden', t !== tab);
        document.getElementById(`tab-${t}`).classList.toggle('text-blue-600', t === tab);
        document.getElementById(`tab-${t}`).classList.toggle('border-blue-600', t === tab);
        document.getElementById(`tab-${t}`).classList.toggle('text-gray-500', t !== tab);
        document.getElementById(`tab-${t}`).classList.toggle('border-transparent', t !== tab);
    });
}

function downloadCustomerPdf() {
    if (!window.currentViewingCustomerId) return;
    const tab = window.currentActiveTab || 'ledger';
    const start = window.currentCustomerStartDate;
    const end = window.currentCustomerEndDate;
    
    let url = `${BASE_URL}/customers/${window.currentViewingCustomerId}/export-pdf/${tab}`;
    const params = [];
    if (start) params.push(`start_date=${start}`);
    if (end) params.push(`end_date=${end}`);
    
    if (params.length > 0) {
        url += '?' + params.join('&');
    }
    
    window.open(url, '_blank');
}

function badge(status) {
    const s = (status||'').toLowerCase();
    if (s === 'paid') return '<span class="px-2 py-1 rounded-full text-xs bg-green-100 text-green-700">Paid</span>';
    if (s === 'partial') return '<span class="px-2 py-1 rounded-full text-xs bg-yellow-100 text-yellow-700">Partial</span>';
    return '<span class="px-2 py-1 rounded-full text-xs bg-gray-100 text-gray-700">Pending</span>';
}

const fmt = n => Number(n||0).toFixed(2);
const formatDate = d => {
    if (!d) return '';
    const dt = new Date(d);
    if (isNaN(dt.getTime())) return d;
    return dt.toLocaleDateString(undefined, { year: 'numeric', month: '2-digit', day: '2-digit' });
}

function closeViewModal() {
    document.getElementById('viewModal').classList.add('hidden');
}

function editCustomer(id) {
    fetch(`${BASE_URL}/customers/${id}/edit`, {
        headers: {
            'Accept': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const customer = data.customer;
            document.getElementById('edit_customer_id').value = customer.id;
            document.getElementById('edit_name').value = customer.name;
            document.getElementById('edit_phone').value = customer.phone || '';
            document.getElementById('edit_tin').value = customer.tin || '';
            document.getElementById('edit_email').value = customer.email || '';
            document.getElementById('edit_city').value = customer.city || '';
            document.getElementById('edit_country').value = customer.country || '';
            document.getElementById('edit_opening_balance').value = customer.opening_balance || 0;
            document.getElementById('edit_address').value = customer.address || '';
            document.getElementById('edit_is_active').checked = customer.is_active;
            document.getElementById('editModal').classList.remove('hidden');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to load customer data');
    });
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
    document.getElementById('editForm').reset();
}

function submitEdit(event) {
    event.preventDefault();
    const form = event.target;
    const customerId = document.getElementById('edit_customer_id').value;
    const formData = new FormData(form);
    
    const BASE_URL = '{{ url('/') }}';
    fetch(`${BASE_URL}/customers/${customerId}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeEditModal();
            window.location.reload();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to update customer');
    });
}

function deleteCustomer(id) {
    if (!confirm('Are you sure you want to delete this customer? This action cannot be undone.')) {
        return;
    }
    
    
    fetch(`${BASE_URL}/customers/${id}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.message || 'Failed to delete customer');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to delete customer');
    });
}

function openAdvanceModal(id, name) {
    document.getElementById('advanceForm').reset();
    toggleAdvanceChequeFields();
    document.getElementById('advance-payment-method').dispatchEvent(new Event('change'));
    document.getElementById('advanceCustomerName').textContent = name;
    document.getElementById('advanceForm').action = `${BASE_URL}/customers/${id}/advance`;
    document.getElementById('advanceModal').classList.remove('hidden');
}

function closeAdvanceModal() {
    document.getElementById('advanceModal').classList.add('hidden');
}

// Client-side instant live search & filter helpers
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('customerSearchInput');
    const clearBtn = document.getElementById('clearSearchBtn');
    const filterForm = document.getElementById('customerFilterForm');
    const rows = document.querySelectorAll('.customer-row');
    const noMatchRow = document.getElementById('clientNoMatchRow');
    const resultsCountEl = document.getElementById('resultsCount');
    const defaultCountText = resultsCountEl ? resultsCountEl.textContent : '';

    function applyLiveSearch() {
        const query = (searchInput.value || '').trim().toLowerCase();

        if (clearBtn) {
            if (query.length > 0) {
                clearBtn.classList.remove('hidden');
            } else {
                clearBtn.classList.add('hidden');
            }
        }

        let visibleCount = 0;
        rows.forEach(function(row) {
            const rowData = row.getAttribute('data-search') || '';
            if (!query || rowData.includes(query)) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        if (noMatchRow) {
            if (visibleCount === 0 && rows.length > 0) {
                noMatchRow.classList.remove('hidden');
            } else {
                noMatchRow.classList.add('hidden');
            }
        }

        if (resultsCountEl) {
            if (query.length > 0) {
                resultsCountEl.textContent = `Found ${visibleCount} matching on this page (press Enter to search all)`;
            } else {
                resultsCountEl.textContent = defaultCountText;
            }
        }
    }

    if (searchInput) {
        let debounceTimer;
        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(applyLiveSearch, 120);
        });

        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                if (filterForm) {
                    filterForm.submit();
                }
            }
        });
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            searchInput.value = '';
            applyLiveSearch();
            searchInput.focus();
        });
    }
});
</script>

<!-- Add Advance Modal -->
<div id="advanceModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative my-6 mx-auto p-5 border w-11/12 md:w-2/3 lg:w-1/2 max-w-2xl max-h-[calc(100dvh-3rem)] overflow-y-auto shadow-lg rounded-md bg-white">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900">Add Advance Payment</h3>
            <button onclick="closeAdvanceModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="advanceForm" method="POST" action="">
            @csrf
            <p class="text-sm text-gray-600 mb-4">Adding advance payment for <span id="advanceCustomerName" class="font-bold"></span></p>
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Amount <span class="text-red-500">*</span></label>
                    <input type="number" name="amount" step="0.01" min="0.01" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Payment Method</label>
                    <select id="advance-payment-method" name="payment_method" onchange="toggleAdvanceChequeFields()" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="cash">Cash</option>
                        <option value="card">Card</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="cheque">Cheque</option>
                        <option value="bank_deposit">Bank Deposit</option>
                        <option value="mobile_payment">Mobile Payment</option>
                    </select>
                </div>
                <div id="advance-cheque-fields" class="hidden grid grid-cols-1 sm:grid-cols-2 gap-3 rounded-lg bg-indigo-50 p-3">
                    <label class="text-sm font-semibold">Cheque Number *<input name="cheque_number" type="text" maxlength="100" class="mt-1 w-full px-3 py-2 border rounded-lg"></label>
                    <label class="text-sm font-semibold">Cheque Date *<input name="cheque_date" type="date" class="mt-1 w-full px-3 py-2 border rounded-lg"></label>
                    <label class="text-sm font-semibold">Cheque Bank *<input name="bank_name" type="text" maxlength="191" class="mt-1 w-full px-3 py-2 border rounded-lg"></label>
                    <label class="text-sm font-semibold">Account Holder<input name="account_name" type="text" maxlength="191" class="mt-1 w-full px-3 py-2 border rounded-lg"></label>
                    <p class="sm:col-span-2 text-xs text-indigo-700">This cheque stays pending in Cheque Details. The advance is available after it is passed.</p>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Payment Date</label>
                    <input type="date" name="payment_date" value="{{ date('Y-m-d') }}" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Notes</label>
                    <textarea name="notes" rows="2" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">Advance Payment</textarea>
                </div>
            </div>
            
            <div class="flex justify-end space-x-2 mt-6">
                <button type="button" onclick="closeAdvanceModal()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">Save Advance</button>
            </div>
        </form>
    </div>
</div>

@include('customers.payment-reminder-modal')
<script>
function toggleAdvanceChequeFields() {
    const show = document.getElementById('advance-payment-method').value === 'cheque';
    document.getElementById('advance-cheque-fields').classList.toggle('hidden', !show);
    document.querySelectorAll('#advance-cheque-fields input').forEach(input => {
        input.disabled = !show;
        input.required = show && ['cheque_number', 'cheque_date', 'bank_name'].includes(input.name);
    });
}
toggleAdvanceChequeFields();
</script>




@endsection
