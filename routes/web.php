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

// Public Routes (No Authentication Required)
Route::get('/customer/{customer}/bill/{sale}', [CustomerController::class, 'viewBill'])->name('customer.bill.view');
Route::get('/customer/{customer}/history', [CustomerController::class, 'viewHistory'])->name('customer.history.view');
Route::get('/customer/{customer}/pay/{sale}', [CustomerController::class, 'paymentPage'])->name('customer.payment.page');

// Authenticated Routes
Route::middleware('auth')->group(function () {
    
    // Logout
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // Products
    Route::get('products/import', [ProductController::class, 'importForm'])->name('products.import');
    Route::get('products/import/template', [ProductController::class, 'downloadTemplate'])->name('products.import.template');
    Route::post('products/import', [ProductController::class, 'import'])->name('products.import.store');
        Route::get('products/barcode-search', [ProductController::class, 'barcodeSearch'])->name('products.barcode.search');
    Route::get('products/barcode-print', [ProductController::class, 'barcodePrint'])->name('products.barcode.print');
    Route::post('products/barcode-print/preview', [ProductController::class, 'barcodePrintPreview'])->name('products.barcode.preview');
    Route::resource('products', ProductController::class);
    Route::post('products/{product}/update-price', [ProductController::class, 'updatePrice'])->name('products.update-price');
    
    // Product Write-off
    Route::get('products/write-off/index', [\App\Http\Controllers\ProductWriteOffController::class, 'index'])->name('products.write-off.index');
    Route::post('products/write-off/store', [\App\Http\Controllers\ProductWriteOffController::class, 'store'])->name('products.write-off.store');
    
    // Categories
    Route::resource('categories', CategoryController::class);
    
    // Brands
    Route::resource('brands', BrandController::class);
    
    // Units
    Route::resource('units', UnitController::class);
    
    // Suppliers
    Route::resource('suppliers', SupplierController::class);
    
    // Customers
    Route::resource('customers', CustomerController::class);
    Route::post('customers/{customer}/send-reminder', [CustomerController::class, 'sendPaymentReminder'])->name('customers.send-reminder');
    Route::post('customers/{customer}/send-reminder', [CustomerController::class, 'sendPaymentReminder'])->name('customers.send-reminder');
    
    // Purchases
    Route::resource('purchases', PurchaseController::class);
    
    // Sales
    Route::resource('sales', SaleController::class);
    Route::get('sales/{sale}/print', [SaleController::class, 'print'])->name('sales.print');
    Route::post('sales/payment', [SaleController::class, 'addPayment'])->name('sales.payment.store');
    Route::get('sales-export/csv', [SaleController::class, 'exportCsv'])->name('sales.export.csv');
    Route::get('sales-export/pdf', [SaleController::class, 'exportPdf'])->name('sales.export.pdf');
    Route::post('sales/{sale}/return', [SaleController::class, 'returnSale'])->name('sales.return');
    Route::get('quotations', [SaleController::class, 'quotations'])->name('quotations.index');
    Route::get('quotations/{sale}/pdf', [SaleController::class, 'quotationPdf'])->name('quotations.pdf');
    Route::post('quotations/{sale}/convert', [SaleController::class, 'convertQuotation'])->name('quotations.convert');
    
    // POS
    Route::get('pos', [POSController::class, 'index'])->name('pos.index');
    // POS AJAX Cart Endpoints
    Route::post('pos/cart/add', [POSController::class, 'addToCart'])->name('pos.cart.add');
    Route::post('pos/cart/add-return', [POSController::class, 'addReturnItem'])->name('pos.cart.add-return');
    Route::post('pos/search-sale', [POSController::class, 'searchSale'])->name('pos.search-sale');
    Route::get('pos/search-products', [POSController::class, 'searchProducts'])->name('pos.search-products');
    Route::post('pos/cart/update', [POSController::class, 'updateQty'])->name('pos.cart.update');
    Route::post('pos/cart/item/update', [POSController::class, 'updateCartItem'])->name('pos.cart.item.update');
    Route::post('pos/cart/remove', [POSController::class, 'removeItem'])->name('pos.cart.remove');
    Route::post('pos/cart/clear', [POSController::class, 'clearCart'])->name('pos.cart.clear');
    Route::post('pos/cart/discount', [POSController::class, 'setDiscount'])->name('pos.cart.discount');
    Route::post('pos/cart/unit', [POSController::class, 'setItemUnit'])->name('pos.cart.unit');
    Route::post('pos/cart/hold', [POSController::class, 'holdCart'])->name('pos.cart.hold');
    Route::get('pos/cart/holds', [POSController::class, 'listHolds'])->name('pos.cart.holds');
    Route::post('pos/cart/holds/load', [POSController::class, 'loadHold'])->name('pos.cart.holds.load');
    Route::post('pos/cart/holds/remove', [POSController::class, 'removeHold'])->name('pos.cart.holds.remove');
    // Save draft quotation
    Route::post('pos/draft', [POSController::class, 'saveDraft'])->name('pos.draft');
    // Complete checkout
    Route::post('pos/checkout', [POSController::class, 'checkout'])->name('pos.checkout');
    
    // API endpoints for receipt and customer due
    Route::get('api/shop-details', [POSController::class, 'getShopDetails']);
    Route::get('api/sale-receipt/{id}', [POSController::class, 'getSaleReceipt']);
    Route::get('api/customer-due/{id}', [POSController::class, 'getCustomerDue']);
    
    // Expenses
    Route::resource('expenses', ExpenseController::class);
    // Expense Categories (for managing expense category list + AJAX create)
    Route::resource('expense-categories', ExpenseCategoryController::class)->except(['show']);
    
    // Users
    Route::resource('users', UserController::class);
    
    // Roles
    Route::resource('roles', RoleController::class);
    
    // Reports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('sales', [ReportController::class, 'sales'])->name('sales');
        Route::get('sales/pdf', [ReportController::class, 'salesPdf'])->name('sales.pdf');
        Route::get('sales/csv', [ReportController::class, 'salesCsv'])->name('sales.csv');
        Route::get('purchase', [ReportController::class, 'purchase'])->name('purchase');
        Route::get('purchase/pdf', [ReportController::class, 'purchasePdf'])->name('purchase.pdf');
        Route::get('purchase/csv', [ReportController::class, 'purchaseCsv'])->name('purchase.csv');
        Route::get('profit-loss', [ReportController::class, 'profitLoss'])->name('profit-loss');
        Route::get('profit-loss/pdf', [ReportController::class, 'profitLossPdf'])->name('profit-loss.pdf');
        Route::get('profit-loss/csv', [ReportController::class, 'profitLossCsv'])->name('profit-loss.csv');
        Route::get('stock', [ReportController::class, 'stock'])->name('stock');
        Route::get('stock/pdf', [ReportController::class, 'stockPdf'])->name('stock.pdf');
        Route::get('stock/csv', [ReportController::class, 'stockCsv'])->name('stock.csv');
        Route::get('expense', [ReportController::class, 'expense'])->name('expense');
        Route::get('expense/pdf', [ReportController::class, 'expensePdf'])->name('expense.pdf');
        Route::get('expense/csv', [ReportController::class, 'expenseCsv'])->name('expense.csv');
        Route::get('trending', [ReportController::class, 'trending'])->name('trending');
        // New Reports
        Route::get('vat', [ReportController::class, 'vat'])->name('vat');
        Route::get('receive', [ReportController::class, 'receive'])->name('receive');
        Route::get('debit', [ReportController::class, 'debit'])->name('debit');
        // PDF exports
        Route::get('vat/pdf', [ReportController::class, 'vatPdf'])->name('vat.pdf');
        Route::get('vat/csv', [ReportController::class, 'vatCsv'])->name('vat.csv');
        Route::get('vat/day-details', [ReportController::class, 'vatDayDetails'])->name('vat.day');
        Route::get('vat/day/pdf', [ReportController::class, 'vatDayPdf'])->name('vat.day.pdf');
        Route::get('receive/pdf', [ReportController::class, 'receivePdf'])->name('receive.pdf');
        Route::get('receive/csv', [ReportController::class, 'receiveCsv'])->name('receive.csv');
        Route::get('debit/pdf', [ReportController::class, 'debitPdf'])->name('debit.pdf');
        Route::get('debit/csv', [ReportController::class, 'debitCsv'])->name('debit.csv');
        // Rate Conversion
        Route::get('rates', [ReportController::class, 'rates'])->name('rates');
        Route::post('rates/manual', [ReportController::class, 'saveManualRate'])->name('rates.manual.save');
        // Due Bills (Sales with outstanding balance)
        Route::get('due-bills', [ReportController::class, 'dueBills'])->name('due-bills');
        Route::get('due-bills/pdf', [ReportController::class, 'dueBillsPdf'])->name('due-bills.pdf');
        Route::get('due-bills/csv', [ReportController::class, 'dueBillsCsv'])->name('due-bills.csv');
        // Customer Due
        Route::get('customer-due', [ReportController::class, 'customerDue'])->name('customer-due');
        Route::get('customer-due/pdf', [ReportController::class, 'customerDuePdf'])->name('customer-due.pdf');
        Route::get('customer-due/csv', [ReportController::class, 'customerDueCsv'])->name('customer-due.csv');
    });
    
    // Notifications
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/history', [NotificationController::class, 'history'])->name('notifications.history');
    Route::get('notifications/send', [NotificationController::class, 'sendForm'])->name('notifications.send');
    Route::get('notifications/settings', [NotificationController::class, 'settings'])->name('notifications.settings');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('notifications/promotion/send', [NotificationController::class, 'sendPromotion'])->name('notifications.promotion.send');
    Route::post('notifications/settings/save', [NotificationController::class, 'saveSettings'])->name('notifications.settings.save');
    Route::post('notifications/reminder/send-now', [NotificationController::class, 'sendMonthlyRemindersNow'])->name('notifications.reminder.send_now');
    
    // Settings
    Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
    Route::get('settings/business', [SettingController::class, 'business'])->name('settings.business');
    Route::get('settings/general', [SettingController::class, 'general'])->name('settings.general');
    Route::get('settings/invoice', [SettingController::class, 'invoice'])->name('settings.invoice');
    Route::get('settings/quotation', [SettingController::class, 'quotation'])->name('settings.quotation');
    Route::get('settings/pos', [SettingController::class, 'pos'])->name('settings.pos');
    Route::get('settings/barcode', [SettingController::class, 'barcode'])->name('settings.barcode');
    Route::post('settings/save', [SettingController::class, 'save'])->name('settings.save');
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
    Route::get('activity-log', [ActivityLogController::class, 'index'])->name('activity-log.index');

    // Payments
    Route::get('payments/create', [\App\Http\Controllers\PaymentController::class, 'create'])->name('payments.create');
    Route::post('payments', [\App\Http\Controllers\PaymentController::class, 'store'])->name('payments.store');

    // Product Write-offs
    Route::resource('product-write-offs', ProductWriteOffController::class);

    // Sale Returns
    Route::resource('sale-returns', SaleReturnController::class);
    Route::get('sale-returns/{id}/print', [SaleReturnController::class, 'print'])->name('sale-returns.print');

    // Purchase Returns
    Route::resource('purchase-returns', PurchaseReturnController::class);
});

Route::get('/run-migrate', function () {
    Artisan::call('migrate', ['--force' => true]);
    return "Migration completed!";
});