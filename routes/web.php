<?php

use App\Http\Controllers\Accounting\AccountingController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ChequePaymentController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\InformationController;
use App\Http\Controllers\InventoryStoreController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\POSController;
use App\Http\Controllers\PrivacyModeController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductPriceController;
use App\Http\Controllers\ProductWriteOffController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\PurchaseReturnController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SaleReturnController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SuperAdminDashboardController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use App\Support\PublicStorageSync;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

// Guest Routes
Route::middleware('guest')->group(function () {
    Route::get('/', function () {
        return redirect()->route('login');
    });
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

// Secret POS Information (Public but protected via 4-digit code)
Route::prefix('information')->group(function () {
    // Visiting card page with logo click to open code login
    Route::get('/', [InformationController::class, 'card'])->name('information.card');
    // Verify 4-digit code and set session
    Route::post('/login', [InformationController::class, 'login'])->name('information.login');
    // Secret page (requires session auth)
    Route::get('/secret', [InformationController::class, 'secret'])->name('information.secret');
    // Save secret configs
    Route::post('/secret/save', [InformationController::class, 'save'])->name('information.secret.save');
    // AJAX: bills list for a hidden range (requires secret session)
    Route::get('/range-bills', [InformationController::class, 'rangeBills'])->name('information.range-bills');
});

// Public Routes (No Authentication Required)
Route::get('/customer/{customer}/bill/{sale}', [CustomerController::class, 'viewBill'])->name('customer.bill.view');
Route::get('/customer/{customer}/history', [CustomerController::class, 'viewHistory'])->name('customer.history.view')->middleware('privacy_mode');
Route::get('/customer/{customer}/pay/{sale}', [CustomerController::class, 'paymentPage'])->name('customer.payment.page');

// Authenticated Routes
Route::middleware(['auth', 'privacy_mode'])->group(function () {

    // Logout
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('permission:dashboard.view')
        ->name('dashboard');

    // Privacy Mode Toggle
    Route::post('/privacy-mode/toggle', [PrivacyModeController::class, 'toggle'])
        ->name('privacy-mode.toggle');

    // Super Admin Secret Dashboard (fully separate)
    Route::get('/fun/dashboard', [SuperAdminDashboardController::class, 'index'])
        ->middleware('superadmin')
        ->name('fun.dashboard');
    Route::get('/fun/dashboard/{section}', [SuperAdminDashboardController::class, 'index'])
        ->whereIn('section', ['overview', 'dashboard', 'inventory', 'records', 'privacy'])
        ->middleware('superadmin')
        ->name('fun.dashboard.section');
    Route::post('/fun/dashboard/save', [SuperAdminDashboardController::class, 'save'])
        ->middleware('superadmin')
        ->name('fun.dashboard.save');
    Route::get('/fun/search/products', [SuperAdminDashboardController::class, 'searchProducts'])
        ->middleware('superadmin')
        ->name('fun.search.products');
    Route::get('/fun/search/suppliers', [SuperAdminDashboardController::class, 'searchSuppliers'])
        ->middleware('superadmin')
        ->name('fun.search.suppliers');
    Route::get('/fun/search/customers', [SuperAdminDashboardController::class, 'searchCustomers'])
        ->middleware('superadmin')
        ->name('fun.search.customers');
    Route::get('/fun/search/sales', [SuperAdminDashboardController::class, 'searchSales'])
        ->middleware('superadmin')
        ->name('fun.search.sales');

    // Products
    Route::get('products/import', [ProductController::class, 'importForm'])
        ->middleware('permission:products.create')
        ->name('products.import');
    Route::get('products/import/template', [ProductController::class, 'downloadTemplate'])
        ->middleware('permission:products.create')
        ->name('products.import.template');
    Route::post('products/import', [ProductController::class, 'import'])
        ->middleware('permission:products.create')
        ->name('products.import.store');
    Route::get('products/barcode-search', [ProductController::class, 'barcodeSearch'])
        ->middleware('permission:barcode.print')
        ->name('products.barcode.search');
    Route::get('products/barcode-print', [ProductController::class, 'barcodePrint'])
        ->middleware('permission:barcode.print')
        ->name('products.barcode.print');
    Route::post('products/barcode-print/preview', [ProductController::class, 'barcodePrintPreview'])
        ->middleware('permission:barcode.print')
        ->name('products.barcode.preview');
    Route::resource('products', ProductController::class)
        ->middlewareFor(['index', 'show'], 'permission:products.view')
        ->middlewareFor(['create', 'store'], 'permission:products.create')
        ->middlewareFor(['edit', 'update'], 'permission:products.edit')
        ->middlewareFor(['destroy'], 'permission:products.delete');
    Route::post('products/{product}/update-price', [ProductController::class, 'updatePrice'])
        ->middleware('permission:products.update-price')
        ->name('products.update-price');
    Route::get('products/{product}/prices', [ProductPriceController::class, 'index'])
        ->middleware('permission:view_product_prices')
        ->name('product-prices.index');
    Route::post('product-prices', [ProductPriceController::class, 'store'])
        ->middleware('permission:create_product_prices')
        ->name('product-prices.store');
    Route::put('product-prices/{productPrice}', [ProductPriceController::class, 'update'])
        ->middleware('permission:edit_product_prices')
        ->name('product-prices.update');
    Route::delete('product-prices/{productPrice}', [ProductPriceController::class, 'destroy'])
        ->middleware('permission:delete_product_prices')
        ->name('product-prices.destroy');

    // Product Write-off
    Route::get('products/write-off/index', [\App\Http\Controllers\ProductWriteOffController::class, 'index'])
        ->middleware('permission:products.edit')
        ->name('products.write-off.index');
    Route::post('products/write-off/store', [\App\Http\Controllers\ProductWriteOffController::class, 'store'])
        ->middleware('permission:products.edit')
        ->name('products.write-off.store');

    // Categories
    Route::get('categories/{category}/children', [CategoryController::class, 'children'])
        ->name('categories.children');
    Route::resource('categories', CategoryController::class)
        ->middlewareFor(['index', 'show'], 'permission:categories.view')
        ->middlewareFor(['create', 'store'], 'permission:categories.create')
        ->middlewareFor(['edit', 'update'], 'permission:categories.edit')
        ->middlewareFor(['destroy'], 'permission:categories.delete');

    // Brands
    Route::resource('brands', BrandController::class)
        ->middlewareFor(['index', 'show'], 'permission:brands.view')
        ->middlewareFor(['create', 'store'], 'permission:brands.create')
        ->middlewareFor(['edit', 'update'], 'permission:brands.edit')
        ->middlewareFor(['destroy'], 'permission:brands.delete');

    // Units
    Route::resource('units', UnitController::class)
        ->middlewareFor(['index', 'show'], 'permission:units.view')
        ->middlewareFor(['create', 'store'], 'permission:units.create')
        ->middlewareFor(['edit', 'update'], 'permission:units.edit')
        ->middlewareFor(['destroy'], 'permission:units.delete');

    // Suppliers
    Route::resource('suppliers', SupplierController::class)
        ->middlewareFor(['index', 'show'], 'permission:suppliers.view')
        ->middlewareFor(['create', 'store'], 'permission:suppliers.create')
        ->middlewareFor(['edit', 'update'], 'permission:suppliers.edit')
        ->middlewareFor(['destroy'], 'permission:suppliers.delete');

    // Customers
    Route::resource('customers', CustomerController::class)
        ->middlewareFor(['index', 'show'], 'permission:customers.view')
        ->middlewareFor(['create', 'store'], 'permission:customers.create')
        ->middlewareFor(['edit', 'update'], 'permission:customers.edit')
        ->middlewareFor(['destroy'], 'permission:customers.delete');
    Route::post('customers/{customer}/send-reminder', [CustomerController::class, 'sendPaymentReminder'])
        ->middleware('permission:customers.edit')
        ->name('customers.send-reminder');

    // Purchases
    Route::resource('purchases', PurchaseController::class)
        ->middlewareFor(['index', 'show'], 'permission:purchases.view')
        ->middlewareFor(['create', 'store'], 'permission:purchases.create')
        ->middlewareFor(['edit', 'update'], 'permission:purchases.edit')
        ->middlewareFor(['destroy'], 'permission:purchases.delete');

    // Sales
    Route::resource('sales', SaleController::class)
        ->middlewareFor(['index', 'show'], 'permission:sales.view')
        ->middlewareFor(['create', 'store'], 'permission:sales.create')
        ->middlewareFor(['edit', 'update'], 'permission:sales.edit')
        ->middlewareFor(['destroy'], 'permission:sales.delete');
    Route::get('sales/{sale}/print', [SaleController::class, 'print'])
        ->middleware('permission:sales.view')
        ->name('sales.print');
    Route::post('sales/payment', [SaleController::class, 'addPayment'])
        ->middleware('permission:sales.edit')
        ->name('sales.payment.store');
    Route::get('sales-export/csv', [SaleController::class, 'exportCsv'])
        ->middleware('permission:sales.view')
        ->name('sales.export.csv');
    Route::get('sales-export/pdf', [SaleController::class, 'exportPdf'])
        ->middleware('permission:sales.view')
        ->name('sales.export.pdf');
    Route::post('sales/{sale}/return', [SaleController::class, 'returnSale'])
        ->middleware('permission:sales.edit')
        ->name('sales.return');
    Route::get('quotations/create', [POSController::class, 'quotationCreate'])
        ->middleware('permission:quotations.create')
        ->name('quotations.create');
    Route::get('quotations', [SaleController::class, 'quotations'])
        ->middleware('permission:quotations.view')
        ->name('quotations.index');
    Route::get('quotations/{sale}/pdf', [SaleController::class, 'quotationPdf'])
        ->middleware('permission:quotations.view')
        ->name('quotations.pdf');
    Route::post('quotations/{sale}/convert', [SaleController::class, 'convertQuotation'])
        ->middleware('permission:quotations.edit')
        ->name('quotations.convert');

    // POS
    Route::get('pos', [POSController::class, 'index'])
        ->middleware('permission:pos.access')
        ->name('pos.index');
    Route::get('pos/recent-sales', [POSController::class, 'recentSales'])
        ->middleware('permission:pos.access')
        ->name('pos.recent-sales');
    // POS AJAX Cart Endpoints
    Route::post('pos/cart/add', [POSController::class, 'addToCart'])
        ->middleware('permission:pos.access')
        ->name('pos.cart.add');
    Route::post('pos/cart/add-return', [POSController::class, 'addReturnItem'])
        ->middleware('permission:pos.access')
        ->name('pos.cart.add-return');
    Route::post('pos/search-sale', [POSController::class, 'searchSale'])
        ->middleware('permission:pos.access')
        ->name('pos.search-sale');
    Route::get('pos/search-products', [POSController::class, 'searchProducts'])
        ->middleware('permission:pos.access')
        ->name('pos.search-products');
    Route::get('pos/products/{product}/prices', [POSController::class, 'productPrices'])
        ->middleware('permission:pos.access')
        ->name('pos.product-prices');
    Route::post('pos/cart/update', [POSController::class, 'updateQty'])
        ->middleware('permission:pos.access')
        ->name('pos.cart.update');
    Route::post('pos/cart/item/update', [POSController::class, 'updateCartItem'])
        ->middleware('permission:pos.access')
        ->name('pos.cart.item.update');
    Route::post('pos/cart/remove', [POSController::class, 'removeItem'])
        ->middleware('permission:pos.access')
        ->name('pos.cart.remove');
    Route::post('pos/cart/clear', [POSController::class, 'clearCart'])
        ->middleware('permission:pos.access')
        ->name('pos.cart.clear');
    Route::post('pos/cart/discount', [POSController::class, 'setDiscount'])
        ->middleware('permission:pos.access')
        ->name('pos.cart.discount');
    Route::post('pos/cart/unit', [POSController::class, 'setItemUnit'])
        ->middleware('permission:pos.access')
        ->name('pos.cart.unit');
    Route::post('pos/cart/hold', [POSController::class, 'holdCart'])
        ->middleware('permission:pos.access')
        ->name('pos.cart.hold');
    Route::get('pos/cart/holds', [POSController::class, 'listHolds'])
        ->middleware('permission:pos.access')
        ->name('pos.cart.holds');
    Route::post('pos/cart/holds/load', [POSController::class, 'loadHold'])
        ->middleware('permission:pos.access')
        ->name('pos.cart.holds.load');
    Route::post('pos/cart/holds/remove', [POSController::class, 'removeHold'])
        ->middleware('permission:pos.access')
        ->name('pos.cart.holds.remove');
    // Save draft quotation
    Route::post('pos/draft', [POSController::class, 'saveDraft'])
        ->middleware('permission:pos.access')
        ->name('pos.draft');
    // Complete checkout
    Route::post('pos/checkout', [POSController::class, 'checkout'])
        ->middleware('permission:pos.access')
        ->name('pos.checkout');

    Route::get('cheque-payments', [ChequePaymentController::class, 'index'])
        ->middleware('permission:cheque_payments.view')
        ->name('cheque-payments.index');
    Route::post('cheque-payments/{chequePayment}/pass', [ChequePaymentController::class, 'pass'])
        ->middleware('permission:cheque_payments.manage')
        ->name('cheque-payments.pass');
    Route::post('cheque-payments/{chequePayment}/return', [ChequePaymentController::class, 'return'])
        ->middleware('permission:cheque_payments.manage')
        ->name('cheque-payments.return');

    // API endpoints for receipt and customer due
    Route::get('api/shop-details', [POSController::class, 'getShopDetails'])
        ->middleware('permission:pos.access');
    Route::get('api/sale-receipt/{id}', [POSController::class, 'getSaleReceipt'])
        ->middleware('permission:pos.access');
    Route::get('api/customer-due/{id}', [POSController::class, 'getCustomerDue'])
        ->middleware('permission:pos.access');

    // Expenses
    Route::resource('expenses', ExpenseController::class)
        ->middlewareFor(['index', 'show'], 'permission:expenses.view')
        ->middlewareFor(['create', 'store'], 'permission:expenses.create')
        ->middlewareFor(['edit', 'update'], 'permission:expenses.edit')
        ->middlewareFor(['destroy'], 'permission:expenses.delete');

    // QuickBooks-style Accounting
    Route::prefix('accounting')->name('accounting.')->group(function () {
        Route::get('/', [AccountingController::class, 'index'])->middleware('permission:accounting.view')->name('index');
        Route::get('accounts', [AccountingController::class, 'accounts'])->middleware('permission:accounting.accounts')->name('accounts');
        Route::get('transactions', [AccountingController::class, 'transactions'])->middleware('permission:accounting.transactions')->name('transactions');
        Route::get('banks', [AccountingController::class, 'banks'])->middleware('permission:accounting.banks')->name('banks');
        Route::get('petty-cash', [AccountingController::class, 'pettyCash'])->middleware('permission:accounting.petty-cash')->name('petty-cash');
        Route::get('ledger', [AccountingController::class, 'ledger'])->middleware('permission:accounting.ledger')->name('ledger');
        Route::get('t-accounts', [AccountingController::class, 'tAccounts'])
            ->middleware('permission:accounting.t-accounts')
            ->name('t-accounts');
        Route::get('trial-balance', [AccountingController::class, 'trialBalance'])
            ->middleware('permission:accounting.trial-balance')
            ->name('trial-balance');
        Route::get('balance-sheet', [AccountingController::class, 'balanceSheet'])->middleware('permission:accounting.balance-sheet')->name('balance-sheet');
        Route::get('cash-book', [AccountingController::class, 'cashBook'])->middleware('permission:accounting.cash-book')->name('cash-book');
        Route::get('bank-book', [AccountingController::class, 'bankBook'])->middleware('permission:accounting.bank-book')->name('bank-book');
        Route::get('owner-equity', [AccountingController::class, 'ownerEquity'])
            ->middleware('permission:accounting.owner-equity.view')
            ->name('owner-equity');
        Route::get('export/{section}/{format}', [AccountingController::class, 'export'])
            ->whereIn('section', ['accounts', 'transactions', 'banks', 'petty-cash', 'ledger'])
            ->whereIn('format', ['pdf', 'excel'])
            ->middleware('permission:accounting.view')
            ->name('export');
        Route::post('accounts', [AccountingController::class, 'storeAccount'])->middleware('permission:accounting.manage')->name('accounts.store');
        Route::post('transactions', [AccountingController::class, 'storeTransaction'])->middleware('permission:accounting.manage')->name('transactions.store');
        Route::post('banks', [AccountingController::class, 'storeBank'])->middleware('permission:accounting.manage')->name('banks.store');
        Route::get('banks/{bankAccount}/system-balance', [AccountingController::class, 'bankSystemBalance'])->name('banks.system-balance');
        Route::post('banks/{bankAccount}/reconcile', [AccountingController::class, 'reconcile'])->middleware('permission:accounting.manage')->name('banks.reconcile');
        Route::post('petty-cash', [AccountingController::class, 'storePettyFund'])->middleware('permission:accounting.manage')->name('petty-cash.store');
        Route::post('petty-cash/expenses', [AccountingController::class, 'storePettyExpense'])->middleware('permission:accounting.manage')->name('petty-cash.expenses.store');
        Route::post('owner-equity', [AccountingController::class, 'storeOwnerEquity'])
            ->middleware('permission:accounting.owner-equity.create')
            ->name('owner-equity.store');
        Route::put('owner-equity/{ownerEquity}', [AccountingController::class, 'updateOwnerEquity'])
            ->middleware('permission:accounting.owner-equity.edit')
            ->name('owner-equity.update');
        Route::delete('owner-equity/{ownerEquity}', [AccountingController::class, 'destroyOwnerEquity'])
            ->middleware('permission:accounting.owner-equity.delete')
            ->name('owner-equity.destroy');
    });

    // Store-based stock and shipment GRN
    Route::prefix('stores')->name('stores.')->group(function () {
        Route::get('/', [InventoryStoreController::class, 'index'])->middleware('permission:stores.view')->name('index');
        Route::get('stores', [InventoryStoreController::class, 'stores'])->middleware('permission:stores.stores')->name('stores');
        Route::get('shipments', [InventoryStoreController::class, 'shipments'])->middleware('permission:stores.shipments')->name('shipments');
        Route::get('allocations', [InventoryStoreController::class, 'allocations'])->middleware('permission:stores.allocations')->name('allocations');
        Route::get('transfers', [InventoryStoreController::class, 'transfers'])->middleware('permission:stores.transfers')->name('transfers');
        Route::get('report', [InventoryStoreController::class, 'report'])->middleware('permission:stores.report')->name('report');
        Route::get('transfer-report', [InventoryStoreController::class, 'transferHistory'])->middleware('permission:stores.transfer-report')->name('transfer-report');
        Route::post('stores', [InventoryStoreController::class, 'store'])->middleware('permission:stores.manage')->name('store');
        Route::post('stores/{store}/set-default', [InventoryStoreController::class, 'setAsDefault'])->middleware('permission:stores.manage')->name('set-default');
        Route::post('shipments', [InventoryStoreController::class, 'shipment'])->middleware('permission:stores.manage')->name('shipments.store');
        Route::post('shipments/allocate', [InventoryStoreController::class, 'allocate'])->middleware('permission:stores.manage')->name('shipments.allocate');
        Route::post('transfers', [InventoryStoreController::class, 'transfer'])->middleware('permission:stores.manage')->name('transfers.store');
        Route::put('transfers/{transfer}', [InventoryStoreController::class, 'updateTransfer'])->middleware('permission:stores.manage')->name('transfers.update');
        Route::delete('transfers/{transfer}', [InventoryStoreController::class, 'destroyTransfer'])->middleware('permission:stores.manage')->name('transfers.destroy');
    });
    // Expense Categories (for managing expense category list + AJAX create)
    Route::resource('expense-categories', ExpenseCategoryController::class)
        ->middlewareFor(['index'], 'permission:expenses.view')
        ->middlewareFor(['create', 'store'], 'permission:expenses.create')
        ->middlewareFor(['edit', 'update'], 'permission:expenses.edit')
        ->middlewareFor(['destroy'], 'permission:expenses.delete')
        ->except(['show']);

    // Users
    Route::resource('users', UserController::class)
        ->middlewareFor(['index', 'show'], 'permission:users.view')
        ->middlewareFor(['create', 'store'], 'permission:users.create')
        ->middlewareFor(['edit', 'update'], 'permission:users.edit')
        ->middlewareFor(['destroy'], 'permission:users.delete');

    // Roles
    Route::resource('roles', RoleController::class)
        ->middlewareFor(['index', 'show'], 'permission:roles.view')
        ->middlewareFor(['create', 'store'], 'permission:roles.create')
        ->middlewareFor(['edit', 'update'], 'permission:roles.edit')
        ->middlewareFor(['destroy'], 'permission:roles.delete');

    // Reports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('sales', [ReportController::class, 'sales'])->middleware('permission:reports.sales')->name('sales');
        Route::get('sales/pdf', [ReportController::class, 'salesPdf'])->middleware('permission:reports.sales')->name('sales.pdf');
        Route::get('sales/csv', [ReportController::class, 'salesCsv'])->middleware('permission:reports.sales')->name('sales.csv');
        Route::get('purchase', [ReportController::class, 'purchase'])->middleware('permission:reports.purchase')->name('purchase');
        Route::get('purchase/pdf', [ReportController::class, 'purchasePdf'])->middleware('permission:reports.purchase')->name('purchase.pdf');
        Route::get('purchase/csv', [ReportController::class, 'purchaseCsv'])->middleware('permission:reports.purchase')->name('purchase.csv');
        Route::get('profit-loss', [ReportController::class, 'profitLoss'])->middleware('permission:reports.profit-loss')->name('profit-loss');
        Route::get('profit-loss/pdf', [ReportController::class, 'profitLossPdf'])->middleware('permission:reports.profit-loss')->name('profit-loss.pdf');
        Route::get('profit-loss/csv', [ReportController::class, 'profitLossCsv'])->middleware('permission:reports.profit-loss')->name('profit-loss.csv');
        Route::get('stock', [ReportController::class, 'stock'])->middleware('permission:reports.stock')->name('stock');
        Route::get('stock/pdf', [ReportController::class, 'stockPdf'])->middleware('permission:reports.stock')->name('stock.pdf');
        Route::get('stock/csv', [ReportController::class, 'stockCsv'])->middleware('permission:reports.stock')->name('stock.csv');
        Route::get('expense', [ReportController::class, 'expense'])->middleware('permission:reports.expense')->name('expense');
        Route::get('expense/pdf', [ReportController::class, 'expensePdf'])->middleware('permission:reports.expense')->name('expense.pdf');
        Route::get('expense/csv', [ReportController::class, 'expenseCsv'])->middleware('permission:reports.expense')->name('expense.csv');
        Route::get('trending', [ReportController::class, 'trending'])->middleware('permission:reports.trending')->name('trending');
        // New Reports
        Route::get('vat', [ReportController::class, 'vat'])->middleware('permission:reports.vat')->name('vat');
        Route::get('receive', [ReportController::class, 'receive'])->middleware('permission:reports.receive')->name('receive');
        Route::get('debit', [ReportController::class, 'debit'])->middleware('permission:reports.debit')->name('debit');
        // PDF exports
        Route::get('vat/pdf', [ReportController::class, 'vatPdf'])->middleware('permission:reports.vat')->name('vat.pdf');
        Route::get('vat/csv', [ReportController::class, 'vatCsv'])->middleware('permission:reports.vat')->name('vat.csv');
        Route::get('vat/day-details', [ReportController::class, 'vatDayDetails'])->middleware('permission:reports.vat')->name('vat.day');
        Route::get('vat/day/pdf', [ReportController::class, 'vatDayPdf'])->middleware('permission:reports.vat')->name('vat.day.pdf');
        Route::get('receive/pdf', [ReportController::class, 'receivePdf'])->middleware('permission:reports.receive')->name('receive.pdf');
        Route::get('receive/csv', [ReportController::class, 'receiveCsv'])->middleware('permission:reports.receive')->name('receive.csv');
        Route::get('debit/pdf', [ReportController::class, 'debitPdf'])->middleware('permission:reports.debit')->name('debit.pdf');
        Route::get('debit/csv', [ReportController::class, 'debitCsv'])->middleware('permission:reports.debit')->name('debit.csv');
        // Rate Conversion
        Route::get('rates', [ReportController::class, 'rates'])->middleware('permission:reports.rate-conversion')->name('rates');
        Route::post('rates/manual', [ReportController::class, 'saveManualRate'])->middleware('permission:reports.rate-conversion')->name('rates.manual.save');
        // Due Bills (Sales with outstanding balance)
        Route::get('due-bills', [ReportController::class, 'dueBills'])->middleware('permission:reports.due-bills')->name('due-bills');
        Route::get('due-bills/pdf', [ReportController::class, 'dueBillsPdf'])->middleware('permission:reports.due-bills')->name('due-bills.pdf');
        Route::get('due-bills/csv', [ReportController::class, 'dueBillsCsv'])->middleware('permission:reports.due-bills')->name('due-bills.csv');
        // Customer Due
        Route::get('customer-due', [ReportController::class, 'customerDue'])->middleware('permission:reports.customer-due')->name('customer-due');
        Route::get('customer-due/pdf', [ReportController::class, 'customerDuePdf'])->middleware('permission:reports.customer-due')->name('customer-due.pdf');
        Route::get('customer-due/csv', [ReportController::class, 'customerDueCsv'])->middleware('permission:reports.customer-due')->name('customer-due.csv');
        // Unsold Products
        Route::get('never-sold', [ReportController::class, 'neverSold'])->middleware('permission:reports.never-sold')->name('never-sold');
        Route::get('unsold-recently', [ReportController::class, 'unsoldRecently'])->middleware('permission:reports.unsold-recently')->name('unsold-recently');
    });

    // Notifications
    Route::get('notifications', [NotificationController::class, 'index'])->middleware('permission:notifications.view')->name('notifications.index');
    Route::get('notifications/history', [NotificationController::class, 'history'])->middleware('permission:notifications.view')->name('notifications.history');
    Route::get('notifications/send', [NotificationController::class, 'sendForm'])->middleware('permission:notifications.configure')->name('notifications.send');
    Route::get('notifications/settings', [NotificationController::class, 'settings'])->middleware('permission:notifications.configure')->name('notifications.settings');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead'])->middleware('permission:notifications.view')->name('notifications.read');
    Route::post('notifications/promotion/send', [NotificationController::class, 'sendPromotion'])->middleware('permission:notifications.configure')->name('notifications.promotion.send');
    Route::post('notifications/settings/save', [NotificationController::class, 'saveSettings'])->middleware('permission:notifications.configure')->name('notifications.settings.save');
    Route::post('notifications/reminder/send-now', [NotificationController::class, 'sendMonthlyRemindersNow'])->middleware('permission:notifications.configure')->name('notifications.reminder.send_now');

    // Settings
    Route::get('settings', [SettingController::class, 'index'])->middleware('permission:settings.view')->name('settings.index');
    Route::get('settings/business', [SettingController::class, 'business'])->middleware('permission:settings.view')->name('settings.business');
    Route::get('settings/general', [SettingController::class, 'general'])->middleware('permission:settings.view')->name('settings.general');
    Route::get('settings/invoice', [SettingController::class, 'invoice'])->middleware('permission:settings.view')->name('settings.invoice');
    Route::get('settings/quotation', [SettingController::class, 'quotation'])->middleware('permission:settings.view')->name('settings.quotation');
    Route::get('settings/pos', [SettingController::class, 'pos'])->middleware('permission:settings.view')->name('settings.pos');
    Route::get('settings/barcode', [SettingController::class, 'barcode'])->middleware('permission:settings.view')->name('settings.barcode');
    Route::post('settings/save', [SettingController::class, 'save'])->middleware('permission:settings.edit')->name('settings.save');
    Route::get('settings/storage-link', function (Request $request) {
        if (! $request->user()?->hasPermission('settings.edit')) {
            abort(403);
        }

        $result = PublicStorageSync::linkAndSyncAll();
        $message = 'Storage setup completed. Synced '.$result['files_synced'].' file(s).';

        if (! $result['link_created']) {
            $message .= ' Symlink could not be created on this hosting, but manual copy is active.';
        }

        return redirect()->route('settings.index')->with('success', $message);
    })->name('settings.storage-link');

    // Activity Log
    Route::get('activity-log', [ActivityLogController::class, 'index'])
        ->middleware('permission:activity-log.view')
        ->name('activity-log.index');

    // Payments
    Route::get('payments/create', [\App\Http\Controllers\PaymentController::class, 'create'])
        ->middleware('permission:purchases.edit')
        ->name('payments.create');
    Route::post('payments', [\App\Http\Controllers\PaymentController::class, 'store'])
        ->middleware('permission:purchases.edit')
        ->name('payments.store');

    // Product Write-offs
    Route::resource('product-write-offs', ProductWriteOffController::class)
        ->middlewareFor(['index', 'show'], 'permission:products.edit')
        ->middlewareFor(['create', 'store'], 'permission:products.edit')
        ->middlewareFor(['edit', 'update'], 'permission:products.edit')
        ->middlewareFor(['destroy'], 'permission:products.edit');

    // Sale Returns
    Route::resource('sale-returns', SaleReturnController::class)
        ->middlewareFor(['index', 'show'], 'permission:sales.edit')
        ->middlewareFor(['create', 'store'], 'permission:sales.edit')
        ->middlewareFor(['edit', 'update'], 'permission:sales.edit')
        ->middlewareFor(['destroy'], 'permission:sales.edit');
    Route::get('sale-returns/{id}/print', [SaleReturnController::class, 'print'])
        ->middleware('permission:sales.edit')
        ->name('sale-returns.print');

    // Purchase Returns
    Route::resource('purchase-returns', PurchaseReturnController::class)
        ->middlewareFor(['index', 'show'], 'permission:purchases.edit')
        ->middlewareFor(['create', 'store'], 'permission:purchases.edit')
        ->middlewareFor(['edit', 'update'], 'permission:purchases.edit')
        ->middlewareFor(['destroy'], 'permission:purchases.edit');
});

Route::get('/run-migrate', function () {
    $connectionName = config('database.default');
    $databaseName = config("database.connections.{$connectionName}.database", 'unknown');
    $repairOutput = '';

    try {
        if (\Illuminate\Support\Facades\DB::getDriverName() === 'mysql') {
            $idColumns = \Illuminate\Support\Facades\DB::select(
                "SELECT TABLE_NAME, COLUMN_TYPE, EXTRA, COLUMN_KEY
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = ?
                   AND COLUMN_NAME = 'id'
                   AND DATA_TYPE IN ('int', 'bigint', 'mediumint', 'smallint', 'tinyint')",
                [$databaseName]
            );

            $repaired = [];
            foreach ($idColumns as $column) {
                $table = str_replace('`', '``', (string) $column->TABLE_NAME);
                $type = (string) $column->COLUMN_TYPE;

                $primaryKey = \Illuminate\Support\Facades\DB::selectOne(
                    "SELECT COLUMN_NAME
                     FROM information_schema.KEY_COLUMN_USAGE
                     WHERE TABLE_SCHEMA = ?
                       AND TABLE_NAME = ?
                       AND CONSTRAINT_NAME = 'PRIMARY'
                     LIMIT 1",
                    [$databaseName, $column->TABLE_NAME]
                );

                $tableRepairs = [];
                if ((string) $column->COLUMN_KEY !== 'PRI' && ! $primaryKey) {
                    \Illuminate\Support\Facades\DB::statement("ALTER TABLE `{$table}` ADD PRIMARY KEY (`id`)");
                    $tableRepairs[] = 'primary key';
                }

                if (! str_contains(strtolower((string) $column->EXTRA), 'auto_increment')) {
                    \Illuminate\Support\Facades\DB::statement("ALTER TABLE `{$table}` MODIFY `id` {$type} NOT NULL AUTO_INCREMENT");
                    $tableRepairs[] = 'auto increment';
                }

                if (! empty($tableRepairs)) {
                    $repaired[] = $column->TABLE_NAME.' ('.implode(', ', $tableRepairs).')';
                }
            }

            $repairOutput = empty($repaired)
                ? 'No primary id columns needed AUTO_INCREMENT repair.'
                : 'Repaired AUTO_INCREMENT on: '.implode(', ', $repaired);

            // 1. Convert any MyISAM tables to InnoDB
            $myisamTables = \Illuminate\Support\Facades\DB::select(
                "SELECT TABLE_NAME 
                 FROM information_schema.TABLES 
                 WHERE TABLE_SCHEMA = ? 
                   AND ENGINE = 'MyISAM'",
                [$databaseName]
            );

            if (! empty($myisamTables)) {
                $converted = [];
                foreach ($myisamTables as $table) {
                    $tableName = str_replace('`', '``', (string) $table->TABLE_NAME);
                    \Illuminate\Support\Facades\DB::statement("ALTER TABLE `{$tableName}` ENGINE=InnoDB");
                    $converted[] = $tableName;
                }
                $repairOutput .= "\nConverted MyISAM to InnoDB on: ".implode(', ', $converted);
            }

            // 2. Fix missing tables that are marked as migrated
            $migrations = \Illuminate\Support\Facades\DB::table('migrations')->pluck('migration');
            $tablesToCheck = [
                '2026_05_16_000001_create_product_prices_table' => 'product_prices',
                '2026_05_24_120000_create_privacy_mode_tables' => 'privacy_mode_settings',
            ];

            foreach ($tablesToCheck as $migration => $table) {
                if ($migrations->contains($migration) && ! \Illuminate\Support\Facades\Schema::hasTable($table)) {
                    \Illuminate\Support\Facades\DB::table('migrations')->where('migration', $migration)->delete();
                    $repairOutput .= "\nRemoved missing table migration: {$migration}";
                }
            }

            // 3. Fix partial migration failures (where columns exist but migration is not recorded)
            if (! $migrations->contains('2026_05_16_000002_add_product_price_id_to_stock_documents')) {
                $tablesToCleanup = [
                    'purchase_items' => ['product_price_id', 'selling_price'],
                    'sale_items' => ['product_price_id'],
                    'sale_return_items' => ['product_price_id'],
                ];

                foreach ($tablesToCleanup as $table => $columns) {
                    if (\Illuminate\Support\Facades\Schema::hasTable($table)) {
                        foreach ($columns as $column) {
                            if (\Illuminate\Support\Facades\Schema::hasColumn($table, $column)) {
                                try {
                                    // Try to drop foreign key first if it exists
                                    \Illuminate\Support\Facades\Schema::table($table, function ($t) use ($column) {
                                        $t->dropForeign([$column]);
                                    });
                                } catch (\Exception $e) {
                                    // Ignore foreign key drop errors
                                }
                                \Illuminate\Support\Facades\Schema::table($table, function ($t) use ($column) {
                                    $t->dropColumn($column);
                                });
                                $repairOutput .= "\nDropped partial column {$table}.{$column} for retry.";
                            }
                        }
                    }
                }
            }
        }

        $exitCode = Artisan::call('migrate', ['--force' => true]);
        $migrateOutput = trim(Artisan::output());

        $statusExitCode = Artisan::call('migrate:status', ['--no-ansi' => true]);
        $statusOutput = trim(Artisan::output());

        if ($exitCode !== 0) {
            throw new \RuntimeException($migrateOutput !== '' ? $migrateOutput : 'Migration command returned a non-zero exit code.');
        }

        return response()->make(
            '<html><body style="font-family: Arial, sans-serif; padding: 24px;">'
            .'<h1 style="color: #15803d;">Migration Successful</h1>'
            .'<p><strong>Connection:</strong> '.e($connectionName).'</p>'
            .'<p><strong>Database:</strong> '.e($databaseName).'</p>'
            .'<p><strong>Exit code:</strong> '.e((string) $exitCode).'</p>'
            .'<h2>Preflight Repair</h2>'
            .'<pre style="background: #f5f5f5; padding: 16px; white-space: pre-wrap;">'.e($repairOutput !== '' ? $repairOutput : 'No preflight repair was needed for this database driver.').'</pre>'
            .'<h2>Migration Output</h2>'
            .'<pre style="background: #f5f5f5; padding: 16px; white-space: pre-wrap;">'.e($migrateOutput !== '' ? $migrateOutput : 'No output returned.').'</pre>'
            .'<h2>Migration Status</h2>'
            .'<p><strong>Status command exit code:</strong> '.e((string) $statusExitCode).'</p>'
            .'<pre style="background: #f5f5f5; padding: 16px; white-space: pre-wrap;">'.e($statusOutput !== '' ? $statusOutput : 'No status output returned.').'</pre>'
            .'</body></html>',
            200,
            ['Content-Type' => 'text/html; charset=UTF-8']
        );
    } catch (\Throwable $e) {
        Log::error('Migration link failed.', [
            'message' => $e->getMessage(),
            'exception' => $e,
        ]);

        $logPath = storage_path('logs/laravel.log');
        $recentLog = 'Log file not found.';

        if (is_file($logPath)) {
            $logLines = preg_split('/\R/', trim((string) file_get_contents($logPath))) ?: [];
            $recentLog = implode(PHP_EOL, array_slice($logLines, -80));
        }

        return response()->make(
            '<html><body style="font-family: Arial, sans-serif; padding: 24px;">'
            .'<h1 style="color: #b91c1c;">Migration Failed</h1>'
            .'<p><strong>Connection:</strong> '.e($connectionName).'</p>'
            .'<p><strong>Database:</strong> '.e($databaseName).'</p>'
            .'<p><strong>Error:</strong> '.e($e->getMessage()).'</p>'
            .'<h2>Preflight Repair</h2>'
            .'<pre style="background: #f5f5f5; padding: 16px; white-space: pre-wrap;">'.e($repairOutput !== '' ? $repairOutput : 'Preflight repair did not complete.').'</pre>'
            .'<h2>Recent Error Log</h2>'
            .'<pre style="background: #f5f5f5; padding: 16px; white-space: pre-wrap;">'.e($recentLog).'</pre>'
            .'</body></html>',
            500,
            ['Content-Type' => 'text/html; charset=UTF-8']
        );
    }
});

Route::get('/run-seed', function () {
    $connectionName = config('database.default');
    $databaseName = config("database.connections.{$connectionName}.database", 'unknown');

    try {
        $exitCode = Artisan::call('db:seed', ['--force' => true]);
        $seedOutput = trim(Artisan::output());

        if ($exitCode !== 0) {
            throw new \RuntimeException($seedOutput !== '' ? $seedOutput : 'Seeder command returned a non-zero exit code.');
        }

        return response()->make(
            '<html><body style="font-family: Arial, sans-serif; padding: 24px;">'
            .'<h1 style="color: #15803d;">Seeding Successful</h1>'
            .'<p><strong>Connection:</strong> '.e($connectionName).'</p>'
            .'<p><strong>Database:</strong> '.e($databaseName).'</p>'
            .'<p><strong>Seeder:</strong> DatabaseSeeder</p>'
            .'<p><strong>Exit code:</strong> '.e((string) $exitCode).'</p>'
            .'<h2>Seeder Output</h2>'
            .'<pre style="background: #f5f5f5; padding: 16px; white-space: pre-wrap;">'.e($seedOutput !== '' ? $seedOutput : 'No output returned.').'</pre>'
            .'</body></html>',
            200,
            ['Content-Type' => 'text/html; charset=UTF-8']
        );
    } catch (\Throwable $e) {
        Log::error('Seeder link failed.', [
            'message' => $e->getMessage(),
            'exception' => $e,
        ]);

        $logPath = storage_path('logs/laravel.log');
        $recentLog = 'Log file not found.';

        if (is_file($logPath)) {
            $logLines = preg_split('/\R/', trim((string) file_get_contents($logPath))) ?: [];
            $recentLog = implode(PHP_EOL, array_slice($logLines, -80));
        }

        return response()->make(
            '<html><body style="font-family: Arial, sans-serif; padding: 24px;">'
            .'<h1 style="color: #b91c1c;">Seeding Failed</h1>'
            .'<p><strong>Connection:</strong> '.e($connectionName).'</p>'
            .'<p><strong>Database:</strong> '.e($databaseName).'</p>'
            .'<p><strong>Seeder:</strong> DatabaseSeeder</p>'
            .'<p><strong>Error:</strong> '.e($e->getMessage()).'</p>'
            .'<h2>Recent Error Log</h2>'
            .'<pre style="background: #f5f5f5; padding: 16px; white-space: pre-wrap;">'.e($recentLog).'</pre>'
            .'</body></html>',
            500,
            ['Content-Type' => 'text/html; charset=UTF-8']
        );
    }
});
