<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\User;
use App\Models\Unit;
use App\Models\Supplier;
use App\Models\Setting;
use Illuminate\Support\Facades\Hash;

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
        $adminRole = Role::create([
            'name' => 'Admin',
            'description' => 'Administrator with full system access and all permissions',
            'permissions' => [
                // Dashboard
                'dashboard.view',
                
                // User Management
                'users.view', 'users.create', 'users.edit', 'users.delete',
                'roles.view', 'roles.create', 'roles.edit', 'roles.delete',
                
                // Product Management
                'products.view', 'products.create', 'products.edit', 'products.delete', 'products.update-price',
                'categories.view', 'categories.create', 'categories.edit', 'categories.delete',
                'brands.view', 'brands.create', 'brands.edit', 'brands.delete',
                'units.view', 'units.create', 'units.edit', 'units.delete',
                
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
                
                // Reports
                'reports.sales', 'reports.purchase', 'reports.profit-loss', 
                'reports.stock', 'reports.expense', 'reports.trending',
                
                // Settings & System
                'settings.view', 'settings.edit',
                'activity-log.view',
                'notifications.view', 'notifications.configure',
            ],
            'is_active' => true,
        ]);

        // Create Manager Role
        $managerRole = Role::create([
            'name' => 'Manager',
            'description' => 'Manager with limited management access',
            'permissions' => [
                'dashboard.view',
                'products.view', 'products.create', 'products.edit', 'products.update-price',
                'categories.view', 'categories.create', 'categories.edit',
                'brands.view', 'brands.create', 'brands.edit',
                'suppliers.view', 'suppliers.create', 'suppliers.edit',
                'customers.view', 'customers.create', 'customers.edit',
                'purchases.view', 'purchases.create', 'purchases.edit',
                'sales.view', 'sales.create', 'sales.edit',
                'pos.access',
                'expenses.view', 'expenses.create',
                'reports.sales', 'reports.purchase', 'reports.stock',
            ],
            'is_active' => true,
        ]);

        // Create Cashier Role
        $cashierRole = Role::create([
            'name' => 'Cashier',
            'description' => 'Cashier with POS and sales access only',
            'permissions' => [
                'dashboard.view',
                'pos.access',
                'sales.view', 'sales.create',
                'customers.view', 'customers.create',
                'products.view',
            ],
            'is_active' => true,
        ]);

        echo "✓ Roles created successfully\n\n";

        // Create Admin User with ALL permissions
        echo "Creating users...\n";
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@vehiclepos.com',
            'password' => Hash::make('admin123'),
            'phone' => '+1234567890',
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);
        echo "✓ Admin user created: admin@vehiclepos.com / admin123\n";

        // Create Manager User
        $manager = User::create([
            'name' => 'Manager',
            'email' => 'manager@vehiclepos.com',
            'password' => Hash::make('manager123'),
            'phone' => '+1234567891',
            'role_id' => $managerRole->id,
            'is_active' => true,
        ]);
        echo "✓ Manager user created: manager@vehiclepos.com / manager123\n";

        // Create Cashier User
        $cashier = User::create([
            'name' => 'Cashier',
            'email' => 'cashier@vehiclepos.com',
            'password' => Hash::make('cashier123'),
            'phone' => '+1234567892',
            'role_id' => $cashierRole->id,
            'is_active' => true,
        ]);
        echo "✓ Cashier user created: cashier@vehiclepos.com / cashier123\n\n";

        // Create a few sample Suppliers
        echo "Creating sample suppliers...\n";
        Supplier::insert([
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
                'created_at' => now(),
                'updated_at' => now(),
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
                'created_at' => now(),
                'updated_at' => now(),
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
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        echo "✓ Sample suppliers created\n\n";

        // Create Default Units
        echo "Creating default units...\n";
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
        echo "✓ Units created: Piece, Set of 2, Set of 4, Dozen\n\n";

        // Create Default Settings
        echo "Creating system settings...\n";
        Setting::insert([
            [
                'key' => 'business_name',
                'value' => 'Vehicle POS',
                'type' => 'text',
                'group' => 'business',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'business_email',
                'value' => 'info@vehiclepos.com',
                'type' => 'text',
                'group' => 'business',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'business_phone',
                'value' => '+1234567890',
                'type' => 'text',
                'group' => 'business',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'business_address',
                'value' => '123 Auto Parts Street',
                'type' => 'text',
                'group' => 'business',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'currency',
                'value' => 'USD',
                'type' => 'text',
                'group' => 'general',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'currency_symbol',
                'value' => 'Rs ',
                'type' => 'text',
                'group' => 'general',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'invoice_printer_size',
                'value' => '80mm',
                'type' => 'text',
                'group' => 'invoice',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'tax_rate',
                'value' => '0',
                'type' => 'number',
                'group' => 'general',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        echo "✓ Settings created successfully\n\n";

        echo "================================================\n";
        echo "✅ Database seeded successfully!\n";
        echo "================================================\n\n";
        echo "📌 Login Credentials:\n\n";
        echo "🔐 Admin (Full Access):\n";
        echo "   Email: admin@vehiclepos.com\n";
        echo "   Password: admin123\n\n";
        echo "👔 Manager (Limited Access):\n";
        echo "   Email: manager@vehiclepos.com\n";
        echo "   Password: manager123\n\n";
        echo "💰 Cashier (POS Only):\n";
        echo "   Email: cashier@vehiclepos.com\n";
        echo "   Password: cashier123\n\n";
        echo "================================================\n";
    }
}

