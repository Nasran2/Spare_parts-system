@extends('layouts.app')

@section('title', 'Create Role')
@section('page-title', 'Create New Role')

@section('content')
<div class="max-w-4xl mx-auto">
    <!-- Back Button -->
    <div class="mb-6">
        <a href="{{ route('roles.index') }}" class="inline-flex items-center text-blue-600 hover:text-blue-800 transition">
            <i class="fas fa-arrow-left mr-2"></i>Back to Roles
        </a>
    </div>

    <!-- Form Card -->
    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <!-- Card Header -->
        <div class="bg-gradient-to-r from-blue-600 to-blue-700 p-6 text-white">
            <h2 class="text-2xl font-bold flex items-center">
                <i class="fas fa-shield-alt mr-3"></i>Create New Role
            </h2>
            <p class="text-blue-100 mt-1">Define a new user role with specific permissions</p>
        </div>

        <!-- Card Body -->
        <form action="{{ route('roles.store') }}" method="POST" class="p-6 md:p-8 space-y-6">
            @csrf

            <!-- Role Information Section -->
            <div class="space-y-6">
                <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">
                    <i class="fas fa-info-circle text-blue-600 mr-2"></i>Role Information
                </h3>

                <!-- Role Name -->
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                        Role Name <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-tag text-gray-400"></i>
                        </div>
                        <input
                            type="text"
                            name="name"
                            id="name"
                            value="{{ old('name') }}"
                            class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('name') border-red-500 @enderror"
                            placeholder="e.g., Manager, Cashier, Supervisor"
                            required
                        >
                    </div>
                    @error('name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Description -->
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                        Description
                    </label>
                    <div class="relative">
                        <div class="absolute top-3 left-0 pl-3 pointer-events-none">
                            <i class="fas fa-file-alt text-gray-400"></i>
                        </div>
                        <textarea
                            name="description"
                            id="description"
                            rows="3"
                            class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('description') border-red-500 @enderror"
                            placeholder="Brief description of this role's purpose and responsibilities"
                        >{{ old('description') }}</textarea>
                    </div>
                    @error('description')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Active Status -->
                <div>
                    <label class="flex items-center cursor-pointer">
                        <input
                            type="checkbox"
                            name="is_active"
                            value="1"
                            class="w-5 h-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                            {{ old('is_active', true) ? 'checked' : '' }}
                        >
                        <span class="ml-3 text-sm font-medium text-gray-700">
                            <i class="fas fa-check-circle text-green-500 mr-1"></i>Active Role
                        </span>
                    </label>
                    <p class="ml-8 text-xs text-gray-500 mt-1">Users can be assigned to active roles only</p>
                </div>
            </div>

            <!-- Permissions Section -->
            <div class="space-y-6">
                <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">
                    <i class="fas fa-key text-blue-600 mr-2"></i>Permissions <span class="text-red-500">*</span>
                </h3>

                @php
                    $privacyModePermissions = auth()->user()?->isSuperAdmin()
                        ? ['privacy_mode.view', 'privacy_mode.toggle', 'privacy_mode.settings', 'privacy_mode.bypass']
                        : ['privacy_mode.view', 'privacy_mode.toggle'];
                    $permissionGroups = [
                        'Dashboard' => ['dashboard.view'],
                        'Products' => ['products.view', 'products.create', 'products.edit', 'products.delete', 'products.update-price', 'view_product_prices', 'create_product_prices', 'edit_product_prices', 'delete_product_prices', 'view_cost_price_in_pos'],
                        'Categories' => ['categories.view', 'categories.create', 'categories.edit', 'categories.delete'],
                        'Brands' => ['brands.view', 'brands.create', 'brands.edit', 'brands.delete'],
                        'Units' => ['units.view', 'units.create', 'units.edit', 'units.delete'],
                        'Suppliers' => ['suppliers.view', 'suppliers.create', 'suppliers.edit', 'suppliers.delete'],
                        'Customers' => ['customers.view', 'customers.create', 'customers.edit', 'customers.delete'],
                        'Purchases' => ['purchases.view', 'purchases.create', 'purchases.edit', 'purchases.delete'],
                        'Sales' => ['sales.view', 'sales.create', 'sales.edit', 'sales.delete'],
                        'Quotations' => ['quotations.view', 'quotations.create', 'quotations.edit', 'quotations.delete'],
                        'POS' => ['pos.access'],
                        'Accounting' => ['accounting.view', 'accounting.manage', 'accounting.accounts', 'accounting.transactions', 'accounting.cash-book', 'accounting.bank-book', 'accounting.banks', 'accounting.petty-cash', 'accounting.ledger', 'accounting.t-accounts', 'accounting.trial-balance', 'accounting.balance-sheet', 'accounting.owner-equity.view', 'accounting.owner-equity.create', 'accounting.owner-equity.edit', 'accounting.owner-equity.delete'],
                        'Store Stock' => ['stores.view', 'stores.manage', 'stores.stores', 'stores.allocations', 'stores.transfers', 'stores.transfer-report', 'stores.report'],
                        'Cheque Payments' => ['cheque_payments.view', 'cheque_payments.create', 'cheque_payments.manage', 'cheque_payments.settings'],
                        'Expenses' => ['expenses.view', 'expenses.create', 'expenses.edit', 'expenses.delete'],
                        'Users' => ['users.view', 'users.create', 'users.edit', 'users.delete'],
                        'Roles' => ['roles.view', 'roles.create', 'roles.edit', 'roles.delete'],
                        'Settings' => ['settings.view', 'settings.edit'],
                        'Reports' => ['reports.sales', 'reports.purchase', 'reports.profit-loss', 'reports.stock', 'reports.expense', 'reports.trending', 'reports.vat', 'reports.receive', 'reports.debit', 'reports.rate-conversion', 'reports.due-bills', 'reports.customer-due', 'reports.never-sold', 'reports.unsold-recently'],
                        'Barcodes' => ['barcode.print', 'barcode.settings'],
                        'Privacy Mode' => $privacyModePermissions,
                        'Activity Log' => ['activity-log.view'],
                        'Notifications' => ['notifications.view', 'notifications.configure'],
                    ];
                @endphp

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @foreach($permissionGroups as $group => $permissions)
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <h4 class="font-semibold text-gray-800 mb-3 flex items-center">
                            <i class="fas fa-folder text-blue-600 mr-2"></i>{{ $group }}
                        </h4>
                        <div class="space-y-2">
                            @foreach($permissions as $permission)
                            <label class="flex items-center cursor-pointer hover:bg-white p-2 rounded transition">
                                <input
                                    type="checkbox"
                                    name="permissions[]"
                                    value="{{ $permission }}"
                                    class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                    {{ in_array($permission, old('permissions', [])) ? 'checked' : '' }}
                                >
                                <span class="ml-3 text-sm text-gray-700">
                                    {{ ucfirst(str_replace(['.', '-', '_'], [' ', ' ', ' '], strpos($permission, '.') !== false ? substr($permission, strpos($permission, '.') + 1) : $permission)) }}
                                </span>
                            </label>
                            @endforeach
                        </div>
                    </div>
                    @endforeach
                </div>

                @error('permissions')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Helper Tools -->
            <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <p class="text-sm text-blue-800">
                        <i class="fas fa-info-circle mr-2"></i>Quick Actions:
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" onclick="selectAllPermissions()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm">
                            <i class="fas fa-check-double mr-1"></i>Select All
                        </button>
                        <button type="button" onclick="deselectAllPermissions()" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition text-sm">
                            <i class="fas fa-times mr-1"></i>Deselect All
                        </button>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex flex-col md:flex-row gap-3 pt-6 border-t">
                <button
                    type="submit"
                    class="flex-1 md:flex-none px-8 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg hover:from-blue-700 hover:to-blue-800 transition shadow-lg font-semibold"
                >
                    <i class="fas fa-save mr-2"></i>Create Role
                </button>
                <a
                    href="{{ route('roles.index') }}"
                    class="flex-1 md:flex-none px-8 py-3 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition text-center font-semibold"
                >
                    <i class="fas fa-times mr-2"></i>Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
function selectAllPermissions() {
    document.querySelectorAll('input[name="permissions[]"]').forEach(checkbox => {
        checkbox.checked = true;
    });
}

function deselectAllPermissions() {
    document.querySelectorAll('input[name="permissions[]"]').forEach(checkbox => {
        checkbox.checked = false;
    });
}
</script>
@endsection
