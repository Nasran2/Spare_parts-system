<?php

namespace Database\Seeders;

use App\Models\Accounting\AccountTransaction;
use App\Models\Accounting\BankAccount;
use App\Models\Accounting\BankReconciliation;
use App\Models\Accounting\ChartAccount;
use App\Models\Accounting\PettyCashExpense;
use App\Models\Accounting\PettyCashFund;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\SecretSetting;
use App\Models\Setting;
use App\Models\StockShipment;
use App\Models\StockShipmentAllocation;
use App\Models\StockShipmentItem;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\StoreStockTransfer;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        echo "🚗 Seeding Vehicle POS Database...\n\n";

        // Create Admin Role with ALL Permissions
        echo "Creating roles...\n";
        $adminPermissions = [
            // Dashboard
            'dashboard.view',

            // User Management
            'users.view', 'users.create', 'users.edit', 'users.delete',
            'roles.view', 'roles.create', 'roles.edit', 'roles.delete',

            // Product Management
            'products.view', 'products.create', 'products.edit', 'products.delete', 'products.update-price',
            'view_product_prices', 'create_product_prices', 'edit_product_prices', 'delete_product_prices', 'view_cost_price_in_pos',
            'categories.view', 'categories.create', 'categories.edit', 'categories.delete',
            'brands.view', 'brands.create', 'brands.edit', 'brands.delete',
            'units.view', 'units.create', 'units.edit', 'units.delete',
            'barcode.print', 'barcode.settings',

            // Supplier & Customer
            'suppliers.view', 'suppliers.create', 'suppliers.edit', 'suppliers.delete',
            'customers.view', 'customers.create', 'customers.edit', 'customers.delete',

            // Purchases
            'purchases.view', 'purchases.create', 'purchases.edit', 'purchases.delete',

            // Sales
            'sales.view', 'sales.create', 'sales.edit', 'sales.delete',
            'quotations.view', 'quotations.create', 'quotations.edit', 'quotations.delete',
            'pos.access',

            // Expenses
            'expenses.view', 'expenses.create', 'expenses.edit', 'expenses.delete',

            // Accounting and store stock
            'accounting.view', 'accounting.manage', 'accounting.t-accounts', 'accounting.trial-balance',
            'accounting.owner-equity.view', 'accounting.owner-equity.create', 'accounting.owner-equity.edit', 'accounting.owner-equity.delete',
            'stores.view', 'stores.manage',
            'cheque_payments.view', 'cheque_payments.create', 'cheque_payments.manage', 'cheque_payments.settings',

            // Reports
            'reports.sales', 'reports.purchase', 'reports.profit-loss',
            'reports.stock', 'reports.expense', 'reports.trending',

            // Settings & System
            'settings.view', 'settings.edit',
            'activity-log.view',
            'notifications.view', 'notifications.configure',
            'privacy_mode.view', 'privacy_mode.toggle',
        ];
        $superAdminPermissions = array_values(array_unique(array_merge($adminPermissions, [
            'privacy_mode.settings',
            'privacy_mode.bypass',
        ])));

        $adminRole = Role::updateOrCreate(
            ['name' => 'Admin'],
            [
                'description' => 'Administrator with full system access and all permissions',
                'permissions' => $adminPermissions,
                'is_active' => true,
            ]
        );

        $superAdminRole = Role::firstOrCreate(
            ['name' => 'Super Admin'],
            [
                'description' => 'Super administrator with secret dashboard access and full system control',
                'permissions' => $superAdminPermissions,
                'is_active' => true,
            ]
        );

        // Create Manager Role
        $managerRole = Role::updateOrCreate(
            ['name' => 'Manager'],
            [
                'description' => 'Manager with limited management access',
                'permissions' => [
                    'dashboard.view',
                    'products.view', 'products.create', 'products.edit', 'products.update-price',
                    'view_product_prices', 'create_product_prices', 'edit_product_prices', 'delete_product_prices', 'view_cost_price_in_pos',
                    'categories.view', 'categories.create', 'categories.edit',
                    'brands.view', 'brands.create', 'brands.edit',
                    'barcode.print', 'barcode.settings',
                    'suppliers.view', 'suppliers.create', 'suppliers.edit',
                    'customers.view', 'customers.create', 'customers.edit',
                    'purchases.view', 'purchases.create', 'purchases.edit',
                    'sales.view', 'sales.create', 'sales.edit',
                    'pos.access',
                    'privacy_mode.view', 'privacy_mode.toggle',
                    'expenses.view', 'expenses.create',
                    'accounting.view', 'accounting.manage', 'accounting.t-accounts', 'accounting.trial-balance',
                    'accounting.owner-equity.view', 'accounting.owner-equity.create', 'accounting.owner-equity.edit', 'accounting.owner-equity.delete',
                    'stores.view', 'stores.manage',
                    'cheque_payments.view', 'cheque_payments.create', 'cheque_payments.manage',
                    'reports.sales', 'reports.purchase', 'reports.stock',
                ],
                'is_active' => true,
            ]
        );

        // Create Cashier Role
        $cashierRole = Role::updateOrCreate(
            ['name' => 'Cashier'],
            [
                'description' => 'Cashier with POS and sales access only',
                'permissions' => [
                    'dashboard.view',
                    'pos.access',
                    'cheque_payments.create',
                    'sales.view', 'sales.create',
                    'privacy_mode.view', 'privacy_mode.toggle',
                    'customers.view', 'customers.create',
                    'products.view',
                ],
                'is_active' => true,
            ]
        );

        echo "✓ Roles created successfully\n\n";

        // Create Admin User with ALL permissions
        echo "Creating users...\n";
        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@vehiclepos.com'],
            [
                'name' => 'Super Admin',
                'username' => 'superadmin',
                'password' => Hash::make('superadmin123'),
                'phone' => '+1234567888',
                'role_id' => $superAdminRole->id,
                'is_active' => true,
            ]
        );
        if ($superAdmin->role_id !== $superAdminRole->id || empty($superAdmin->username)) {
            $superAdmin->role_id = $superAdminRole->id;
            $superAdmin->username = $superAdmin->username ?: 'superadmin';
            $superAdmin->save();
        }
        echo "✓ Super Admin user created: superadmin@vehiclepos.com / superadmin123 (username: superadmin)\n";

        $admin = User::updateOrCreate(
            ['email' => 'admin@vehiclepos.com'],
            [
                'name' => 'Admin',
                'username' => 'admin',
                'password' => Hash::make('admin123'),
                'phone' => '+1234567890',
                'role_id' => $adminRole->id,
                'is_active' => true,
            ]
        );
        echo "✓ Admin user created: admin@vehiclepos.com / admin123\n";

        // Create Manager User
        $manager = User::updateOrCreate(
            ['email' => 'manager@vehiclepos.com'],
            [
                'name' => 'Manager',
                'username' => 'manager',
                'password' => Hash::make('manager123'),
                'phone' => '+1234567891',
                'role_id' => $managerRole->id,
                'is_active' => true,
            ]
        );
        echo "✓ Manager user created: manager@vehiclepos.com / manager123\n";

        // Create Cashier User
        $cashier = User::updateOrCreate(
            ['email' => 'cashier@vehiclepos.com'],
            [
                'name' => 'Cashier',
                'username' => 'cashier',
                'password' => Hash::make('cashier123'),
                'phone' => '+1234567892',
                'role_id' => $cashierRole->id,
                'is_active' => true,
            ]
        );
        echo "✓ Cashier user created: cashier@vehiclepos.com / cashier123\n\n";

        // Create extra dummy users
        if (User::count() < 10) {
            echo "Creating dummy users...\n";
            for ($i = 1; $i <= 6; $i++) {
                User::firstOrCreate(
                    ['email' => "staff{$i}@vehiclepos.com"],
                    [
                        'name' => "Staff {$i}",
                        'username' => "staff{$i}",
                        'password' => Hash::make('staff123'),
                        'phone' => '+12345000'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                        'role_id' => $i % 2 === 0 ? $managerRole->id : $cashierRole->id,
                        'is_active' => true,
                    ]
                );
            }
            echo "✓ Dummy users created\n\n";
        }

        // Create a few sample Suppliers
        echo "Creating sample suppliers...\n";
        $suppliers = [
            [
                'name' => 'AutoParts Co.',
                'company_name' => 'AutoParts Co. Ltd',
                'email' => 'contact@autoparts.example',
                'phone' => '+1000000001',
                'address' => '12 Industrial Road',
                'city' => 'Detroit',
                'country' => 'USA',
                'opening_balance' => 0,
                'is_active' => true,
            ],
            [
                'name' => 'GearHub',
                'company_name' => 'GearHub Trading',
                'email' => 'sales@gearhub.example',
                'phone' => '+1000000002',
                'address' => '5 Mechanics Ave',
                'city' => 'Houston',
                'country' => 'USA',
                'opening_balance' => 250.00,
                'is_active' => true,
            ],
            [
                'name' => 'Brake Masters',
                'company_name' => 'Brake Masters Inc.',
                'email' => 'info@brakemasters.example',
                'phone' => '+1000000003',
                'address' => '44 Auto Park',
                'city' => 'Chicago',
                'country' => 'USA',
                'opening_balance' => 0,
                'is_active' => true,
            ],
            [
                'name' => 'Engine One Supply',
                'company_name' => 'Engine One Supply LLC',
                'email' => 'hello@engineone.example',
                'phone' => '+1000000004',
                'address' => '88 Torque Street',
                'city' => 'Phoenix',
                'country' => 'USA',
                'opening_balance' => 100,
                'is_active' => true,
            ],
            [
                'name' => 'DriveLine Partners',
                'company_name' => 'DriveLine Partners',
                'email' => 'trade@driveline.example',
                'phone' => '+1000000005',
                'address' => '31 Workshop Blvd',
                'city' => 'Dallas',
                'country' => 'USA',
                'opening_balance' => 0,
                'is_active' => true,
            ],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::updateOrCreate(['phone' => $supplier['phone']], $supplier);
        }
        echo "✓ Sample suppliers created\n\n";

        // Create Default Units
        echo "Creating default units...\n";
        if (Unit::count() === 0) {
            Unit::insert([
                [
                    'name' => 'Piece',
                    'short_name' => 'pc',
                    'base_unit_multiplier' => 1,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name' => 'Set of 2 Pieces',
                    'short_name' => 'set2',
                    'base_unit_multiplier' => 2,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name' => 'Set of 4 Pieces',
                    'short_name' => 'set4',
                    'base_unit_multiplier' => 4,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name' => 'Dozen (12 Pieces)',
                    'short_name' => 'dz',
                    'base_unit_multiplier' => 12,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }
        echo "✓ Units created: Piece, Set of 2, Set of 4, Dozen\n\n";

        // Create dummy categories and brands
        echo "Creating categories and brands...\n";
        if (Category::count() === 0) {
            Category::insert([
                ['name' => 'Engine Parts', 'description' => 'Engine and performance components', 'parent_id' => null, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Brake System', 'description' => 'Brake pads, discs, and accessories', 'parent_id' => null, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Suspension', 'description' => 'Shocks, coils, and suspension kits', 'parent_id' => null, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Filters', 'description' => 'Oil, air, fuel filters', 'parent_id' => null, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        $categoryMap = Category::query()->pluck('id', 'name');

        if (Brand::count() === 0) {
            $hasBrandCategory = Schema::hasColumn('brands', 'category_id');
            $hasBrandSubcategory = Schema::hasColumn('brands', 'subcategory_id');

            $brands = [
                ['name' => 'Bosch', 'description' => 'OEM-grade parts', 'logo' => null, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Brembo', 'description' => 'High-performance brakes', 'logo' => null, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'KYB', 'description' => 'Suspension specialists', 'logo' => null, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Mann Filter', 'description' => 'Premium filters', 'logo' => null, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ];

            if ($hasBrandCategory) {
                $brands[0]['category_id'] = $categoryMap['Engine Parts'] ?? null;
                $brands[1]['category_id'] = $categoryMap['Brake System'] ?? null;
                $brands[2]['category_id'] = $categoryMap['Suspension'] ?? null;
                $brands[3]['category_id'] = $categoryMap['Filters'] ?? null;
            }

            if ($hasBrandSubcategory) {
                $brands[0]['subcategory_id'] = null;
                $brands[1]['subcategory_id'] = null;
                $brands[2]['subcategory_id'] = null;
                $brands[3]['subcategory_id'] = null;
            }

            Brand::insert($brands);
        }
        echo "✓ Categories and brands ready\n\n";

        // Create dummy customers
        echo "Creating sample customers...\n";
        $customers = [
            ['name' => 'John Carter', 'email' => 'john.carter@example.com', 'phone' => '+1555000001', 'address' => '101 Lakeview Dr', 'city' => 'Austin', 'country' => 'USA', 'opening_balance' => 0, 'is_active' => true],
            ['name' => 'Mia Robertson', 'email' => 'mia.robertson@example.com', 'phone' => '+1555000002', 'address' => '22 Grand Ave', 'city' => 'Denver', 'country' => 'USA', 'opening_balance' => 50, 'is_active' => true],
            ['name' => 'Noah Miller', 'email' => 'noah.miller@example.com', 'phone' => '+1555000003', 'address' => '89 Elm Street', 'city' => 'Seattle', 'country' => 'USA', 'opening_balance' => 0, 'is_active' => true],
            ['name' => 'AutoFix Garage', 'email' => 'autofix@example.com', 'phone' => '+1555000004', 'address' => '77 Service Road', 'city' => 'Miami', 'country' => 'USA', 'opening_balance' => 120, 'is_active' => true],
            ['name' => 'Prime Motors', 'email' => 'prime.motors@example.com', 'phone' => '+1555000005', 'address' => '15 Industrial Loop', 'city' => 'Atlanta', 'country' => 'USA', 'opening_balance' => 0, 'is_active' => true],
        ];

        foreach ($customers as $customer) {
            Customer::updateOrCreate(['phone' => $customer['phone']], $customer);
        }
        echo "✓ Sample customers created\n\n";

        // Create dummy products
        echo "Creating sample products...\n";
        $unitId = Unit::query()->where('short_name', 'pc')->value('id') ?? Unit::query()->value('id');
        $brandMap = Brand::query()->pluck('id', 'name');

        $products = [
            ['name' => 'Oil Filter OF-101', 'sku' => 'PRD-0001', 'category_id' => $categoryMap['Filters'] ?? null, 'brand_id' => $brandMap['Mann Filter'] ?? null, 'unit_id' => $unitId, 'base_unit' => 'pc', 'unit_factors' => null, 'visible_units' => null, 'description' => 'Standard oil filter', 'cost_price' => 5.00, 'selling_price' => 8.50, 'stock_quantity' => 120, 'alert_quantity' => 10, 'image' => null, 'barcode' => '890000000001', 'is_active' => true],
            ['name' => 'Air Filter AF-220', 'sku' => 'PRD-0002', 'category_id' => $categoryMap['Filters'] ?? null, 'brand_id' => $brandMap['Mann Filter'] ?? null, 'unit_id' => $unitId, 'base_unit' => 'pc', 'unit_factors' => null, 'visible_units' => null, 'description' => 'High airflow filter', 'cost_price' => 7.00, 'selling_price' => 11.00, 'stock_quantity' => 90, 'alert_quantity' => 10, 'image' => null, 'barcode' => '890000000002', 'is_active' => true],
            ['name' => 'Brake Pad BP-330', 'sku' => 'PRD-0003', 'category_id' => $categoryMap['Brake System'] ?? null, 'brand_id' => $brandMap['Brembo'] ?? null, 'unit_id' => $unitId, 'base_unit' => 'pc', 'unit_factors' => null, 'visible_units' => null, 'description' => 'Front brake pad set', 'cost_price' => 22.00, 'selling_price' => 32.00, 'stock_quantity' => 75, 'alert_quantity' => 8, 'image' => null, 'barcode' => '890000000003', 'is_active' => true],
            ['name' => 'Brake Disc BD-410', 'sku' => 'PRD-0004', 'category_id' => $categoryMap['Brake System'] ?? null, 'brand_id' => $brandMap['Brembo'] ?? null, 'unit_id' => $unitId, 'base_unit' => 'pc', 'unit_factors' => null, 'visible_units' => null, 'description' => 'Ventilated brake disc', 'cost_price' => 38.00, 'selling_price' => 55.00, 'stock_quantity' => 50, 'alert_quantity' => 6, 'image' => null, 'barcode' => '890000000004', 'is_active' => true],
            ['name' => 'Shock Absorber SA-520', 'sku' => 'PRD-0005', 'category_id' => $categoryMap['Suspension'] ?? null, 'brand_id' => $brandMap['KYB'] ?? null, 'unit_id' => $unitId, 'base_unit' => 'pc', 'unit_factors' => null, 'visible_units' => null, 'description' => 'Rear shock absorber', 'cost_price' => 41.00, 'selling_price' => 60.00, 'stock_quantity' => 65, 'alert_quantity' => 6, 'image' => null, 'barcode' => '890000000005', 'is_active' => true],
            ['name' => 'Spark Plug SP-610', 'sku' => 'PRD-0006', 'category_id' => $categoryMap['Engine Parts'] ?? null, 'brand_id' => $brandMap['Bosch'] ?? null, 'unit_id' => $unitId, 'base_unit' => 'pc', 'unit_factors' => null, 'visible_units' => null, 'description' => 'Iridium spark plug', 'cost_price' => 4.20, 'selling_price' => 7.00, 'stock_quantity' => 220, 'alert_quantity' => 20, 'image' => null, 'barcode' => '890000000006', 'is_active' => true],
            ['name' => 'Ignition Coil IC-710', 'sku' => 'PRD-0007', 'category_id' => $categoryMap['Engine Parts'] ?? null, 'brand_id' => $brandMap['Bosch'] ?? null, 'unit_id' => $unitId, 'base_unit' => 'pc', 'unit_factors' => null, 'visible_units' => null, 'description' => 'Ignition coil assembly', 'cost_price' => 28.00, 'selling_price' => 40.00, 'stock_quantity' => 55, 'alert_quantity' => 5, 'image' => null, 'barcode' => '890000000007', 'is_active' => true],
            ['name' => 'Control Arm CA-810', 'sku' => 'PRD-0008', 'category_id' => $categoryMap['Suspension'] ?? null, 'brand_id' => $brandMap['KYB'] ?? null, 'unit_id' => $unitId, 'base_unit' => 'pc', 'unit_factors' => null, 'visible_units' => null, 'description' => 'Front lower control arm', 'cost_price' => 30.00, 'selling_price' => 45.00, 'stock_quantity' => 48, 'alert_quantity' => 5, 'image' => null, 'barcode' => '890000000008', 'is_active' => true],
            ['name' => 'Cabin Filter CF-920', 'sku' => 'PRD-0009', 'category_id' => $categoryMap['Filters'] ?? null, 'brand_id' => $brandMap['Mann Filter'] ?? null, 'unit_id' => $unitId, 'base_unit' => 'pc', 'unit_factors' => null, 'visible_units' => null, 'description' => 'Cabin air purifier filter', 'cost_price' => 6.00, 'selling_price' => 9.00, 'stock_quantity' => 95, 'alert_quantity' => 10, 'image' => null, 'barcode' => '890000000009', 'is_active' => true],
            ['name' => 'Fuel Filter FF-1001', 'sku' => 'PRD-0010', 'category_id' => $categoryMap['Filters'] ?? null, 'brand_id' => $brandMap['Bosch'] ?? null, 'unit_id' => $unitId, 'base_unit' => 'pc', 'unit_factors' => null, 'visible_units' => null, 'description' => 'Fuel line filter', 'cost_price' => 5.80, 'selling_price' => 9.50, 'stock_quantity' => 80, 'alert_quantity' => 10, 'image' => null, 'barcode' => '890000000010', 'is_active' => true],
        ];

        foreach ($products as $product) {
            Product::updateOrCreate(['sku' => $product['sku']], $product);
        }
        echo "✓ Sample products created\n\n";

        // Create dummy expense categories and expenses
        echo "Creating expense categories and expenses...\n";
        if (ExpenseCategory::count() === 0) {
            ExpenseCategory::insert([
                ['name' => 'Rent', 'description' => 'Shop rent', 'is_active' => true, 'limit' => 2000, 'reset_frequency' => 'monthly', 'reset_date' => 1, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Utilities', 'description' => 'Electricity, water, internet', 'is_active' => true, 'limit' => 1500, 'reset_frequency' => 'monthly', 'reset_date' => 1, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Transport', 'description' => 'Delivery and travel', 'is_active' => true, 'limit' => 800, 'reset_frequency' => 'monthly', 'reset_date' => 1, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Maintenance', 'description' => 'Tools and service maintenance', 'is_active' => true, 'limit' => 1000, 'reset_frequency' => 'monthly', 'reset_date' => 1, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        if (Expense::count() === 0) {
            $expenseCategoryIds = ExpenseCategory::query()->pluck('id')->values();
            $expenseUserIds = User::query()->pluck('id')->values();

            for ($i = 0; $i < 12; $i++) {
                Expense::create([
                    'expense_category_id' => $expenseCategoryIds[$i % max(1, $expenseCategoryIds->count())],
                    'user_id' => $expenseUserIds[$i % max(1, $expenseUserIds->count())],
                    'expense_date' => now()->subDays(30 - $i),
                    'amount' => rand(25, 350),
                    'description' => 'Dummy expense #'.($i + 1),
                    'receipt' => null,
                ]);
            }
        }
        echo "✓ Expense categories and expenses created\n\n";

        // Create purchases, purchase items, and purchase payments
        echo "Creating purchases and sale transactions...\n";
        if (Purchase::count() < 6 || Sale::count() < 10) {
            DB::transaction(function () use ($admin) {
                $supplierIds = Supplier::query()->pluck('id')->values();
                $customerIds = Customer::query()->pluck('id')->values();
                $productIds = Product::query()->pluck('id')->values();

                if ($supplierIds->isEmpty() || $customerIds->isEmpty() || $productIds->count() < 2) {
                    return;
                }

                // Purchases
                for ($i = 1; $i <= 6; $i++) {
                    $purchaseNo = 'PUR-DMY-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT);
                    if (Purchase::where('purchase_no', $purchaseNo)->exists()) {
                        continue;
                    }

                    $p1 = Product::find($productIds[($i * 2 - 2) % max(1, $productIds->count())]);
                    $p2 = Product::find($productIds[($i * 2 - 1) % max(1, $productIds->count())]);

                    if (! $p1 || ! $p2) {
                        continue;
                    }

                    $qty1 = 10 + $i;
                    $qty2 = 6 + $i;
                    $line1 = round((float) $p1->cost_price * $qty1, 2);
                    $line2 = round((float) $p2->cost_price * $qty2, 2);
                    $subtotal = $line1 + $line2;
                    $tax = round($subtotal * 0.05, 2);
                    $shipping = 10;
                    $total = $subtotal + $tax + $shipping;
                    $paid = $i % 2 === 0 ? $total : round($total * 0.6, 2);
                    $due = $total - $paid;

                    $purchase = Purchase::create([
                        'purchase_no' => $purchaseNo,
                        'reference_no' => 'REF-DMY-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                        'supplier_id' => $supplierIds[($i - 1) % max(1, $supplierIds->count())],
                        'user_id' => $admin->id,
                        'purchase_date' => now()->subDays(45 - ($i * 3))->toDateString(),
                        'status' => 'received',
                        'discount_type' => 'none',
                        'discount_amount' => 0,
                        'tax_id' => null,
                        'tax_amount' => $tax,
                        'shipping_cost' => $shipping,
                        'shipping_type' => 'divided',
                        'payment_method' => 'cash',
                        'total_amount' => $total,
                        'paid_amount' => $paid,
                        'due_amount' => $due,
                        'payment_status' => $due <= 0 ? 'paid' : 'partial',
                        'document_path' => null,
                        'notes' => 'Dummy purchase entry',
                    ]);

                    PurchaseItem::create([
                        'purchase_id' => $purchase->id,
                        'product_id' => $p1->id,
                        'quantity' => $qty1,
                        'unit_cost' => $p1->cost_price,
                        'total' => $line1,
                    ]);

                    PurchaseItem::create([
                        'purchase_id' => $purchase->id,
                        'product_id' => $p2->id,
                        'quantity' => $qty2,
                        'unit_cost' => $p2->cost_price,
                        'total' => $line2,
                    ]);

                    if ($paid > 0) {
                        Payment::create([
                            'purchase_id' => $purchase->id,
                            'supplier_id' => $purchase->supplier_id,
                            'sale_id' => null,
                            'customer_id' => null,
                            'amount' => $paid,
                            'payment_method' => 'cash',
                            'payment_date' => $purchase->purchase_date,
                            'notes' => 'Dummy purchase payment',
                        ]);
                    }
                }

                // Sales
                for ($i = 1; $i <= 10; $i++) {
                    $saleNo = 'INV-DMY-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT);
                    if (Sale::where('sale_no', $saleNo)->exists()) {
                        continue;
                    }

                    $p1 = Product::find($productIds[($i - 1) % max(1, $productIds->count())]);
                    $p2 = Product::find($productIds[$i % max(1, $productIds->count())]);

                    if (! $p1 || ! $p2) {
                        continue;
                    }

                    $qty1 = 1 + ($i % 4);
                    $qty2 = 1 + (($i + 1) % 3);
                    $line1 = round((float) $p1->selling_price * $qty1, 2);
                    $line2 = round((float) $p2->selling_price * $qty2, 2);
                    $subtotal = $line1 + $line2;
                    $tax = round($subtotal * 0.03, 2);
                    $discount = $i % 3 === 0 ? 5 : 0;
                    $total = $subtotal + $tax - $discount;
                    $paid = $i % 4 === 0 ? round($total * 0.5, 2) : $total;
                    $due = $total - $paid;

                    $sale = Sale::create([
                        'sale_no' => $saleNo,
                        'customer_id' => $customerIds[($i - 1) % max(1, $customerIds->count())],
                        'user_id' => $admin->id,
                        'sale_date' => now()->subDays(25 - ($i * 2))->toDateString(),
                        'subtotal' => $subtotal,
                        'tax' => $tax,
                        'discount' => $discount,
                        'total_amount' => $total,
                        'paid_amount' => $paid,
                        'tendered_amount' => $paid,
                        'due_amount' => $due,
                        'payment_status' => $due <= 0 ? 'paid' : 'partial',
                        'payment_method' => 'cash',
                        'sale_type' => 'sale',
                        'notes' => 'Dummy sale entry',
                    ]);

                    SaleItem::create([
                        'sale_id' => $sale->id,
                        'product_id' => $p1->id,
                        'quantity' => $qty1,
                        'unit_price' => $p1->selling_price,
                        'total' => $line1,
                    ]);

                    SaleItem::create([
                        'sale_id' => $sale->id,
                        'product_id' => $p2->id,
                        'quantity' => $qty2,
                        'unit_price' => $p2->selling_price,
                        'total' => $line2,
                    ]);

                    if ($paid > 0) {
                        Payment::create([
                            'purchase_id' => null,
                            'supplier_id' => null,
                            'sale_id' => $sale->id,
                            'customer_id' => $sale->customer_id,
                            'amount' => $paid,
                            'payment_method' => 'cash',
                            'payment_date' => $sale->sale_date,
                            'notes' => 'Dummy sale payment',
                        ]);
                    }
                }
            });
        }
        echo "✓ Purchases, sales, and payments created\n\n";

        $this->seedCalculationDemoData($admin);
        $this->seedAccountingAndStoreDemoData($admin);

        // Create Default Settings
        echo "Creating system settings...\n";
        $settings = [
            ['key' => 'business_name', 'value' => 'Vehicle POS', 'type' => 'text', 'group' => 'business'],
            ['key' => 'business_email', 'value' => 'info@vehiclepos.com', 'type' => 'text', 'group' => 'business'],
            ['key' => 'business_phone', 'value' => '+1234567890', 'type' => 'text', 'group' => 'business'],
            ['key' => 'business_address', 'value' => '123 Auto Parts Street', 'type' => 'text', 'group' => 'business'],
            ['key' => 'currency', 'value' => 'USD', 'type' => 'text', 'group' => 'general'],
            ['key' => 'currency_symbol', 'value' => 'Rs ', 'type' => 'text', 'group' => 'general'],
            ['key' => 'invoice_printer_size', 'value' => '80mm', 'type' => 'text', 'group' => 'invoice'],
            ['key' => 'tax_rate', 'value' => '0', 'type' => 'number', 'group' => 'general'],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                [
                    'value' => $setting['value'],
                    'type' => $setting['type'],
                    'group' => $setting['group'],
                ]
            );
        }
        echo "✓ Settings created successfully\n\n";

        echo "================================================\n";
        echo "✅ Database seeded successfully!\n";
        echo "================================================\n\n";
        echo "📌 Login Credentials:\n\n";
        echo "🕵️ Super Admin (Secret Dashboard):\n";
        echo "   Email: superadmin@vehiclepos.com\n";
        echo "   Username: superadmin\n";
        echo "   Password: superadmin123\n\n";
        echo "🔐 Admin (Full Access):\n";
        echo "   Email: admin@vehiclepos.com\n";
        echo "   Username: admin\n";
        echo "   Password: admin123\n\n";
        echo "👔 Manager (Limited Access):\n";
        echo "   Email: manager@vehiclepos.com\n";
        echo "   Username: manager\n";
        echo "   Password: manager123\n\n";
        echo "💰 Cashier (POS Only):\n";
        echo "   Email: cashier@vehiclepos.com\n";
        echo "   Username: cashier\n";
        echo "   Password: cashier123\n\n";
        echo "================================================\n";
    }

    private function seedCalculationDemoData(User $admin): void
    {
        echo "Creating calculation demo data...\n";

        DB::transaction(function () use ($admin) {
            $this->clearCalculationDemoTransactions();

            $unit = Unit::query()->where('short_name', 'pc')->first() ?? Unit::query()->first();
            $category = Category::query()->firstOrCreate(
                ['name' => 'Calculation Demo'],
                [
                    'description' => 'Products used to verify POS calculations',
                    'parent_id' => null,
                    'is_active' => true,
                ]
            );
            $brand = Brand::query()->firstOrCreate(
                ['name' => 'Demo Verified'],
                [
                    'description' => 'Demo brand for calculation checks',
                    'logo' => null,
                    'is_active' => true,
                ]
            );
            $supplier = Supplier::query()->updateOrCreate(
                ['phone' => '+94770001001'],
                [
                    'name' => 'Demo Calculation Supplier',
                    'company_name' => 'Demo Calculation Supplier Pvt Ltd',
                    'email' => 'supplier.demo@example.com',
                    'address' => 'Demo Supply Street',
                    'city' => 'Colombo',
                    'country' => 'Sri Lanka',
                    'opening_balance' => 0,
                    'is_active' => true,
                ]
            );
            $customer = Customer::query()->updateOrCreate(
                ['phone' => '+94770002001'],
                [
                    'name' => 'Demo Calculation Customer',
                    'email' => 'customer.demo@example.com',
                    'address' => 'Demo Customer Road',
                    'city' => 'Colombo',
                    'country' => 'Sri Lanka',
                    'opening_balance' => 25,
                    'is_active' => true,
                ]
            );

            $products = [
                'DEMO-CALC-A' => ['name' => 'Demo Calc Brake Cleaner', 'cost' => 100, 'sell' => 150, 'stock' => 16, 'alert' => 5, 'barcode' => '990000000001'],
                'DEMO-CALC-B' => ['name' => 'Demo Calc Engine Oil', 'cost' => 200, 'sell' => 300, 'stock' => 7, 'alert' => 5, 'barcode' => '990000000002'],
                'DEMO-CALC-C' => ['name' => 'Demo Calc Bulb Pack', 'cost' => 50, 'sell' => 85, 'stock' => 3, 'alert' => 5, 'barcode' => '990000000003'],
                'DEMO-CALC-LOW' => ['name' => 'Demo Calc Low Stock Fuse', 'cost' => 20, 'sell' => 35, 'stock' => 2, 'alert' => 5, 'barcode' => '990000000004'],
            ];

            $productModels = [];
            foreach ($products as $sku => $data) {
                $product = Product::query()->updateOrCreate(
                    ['sku' => $sku],
                    [
                        'name' => $data['name'],
                        'category_id' => $category->id,
                        'brand_id' => $brand->id,
                        'unit_id' => $unit->id,
                        'visible_units' => ['pc', 'set2', 'set4'],
                        'description' => 'Deterministic demo item for totals, stock, and due checks',
                        'cost_price' => $data['cost'],
                        'selling_price' => $data['sell'],
                        'stock_quantity' => $data['stock'],
                        'alert_quantity' => $data['alert'],
                        'image' => null,
                        'barcode' => $data['barcode'],
                        'is_active' => true,
                    ]
                );

                if (Schema::hasColumn('products', 'base_unit')) {
                    $product->forceFill(['base_unit' => 'pc'])->save();
                }
                if (Schema::hasColumn('products', 'unit_factors')) {
                    $product->forceFill(['unit_factors' => ['pc' => 1, 'set2' => 2, 'set4' => 4]])->save();
                }
                if (Schema::hasTable('category_product')) {
                    $product->categories()->syncWithoutDetaching([$category->id]);
                }
                if (Schema::hasTable('brand_product')) {
                    $product->brands()->syncWithoutDetaching([$brand->id]);
                }

                $productModels[$sku] = $product->refresh();
            }

            $purchase1 = Purchase::query()->create([
                'purchase_no' => 'DEMO-CALC-PUR-001',
                'reference_no' => 'DEMO-CALC-REF-001',
                'supplier_id' => $supplier->id,
                'user_id' => $admin->id,
                'purchase_date' => now()->subDays(8)->toDateString(),
                'status' => 'received',
                'discount_type' => 'none',
                'discount_amount' => 0,
                'tax_id' => null,
                'tax_amount' => 200,
                'shipping_cost' => 100,
                'shipping_type' => 'divided',
                'payment_method' => 'cash',
                'total_amount' => 4300,
                'paid_amount' => 3000,
                'due_amount' => 1100,
                'payment_status' => 'partial',
                'document_path' => null,
                'notes' => 'Calculation demo: subtotal 4000 + VAT 200 + shipping 100; account return 200 leaves due 1100.',
            ]);
            $purchaseItemA = PurchaseItem::query()->create([
                'purchase_id' => $purchase1->id,
                'product_id' => $productModels['DEMO-CALC-A']->id,
                'quantity' => 20,
                'unit_cost' => 100,
                'total' => 2000,
            ]);
            PurchaseItem::query()->create([
                'purchase_id' => $purchase1->id,
                'product_id' => $productModels['DEMO-CALC-B']->id,
                'quantity' => 10,
                'unit_cost' => 200,
                'total' => 2000,
            ]);
            Payment::query()->create([
                'purchase_id' => $purchase1->id,
                'supplier_id' => $supplier->id,
                'amount' => 3000,
                'payment_method' => 'cash',
                'payment_date' => $purchase1->purchase_date,
                'notes' => 'Calculation demo purchase payment',
            ]);

            $purchaseReturn = PurchaseReturn::query()->create([
                'purchase_id' => $purchase1->id,
                'user_id' => $admin->id,
                'return_date' => now()->subDays(6)->toDateString(),
                'total_refund' => 200,
                'notes' => 'Calculation demo account purchase return',
            ]);
            PurchaseReturnItem::query()->create([
                'purchase_return_id' => $purchaseReturn->id,
                'purchase_item_id' => $purchaseItemA->id,
                'product_id' => $productModels['DEMO-CALC-A']->id,
                'quantity' => 2,
                'unit_price' => 100,
                'total' => 200,
            ]);

            $purchase2 = Purchase::query()->create([
                'purchase_no' => 'DEMO-CALC-PUR-002',
                'reference_no' => 'DEMO-CALC-REF-002',
                'supplier_id' => $supplier->id,
                'user_id' => $admin->id,
                'purchase_date' => now()->subDays(5)->toDateString(),
                'status' => 'received',
                'discount_type' => 'none',
                'discount_amount' => 0,
                'tax_id' => null,
                'tax_amount' => 12.50,
                'shipping_cost' => 0,
                'shipping_type' => 'divided',
                'payment_method' => 'credit',
                'total_amount' => 262.50,
                'paid_amount' => 0,
                'due_amount' => 262.50,
                'payment_status' => 'unpaid',
                'document_path' => null,
                'notes' => 'Calculation demo unpaid purchase: 250 + 5% VAT.',
            ]);
            PurchaseItem::query()->create([
                'purchase_id' => $purchase2->id,
                'product_id' => $productModels['DEMO-CALC-C']->id,
                'quantity' => 5,
                'unit_cost' => 50,
                'total' => 250,
            ]);

            $purchaseToday = Purchase::query()->create([
                'purchase_no' => 'DEMO-CALC-TODAY-PUR-001',
                'reference_no' => 'DEMO-CALC-TODAY-REF-001',
                'supplier_id' => $supplier->id,
                'user_id' => $admin->id,
                'purchase_date' => now()->toDateString(),
                'status' => 'received',
                'discount_type' => 'none',
                'discount_amount' => 0,
                'tax_id' => null,
                'tax_amount' => 50,
                'shipping_cost' => 0,
                'shipping_type' => 'divided',
                'payment_method' => 'cash',
                'total_amount' => 1050,
                'paid_amount' => 1050,
                'due_amount' => 0,
                'payment_status' => 'paid',
                'document_path' => null,
                'notes' => 'Today dashboard demo purchase: subtotal 1000 + VAT 50.',
            ]);
            PurchaseItem::query()->create([
                'purchase_id' => $purchaseToday->id,
                'product_id' => $productModels['DEMO-CALC-A']->id,
                'quantity' => 4,
                'unit_cost' => 100,
                'total' => 400,
            ]);
            PurchaseItem::query()->create([
                'purchase_id' => $purchaseToday->id,
                'product_id' => $productModels['DEMO-CALC-B']->id,
                'quantity' => 3,
                'unit_cost' => 200,
                'total' => 600,
            ]);
            Payment::query()->create([
                'purchase_id' => $purchaseToday->id,
                'supplier_id' => $supplier->id,
                'amount' => 1050,
                'payment_method' => 'cash',
                'payment_date' => $purchaseToday->purchase_date,
                'notes' => 'Today dashboard demo purchase payment',
            ]);

            $sale1 = Sale::query()->create([
                'sale_no' => 'DEMO-CALC-SALE-001',
                'customer_id' => $customer->id,
                'user_id' => $admin->id,
                'sale_date' => now()->subDays(4)->toDateString(),
                'subtotal' => 900,
                'tax' => 0,
                'discount' => 50,
                'total_amount' => 850,
                'paid_amount' => 850,
                'tendered_amount' => 1000,
                'due_amount' => 0,
                'payment_status' => 'paid',
                'payment_method' => 'cash',
                'sale_type' => 'sale',
                'notes' => 'Calculation demo paid sale after one returned item: remaining subtotal 900 - discount 50.',
            ]);
            $saleItemA = SaleItem::query()->create([
                'sale_id' => $sale1->id,
                'product_id' => $productModels['DEMO-CALC-A']->id,
                'quantity' => 3,
                'unit_price' => 150,
                'total' => 450,
            ]);
            SaleItem::query()->create([
                'sale_id' => $sale1->id,
                'product_id' => $productModels['DEMO-CALC-B']->id,
                'quantity' => 2,
                'unit_price' => 300,
                'total' => 600,
            ]);
            Payment::query()->create([
                'sale_id' => $sale1->id,
                'customer_id' => $customer->id,
                'amount' => 1000,
                'payment_method' => 'cash',
                'payment_date' => $sale1->sale_date,
                'notes' => 'Calculation demo original payment',
            ]);
            $saleReturn = SaleReturn::query()->create([
                'sale_id' => $sale1->id,
                'user_id' => $admin->id,
                'return_date' => now()->subDays(3),
                'subtotal' => 150,
                'total_refund' => 150,
                'notes' => 'Calculation demo account credit return',
            ]);
            SaleReturnItem::query()->create([
                'sale_return_id' => $saleReturn->id,
                'sale_item_id' => $saleItemA->id,
                'product_id' => $productModels['DEMO-CALC-A']->id,
                'quantity' => 1,
                'unit_price' => 150,
                'total' => 150,
            ]);
            Payment::query()->create([
                'sale_id' => $sale1->id,
                'customer_id' => $customer->id,
                'amount' => -150,
                'payment_method' => 'account_credit',
                'payment_date' => now()->subDays(3)->toDateString(),
                'notes' => 'Calculation demo account credit refund',
            ]);

            $sale2 = Sale::query()->create([
                'sale_no' => 'DEMO-CALC-SALE-002',
                'customer_id' => $customer->id,
                'user_id' => $admin->id,
                'sale_date' => now()->subDays(2)->toDateString(),
                'subtotal' => 170,
                'tax' => 0,
                'discount' => 0,
                'total_amount' => 170,
                'paid_amount' => 50,
                'tendered_amount' => 50,
                'due_amount' => 120,
                'payment_status' => 'partial',
                'payment_method' => 'cash',
                'sale_type' => 'sale',
                'notes' => 'Calculation demo partial sale: 170 total, 50 paid, 120 due.',
            ]);
            SaleItem::query()->create([
                'sale_id' => $sale2->id,
                'product_id' => $productModels['DEMO-CALC-C']->id,
                'quantity' => 2,
                'unit_price' => 85,
                'total' => 170,
            ]);
            Payment::query()->create([
                'sale_id' => $sale2->id,
                'customer_id' => $customer->id,
                'amount' => 50,
                'payment_method' => 'cash',
                'payment_date' => $sale2->sale_date,
                'notes' => 'Calculation demo partial payment',
            ]);

            $saleToday = Sale::query()->create([
                'sale_no' => 'DEMO-CALC-TODAY-SALE-001',
                'customer_id' => $customer->id,
                'user_id' => $admin->id,
                'sale_date' => now()->toDateString(),
                'subtotal' => 600,
                'tax' => 0,
                'discount' => 50,
                'total_amount' => 550,
                'paid_amount' => 500,
                'tendered_amount' => 500,
                'due_amount' => 50,
                'payment_status' => 'partial',
                'payment_method' => 'cash',
                'sale_type' => 'sale',
                'notes' => 'Today dashboard demo sale: subtotal 600 - discount 50, paid 500, due 50.',
            ]);
            SaleItem::query()->create([
                'sale_id' => $saleToday->id,
                'product_id' => $productModels['DEMO-CALC-A']->id,
                'quantity' => 2,
                'unit_price' => 150,
                'total' => 300,
            ]);
            SaleItem::query()->create([
                'sale_id' => $saleToday->id,
                'product_id' => $productModels['DEMO-CALC-B']->id,
                'quantity' => 1,
                'unit_price' => 300,
                'total' => 300,
            ]);
            Payment::query()->create([
                'sale_id' => $saleToday->id,
                'customer_id' => $customer->id,
                'amount' => 500,
                'payment_method' => 'cash',
                'payment_date' => $saleToday->sale_date,
                'notes' => 'Today dashboard demo partial payment',
            ]);

            $quotation = Sale::query()->create([
                'sale_no' => 'DEMO-CALC-QUO-001',
                'customer_id' => $customer->id,
                'user_id' => $admin->id,
                'sale_date' => now()->subDay()->toDateString(),
                'subtotal' => 320,
                'tax' => 0,
                'discount' => 20,
                'total_amount' => 300,
                'paid_amount' => 0,
                'tendered_amount' => 0,
                'due_amount' => 0,
                'payment_status' => 'unpaid',
                'payment_method' => 'cash',
                'sale_type' => 'quotation',
                'notes' => 'Calculation demo quotation: no stock/payment effect until converted.',
            ]);
            SaleItem::query()->create([
                'sale_id' => $quotation->id,
                'product_id' => $productModels['DEMO-CALC-A']->id,
                'quantity' => 1,
                'unit_price' => 150,
                'total' => 150,
            ]);
            SaleItem::query()->create([
                'sale_id' => $quotation->id,
                'product_id' => $productModels['DEMO-CALC-C']->id,
                'quantity' => 2,
                'unit_price' => 85,
                'total' => 170,
            ]);

            $writeOffCategory = ExpenseCategory::query()->firstOrCreate(
                ['name' => 'Product Write-off'],
                [
                    'description' => 'Inventory write-off losses',
                    'is_active' => true,
                    'limit' => 1000,
                    'reset_frequency' => 'monthly',
                    'reset_date' => 1,
                ]
            );
            Expense::query()->updateOrCreate(
                ['description' => 'Calculation demo write-off: 1 x Demo Calc Engine Oil'],
                [
                    'expense_category_id' => $writeOffCategory->id,
                    'user_id' => $admin->id,
                    'expense_date' => now()->subDay()->toDateString(),
                    'amount' => 200,
                    'receipt' => null,
                ]
            );
            Expense::query()->updateOrCreate(
                ['description' => 'Today dashboard demo expense: delivery fuel'],
                [
                    'expense_category_id' => $writeOffCategory->id,
                    'user_id' => $admin->id,
                    'expense_date' => now()->toDateString(),
                    'amount' => 80,
                    'receipt' => null,
                ]
            );

            $visibilityDefaults = [
                'stock_visible_percentage' => 100,
                'price_visible_percentage' => 100,
                'qty_visible_percentage' => 100,
                'hidden_products' => [$productModels['DEMO-CALC-LOW']->id],
                'hidden_suppliers' => [$supplier->id],
                'hidden_sales_price_ranges' => [['min' => 300, 'max' => 350, 'hide' => true]],
                'hidden_purchase_price_ranges' => [['min' => 250, 'max' => 300, 'hide' => true]],
            ];

            if (Schema::hasTable('secret_settings')) {
                SecretSetting::set('dashboard.visibility', array_replace_recursive(
                    \App\Services\DashboardVisibilityService::defaults(),
                    $visibilityDefaults
                ), 'json', 'dashboard');
            }

            foreach ($products as $sku => $data) {
                Product::query()->where('sku', $sku)->update(['stock_quantity' => $data['stock']]);
            }

            $this->assertCalculationDemoData();
        });

        echo "✓ Calculation demo data created and verified\n\n";
    }

    private function seedAccountingAndStoreDemoData(User $admin): void
    {
        if (! Schema::hasTable('chart_accounts') || ! Schema::hasTable('stores')) {
            echo "Skipping accounting/store demo data until new migrations are run.\n\n";

            return;
        }

        DB::transaction(function () use ($admin) {
            $accounts = [
                ['1000', 'Assets', 'asset', 'main', 0],
                ['1100', 'Cash', 'asset', 'cash', 25000],
                ['1200', 'Bank', 'asset', 'bank', 150000],
                ['1300', 'Customer Receivable', 'asset', 'customer_receivable', 42000],
                ['2000', 'Liabilities', 'liability', 'main', 0],
                ['2100', 'Supplier Payable', 'liability', 'supplier_payable', 60000],
                ['3000', 'Equity', 'equity', 'main', 500000],
                ['3100', 'Owner Capital', 'equity', 'owner_capital', 0],
                ['3200', 'Owner Drawings', 'equity', 'owner_drawings', 0],
                ['4000', 'Income', 'income', 'main', 0],
                ['5000', 'Expense', 'expense', 'main', 0],
            ];

            foreach ($accounts as [$code, $name, $type, $subtype, $opening]) {
                ChartAccount::updateOrCreate(
                    ['code' => $code],
                    [
                        'name' => $name,
                        'type' => $type,
                        'subtype' => $subtype,
                        'opening_balance' => $opening,
                        'current_balance' => $opening,
                        'is_system' => true,
                        'is_active' => true,
                    ]
                );
            }

            $mainBankAccount = ChartAccount::where('code', '1200')->firstOrFail();
            $pettyCashAccount = ChartAccount::where('code', '1100')->firstOrFail();

            $bank = BankAccount::updateOrCreate(
                ['account_number' => 'QB-DEMO-001'],
                [
                    'chart_account_id' => $mainBankAccount->id,
                    'bank_name' => 'Demo Commercial Bank',
                    'account_name' => 'Operating Account',
                    'opening_balance' => 150000,
                    'statement_balance' => 152750,
                    'is_active' => true,
                ]
            );

            $pettyFund = PettyCashFund::updateOrCreate(
                ['name' => 'Head Office Petty Cash'],
                [
                    'chart_account_id' => $pettyCashAccount->id,
                    'opening_balance' => 10000,
                    'current_balance' => 8500,
                    'is_active' => true,
                ]
            );

            $bankExpenseCategory = ExpenseCategory::firstOrCreate(
                ['name' => 'Bank Charges'],
                ['description' => 'Bank fees and charges', 'is_active' => true, 'limit' => 0, 'reset_frequency' => 'monthly', 'reset_date' => 1]
            );
            $dailyExpenseCategory = ExpenseCategory::firstOrCreate(
                ['name' => 'Daily Expenses'],
                ['description' => 'Small operating expenses', 'is_active' => true, 'limit' => 0, 'reset_frequency' => 'monthly', 'reset_date' => 1]
            );

            AccountTransaction::updateOrCreate(
                ['reference_no' => 'ACC-DEMO-BANK-OPENING'],
                [
                    'account_id' => $mainBankAccount->id,
                    'user_id' => $admin->id,
                    'transaction_date' => now()->subDays(8)->toDateString(),
                    'direction' => 'in',
                    'payment_method' => 'bank_deposit',
                    'amount' => 150000,
                    'source_type' => 'sample',
                    'description' => 'Bank opening balance',
                    'is_reconciled' => true,
                    'reconciled_at' => now(),
                ]
            );
            AccountTransaction::updateOrCreate(
                ['reference_no' => 'ACC-DEMO-CHEQUE-001'],
                [
                    'account_id' => $mainBankAccount->id,
                    'related_account_id' => ChartAccount::where('code', '1300')->value('id'),
                    'user_id' => $admin->id,
                    'transaction_date' => now()->subDays(3)->toDateString(),
                    'direction' => 'in',
                    'payment_method' => 'cheque',
                    'amount' => 35000,
                    'cheque_number' => 'CHQ-458921',
                    'source_type' => 'sample',
                    'description' => 'Customer cheque received for credit sale',
                ]
            );
            AccountTransaction::updateOrCreate(
                ['reference_no' => 'ACC-DEMO-BANK-FEE'],
                [
                    'account_id' => $mainBankAccount->id,
                    'related_account_id' => ChartAccount::where('code', '5000')->value('id'),
                    'user_id' => $admin->id,
                    'transaction_date' => now()->subDays(2)->toDateString(),
                    'direction' => 'out',
                    'payment_method' => 'bank_transfer',
                    'amount' => 750,
                    'source_type' => 'sample',
                    'description' => 'Bank service charge',
                ]
            );
            AccountTransaction::updateOrCreate(
                ['reference_no' => 'ACC-DEMO-LOAN'],
                [
                    'account_id' => $mainBankAccount->id,
                    'related_account_id' => ChartAccount::where('code', '2100')->value('id'),
                    'user_id' => $admin->id,
                    'transaction_date' => now()->subDay()->toDateString(),
                    'direction' => 'out',
                    'payment_method' => 'bank_transfer',
                    'amount' => 18000,
                    'source_type' => 'sample',
                    'description' => 'Loan monthly installment',
                ]
            );

            PettyCashExpense::updateOrCreate(
                ['voucher_no' => 'PC-DEMO-001'],
                [
                    'petty_cash_fund_id' => $pettyFund->id,
                    'expense_category_id' => $dailyExpenseCategory->id,
                    'user_id' => $admin->id,
                    'expense_date' => now()->toDateString(),
                    'amount' => 1500,
                    'description' => 'Tea, courier and local delivery expenses',
                ]
            );

            BankReconciliation::updateOrCreate(
                ['bank_account_id' => $bank->id, 'statement_date' => now()->toDateString()],
                [
                    'user_id' => $admin->id,
                    'statement_balance' => 152750,
                    'system_balance' => (float) $mainBankAccount->current_balance,
                    'difference' => round(152750 - (float) $mainBankAccount->current_balance, 2),
                    'status' => 'difference',
                    'notes' => 'Sample statement balance for reconciliation testing.',
                ]
            );

            $mainStore = Store::updateOrCreate(
                ['code' => 'MAIN'],
                ['name' => 'Main Store', 'phone' => '+94110000001', 'address' => 'Main showroom', 'is_default' => true, 'is_active' => true]
            );
            $branchStore = Store::updateOrCreate(
                ['code' => 'BR01'],
                ['name' => 'Branch Store', 'phone' => '+94110000002', 'address' => 'Branch counter', 'is_default' => false, 'is_active' => true]
            );

            $product = Product::query()->where('sku', 'DEMO-CALC-A')->first() ?: Product::query()->first();
            $supplier = Supplier::query()->first();
            if ($product && $supplier) {
                $shipment = StockShipment::updateOrCreate(
                    ['shipment_no' => 'SHIP-DEMO-001'],
                    [
                        'grn_no' => 'GRN-DEMO-001',
                        'supplier_id' => $supplier->id,
                        'shipment_date' => now()->subDays(5)->toDateString(),
                        'received_date' => now()->subDays(4)->toDateString(),
                        'status' => 'allocated',
                        'freight_cost' => 12000,
                        'duty_cost' => 8000,
                        'other_cost' => 2500,
                        'notes' => 'Sample shipment with landed-cost calculation.',
                    ]
                );

                $item = StockShipmentItem::updateOrCreate(
                    ['stock_shipment_id' => $shipment->id, 'product_id' => $product->id],
                    [
                        'quantity' => 40,
                        'unit_cost' => 950,
                        'landed_unit_cost' => 1512.50,
                        'selling_price' => 1850,
                    ]
                );

                StockShipmentAllocation::updateOrCreate(
                    ['stock_shipment_item_id' => $item->id, 'store_id' => $mainStore->id],
                    ['quantity' => 25]
                );
                StockShipmentAllocation::updateOrCreate(
                    ['stock_shipment_item_id' => $item->id, 'store_id' => $branchStore->id],
                    ['quantity' => 15]
                );
                StoreStock::updateOrCreate(
                    ['store_id' => $mainStore->id, 'product_id' => $product->id, 'product_price_id' => null],
                    ['quantity' => 22]
                );
                StoreStock::updateOrCreate(
                    ['store_id' => $branchStore->id, 'product_id' => $product->id, 'product_price_id' => null],
                    ['quantity' => 18]
                );
                StoreStockTransfer::updateOrCreate(
                    ['reference_no' => 'TR-DEMO-001'],
                    [
                        'from_store_id' => $mainStore->id,
                        'to_store_id' => $branchStore->id,
                        'product_id' => $product->id,
                        'quantity' => 3,
                        'transfer_date' => now()->toDateString(),
                        'notes' => 'Sample transfer for branch demand.',
                    ]
                );
            }
        });

        echo "✓ QuickBooks accounting and store stock sample data created\n\n";
    }

    private function clearCalculationDemoTransactions(): void
    {
        $saleIds = Sale::query()
            ->where('sale_no', 'like', 'DEMO-CALC-%')
            ->pluck('id');
        $purchaseIds = Purchase::query()
            ->where('purchase_no', 'like', 'DEMO-CALC-%')
            ->pluck('id');

        if ($saleIds->isNotEmpty()) {
            SaleReturn::query()->whereIn('sale_id', $saleIds)->delete();
            Payment::query()->whereIn('sale_id', $saleIds)->delete();
            SaleItem::query()->whereIn('sale_id', $saleIds)->delete();
            Sale::query()->whereIn('id', $saleIds)->delete();
        }

        if ($purchaseIds->isNotEmpty()) {
            PurchaseReturn::query()->whereIn('purchase_id', $purchaseIds)->delete();
            Payment::query()->whereIn('purchase_id', $purchaseIds)->delete();
            PurchaseItem::query()->whereIn('purchase_id', $purchaseIds)->delete();
            Purchase::query()->whereIn('id', $purchaseIds)->delete();
        }
    }

    private function assertCalculationDemoData(): void
    {
        $sale1 = Sale::query()->where('sale_no', 'DEMO-CALC-SALE-001')->with(['items', 'payments'])->firstOrFail();
        $sale2 = Sale::query()->where('sale_no', 'DEMO-CALC-SALE-002')->with(['items', 'payments'])->firstOrFail();
        $saleToday = Sale::query()->where('sale_no', 'DEMO-CALC-TODAY-SALE-001')->with(['items', 'payments'])->firstOrFail();
        $quotation = Sale::query()->where('sale_no', 'DEMO-CALC-QUO-001')->with('items')->firstOrFail();
        $purchase1 = Purchase::query()->where('purchase_no', 'DEMO-CALC-PUR-001')->with(['items', 'payments'])->firstOrFail();
        $purchase2 = Purchase::query()->where('purchase_no', 'DEMO-CALC-PUR-002')->with('items')->firstOrFail();
        $purchaseToday = Purchase::query()->where('purchase_no', 'DEMO-CALC-TODAY-PUR-001')->with(['items', 'payments'])->firstOrFail();

        \App\Services\SaleRecalculationService::recalculateSaleFinancials($sale1);
        $sale1->refresh();

        $checks = [
            'DEMO-CALC-PUR-001 total' => [4300.00, (float) $purchase1->total_amount],
            'DEMO-CALC-PUR-001 paid' => [3000.00, (float) $purchase1->payments->sum('amount')],
            'DEMO-CALC-PUR-001 due after return' => [1100.00, (float) $purchase1->due_amount],
            'DEMO-CALC-PUR-002 total' => [262.50, (float) $purchase2->total_amount],
            'DEMO-CALC-TODAY-PUR-001 total' => [1050.00, (float) $purchaseToday->total_amount],
            'DEMO-CALC-TODAY-PUR-001 paid' => [1050.00, (float) $purchaseToday->payments->sum('amount')],
            'DEMO-CALC-SALE-001 total' => [850.00, (float) $sale1->total_amount],
            'DEMO-CALC-SALE-001 paid net' => [850.00, (float) $sale1->payments->sum('amount')],
            'DEMO-CALC-SALE-002 due' => [120.00, (float) $sale2->due_amount],
            'DEMO-CALC-TODAY-SALE-001 total' => [550.00, (float) $saleToday->total_amount],
            'DEMO-CALC-TODAY-SALE-001 paid' => [500.00, (float) $saleToday->payments->sum('amount')],
            'DEMO-CALC-TODAY-SALE-001 due' => [50.00, (float) $saleToday->due_amount],
            'DEMO-CALC-QUO-001 total' => [300.00, (float) $quotation->total_amount],
            'DEMO-CALC-A stock' => [16.00, (float) Product::query()->where('sku', 'DEMO-CALC-A')->value('stock_quantity')],
            'DEMO-CALC-B stock' => [7.00, (float) Product::query()->where('sku', 'DEMO-CALC-B')->value('stock_quantity')],
            'DEMO-CALC-C stock' => [3.00, (float) Product::query()->where('sku', 'DEMO-CALC-C')->value('stock_quantity')],
        ];

        foreach ($checks as $label => [$expected, $actual]) {
            if (round($actual, 2) !== round($expected, 2)) {
                throw new \RuntimeException("{$label} expected {$expected}, got {$actual}");
            }
        }
    }
}
