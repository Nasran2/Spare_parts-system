<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\Request;
use App\Support\PublicStorageSync;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\POSController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProductWriteOffController;
use App\Http\Controllers\SaleReturnController;
use App\Http\Controllers\PurchaseReturnController;
use App\Http\Controllers\InformationController;

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
Route::get('/customer/{customer}/history', [CustomerController::class, 'viewHistory'])->name('customer.history.view');
Route::get('/customer/{customer}/pay/{sale}', [CustomerController::class, 'paymentPage'])->name('customer.payment.page');

// Authenticated Routes
Route::middleware('auth')->group(function () {
    
    // Logout
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('permission:dashboard.view')
        ->name('dashboard');
    
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
    
    // Product Write-off
    Route::get('products/write-off/index', [\App\Http\Controllers\ProductWriteOffController::class, 'index'])
        ->middleware('permission:products.edit')
        ->name('products.write-off.index');
    Route::post('products/write-off/store', [\App\Http\Controllers\ProductWriteOffController::class, 'store'])
        ->middleware('permission:products.edit')
        ->name('products.write-off.store');
    
    // Categories
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
        Route::get('vat', [ReportController::class, 'vat'])->middleware('permission:reports.sales')->name('vat');
        Route::get('receive', [ReportController::class, 'receive'])->middleware('permission:reports.sales')->name('receive');
        Route::get('debit', [ReportController::class, 'debit'])->middleware('permission:reports.sales')->name('debit');
        // PDF exports
        Route::get('vat/pdf', [ReportController::class, 'vatPdf'])->middleware('permission:reports.sales')->name('vat.pdf');
        Route::get('vat/csv', [ReportController::class, 'vatCsv'])->middleware('permission:reports.sales')->name('vat.csv');
        Route::get('vat/day-details', [ReportController::class, 'vatDayDetails'])->middleware('permission:reports.sales')->name('vat.day');
        Route::get('vat/day/pdf', [ReportController::class, 'vatDayPdf'])->middleware('permission:reports.sales')->name('vat.day.pdf');
        Route::get('receive/pdf', [ReportController::class, 'receivePdf'])->middleware('permission:reports.sales')->name('receive.pdf');
        Route::get('receive/csv', [ReportController::class, 'receiveCsv'])->middleware('permission:reports.sales')->name('receive.csv');
        Route::get('debit/pdf', [ReportController::class, 'debitPdf'])->middleware('permission:reports.sales')->name('debit.pdf');
        Route::get('debit/csv', [ReportController::class, 'debitCsv'])->middleware('permission:reports.sales')->name('debit.csv');
        // Rate Conversion
        Route::get('rates', [ReportController::class, 'rates'])->middleware('permission:reports.sales')->name('rates');
        Route::post('rates/manual', [ReportController::class, 'saveManualRate'])->middleware('permission:reports.sales')->name('rates.manual.save');
        // Due Bills (Sales with outstanding balance)
        Route::get('due-bills', [ReportController::class, 'dueBills'])->middleware('permission:reports.sales')->name('due-bills');
        Route::get('due-bills/pdf', [ReportController::class, 'dueBillsPdf'])->middleware('permission:reports.sales')->name('due-bills.pdf');
        Route::get('due-bills/csv', [ReportController::class, 'dueBillsCsv'])->middleware('permission:reports.sales')->name('due-bills.csv');
        // Customer Due
        Route::get('customer-due', [ReportController::class, 'customerDue'])->middleware('permission:reports.sales')->name('customer-due');
        Route::get('customer-due/pdf', [ReportController::class, 'customerDuePdf'])->middleware('permission:reports.sales')->name('customer-due.pdf');
        Route::get('customer-due/csv', [ReportController::class, 'customerDueCsv'])->middleware('permission:reports.sales')->name('customer-due.csv');
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
        if (!$request->user()?->hasPermission('settings.edit')) {
            abort(403);
        }

        $result = PublicStorageSync::linkAndSyncAll();
        $message = 'Storage setup completed. Synced ' . $result['files_synced'] . ' file(s).';

        if (!$result['link_created']) {
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
    Artisan::call('migrate', ['--force' => true]);
    return "Migration completed!";
});