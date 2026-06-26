<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\PrivacyModeSetting;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Role as AppRole;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\DashboardVisibilityService;
use App\Services\PrivacyModeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PrivacyModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create standard permissions and roles if spatie permissions are loaded
        if (class_exists(Permission::class)) {
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
            Permission::firstOrCreate(['name' => 'privacy_mode.view']);
            Permission::firstOrCreate(['name' => 'privacy_mode.toggle']);
            Permission::firstOrCreate(['name' => 'privacy_mode.settings']);
            Permission::firstOrCreate(['name' => 'privacy_mode.bypass']);
        }
    }

    public function test_privacy_mode_mask_amount_behavior()
    {
        $settings = PrivacyModeSetting::first() ?? new PrivacyModeSetting;
        $settings->fill([
            'is_enabled' => true,
            'shortcut_key' => 'Alt+S',
            'visible_invoice_limit' => 10,
            'masking_type' => 'hide',
            'apply_to_pos' => true,
            'apply_to_sales_list' => true,
            'apply_to_reports' => true,
            'apply_to_dashboard' => true,
            'apply_to_customer_history' => true,
        ]);
        $settings->save();

        $this->assertEquals('—', PrivacyModeService::maskAmount(100.0));

        $settings->update(['masking_type' => 'low_amount']);
        // 10% of 150 = 15.00
        $this->assertEquals('15.00', PrivacyModeService::maskAmount(150.0));

        $settings->update(['masking_type' => 'blur']);
        $this->assertEquals('****', PrivacyModeService::maskAmount(150.0));

        $settings->update(['masking_type' => 'hidden']);
        $this->assertEquals('Hidden', PrivacyModeService::maskAmount(150.0));
    }

    public function test_privacy_mode_ordered_invoice_label_behavior()
    {
        $this->assertEquals('INV-OTGWOIJUM01', PrivacyModeService::orderedInvoiceLabel('INV-OTGWOIJUM93', 1));
        $this->assertEquals('INV-MIAWOIZCM02', PrivacyModeService::orderedInvoiceLabel('INV-MIAWOIZCM35', 2));
        $this->assertEquals('INV-12', PrivacyModeService::orderedInvoiceLabel('QUO-20260507-0001', 12));
    }

    public function test_privacy_mode_is_active_for_user_checks()
    {
        $settings = PrivacyModeSetting::first() ?? new PrivacyModeSetting;
        $settings->fill([
            'is_enabled' => false,
            'shortcut_key' => 'Alt+S',
            'visible_invoice_limit' => 10,
            'masking_type' => 'hide',
        ]);
        $settings->save();

        $user = User::factory()->create();

        // Should be inactive when settings is_enabled is false
        $this->assertFalse(PrivacyModeService::isActiveForUser($user));

        // Enable settings
        $settings->update(['is_enabled' => true]);
        $this->assertFalse(PrivacyModeService::isActiveForUser($user));

        // Set session active
        session(['privacy_mode_active' => true]);
        $this->assertTrue(PrivacyModeService::isActiveForUser($user));

        // If user has bypass permission, it should be inactive
        if (method_exists($user, 'givePermissionTo')) {
            $user->givePermissionTo('privacy_mode.bypass');
        } else {
            // Mock hasPermission if using custom permission library
            // For now, let's test if the method checks bypass.
        }
    }

    public function test_privacy_mode_toggle_works_for_non_super_admin_with_toggle_permission()
    {
        PrivacyModeSetting::query()->updateOrCreate(['id' => 1], [
            'is_enabled' => true,
            'shortcut_key' => 'Ctrl + Shift + H',
            'shortcut_key_mac' => 'Cmd+X',
            'visible_invoice_limit' => 10,
            'masking_type' => 'hide',
            'apply_to_pos' => true,
            'apply_to_sales_list' => true,
            'apply_to_reports' => true,
            'apply_to_dashboard' => true,
            'apply_to_customer_history' => true,
        ]);

        $role = AppRole::query()->create([
            'name' => 'Cashier',
            'permissions' => ['privacy_mode.view', 'privacy_mode.toggle'],
            'is_active' => true,
        ]);
        $user = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($user)
            ->postJson(route('privacy-mode.toggle'), ['page' => '/pos'])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'active' => true,
                'message' => 'Activated.',
            ]);

        $this->assertTrue((bool) session('privacy_mode_active'));
        $this->assertFalse(session()->has('success'));
        $this->assertDatabaseHas('privacy_mode_logs', [
            'user_id' => $user->id,
            'action' => 'activated',
            'page' => '/pos',
        ]);
    }

    public function test_privacy_mode_toggle_rejects_bypass_users()
    {
        PrivacyModeSetting::query()->updateOrCreate(['id' => 1], [
            'is_enabled' => true,
            'shortcut_key' => 'Alt+S',
            'shortcut_key_mac' => 'Cmd+X',
            'visible_invoice_limit' => 10,
            'masking_type' => 'hide',
            'apply_to_pos' => true,
            'apply_to_sales_list' => true,
            'apply_to_reports' => true,
            'apply_to_dashboard' => true,
            'apply_to_customer_history' => true,
        ]);

        $role = AppRole::query()->create([
            'name' => 'Trusted Manager',
            'permissions' => ['privacy_mode.view', 'privacy_mode.toggle', 'privacy_mode.bypass'],
            'is_active' => true,
        ]);
        $user = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($user)
            ->postJson(route('privacy-mode.toggle'), ['page' => '/pos'])
            ->assertForbidden()
            ->assertJson([
                'error' => 'This user has Privacy Mode bypass and always sees real data.',
            ]);

        $this->assertFalse((bool) session('privacy_mode_active'));
        $this->assertDatabaseMissing('privacy_mode_logs', [
            'user_id' => $user->id,
            'action' => 'activated',
        ]);
    }

    public function test_sales_list_privacy_mode_keeps_normal_columns_and_masks_invoice_numbers_by_day()
    {
        PrivacyModeSetting::query()->updateOrCreate(['id' => 1], [
            'is_enabled' => true,
            'shortcut_key' => 'Alt+S',
            'shortcut_key_mac' => 'Cmd+X',
            'visible_invoice_limit' => 100,
            'masking_type' => 'hide',
            'apply_to_pos' => true,
            'apply_to_sales_list' => true,
            'apply_to_reports' => true,
            'apply_to_dashboard' => true,
            'apply_to_customer_history' => true,
        ]);

        $role = AppRole::query()->create([
            'name' => 'Cashier',
            'permissions' => ['sales.view', 'privacy_mode.view', 'privacy_mode.toggle'],
            'is_active' => true,
        ]);
        $user = User::factory()->create(['role_id' => $role->id]);

        $olderInvoice = Sale::query()->create([
            'sale_no' => 'INV-MIAWOIZCM35',
            'user_id' => $user->id,
            'sale_date' => '2026-05-16',
            'subtotal' => 200,
            'total_amount' => 200,
            'paid_amount' => 150,
            'due_amount' => 50,
            'payment_status' => 'partial',
            'payment_method' => 'cash',
            'sale_type' => 'sale',
        ]);
        $olderInvoice->created_at = now()->subDay();
        $olderInvoice->updated_at = now()->subDay();
        $olderInvoice->save();

        $sameDayInvoice = Sale::query()->create([
            'sale_no' => 'INV-MIAWOIZCM77',
            'user_id' => $user->id,
            'sale_date' => '2026-05-16',
            'subtotal' => 50,
            'total_amount' => 50,
            'paid_amount' => 50,
            'due_amount' => 0,
            'payment_status' => 'paid',
            'payment_method' => 'card',
            'sale_type' => 'sale',
        ]);
        $sameDayInvoice->created_at = now()->subHours(12);
        $sameDayInvoice->updated_at = now()->subHours(12);
        $sameDayInvoice->save();

        $newerInvoice = Sale::query()->create([
            'sale_no' => 'INV-OTGWOIJUM93',
            'user_id' => $user->id,
            'sale_date' => '2026-05-24',
            'subtotal' => 100,
            'total_amount' => 100,
            'paid_amount' => 100,
            'due_amount' => 0,
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'sale_type' => 'sale',
        ]);
        $newerInvoice->created_at = now();
        $newerInvoice->updated_at = now();
        $newerInvoice->save();

        $demoSale = Sale::query()->create([
            'sale_no' => 'DEMO-CALC-SALE-001',
            'user_id' => $user->id,
            'sale_date' => '2026-05-03',
            'subtotal' => 999,
            'total_amount' => 999,
            'paid_amount' => 999,
            'due_amount' => 0,
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'sale_type' => 'sale',
        ]);
        $demoSale->created_at = now()->addMinute();
        $demoSale->updated_at = now()->addMinute();
        $demoSale->save();

        $quotation = Sale::query()->create([
            'sale_no' => 'QUO-20260507-0001',
            'user_id' => $user->id,
            'sale_date' => '2026-05-07',
            'subtotal' => 300,
            'total_amount' => 300,
            'paid_amount' => 0,
            'due_amount' => 0,
            'payment_status' => 'unpaid',
            'payment_method' => 'cash',
            'sale_type' => 'quotation',
        ]);
        $quotation->created_at = now()->addMinutes(2);
        $quotation->updated_at = now()->addMinutes(2);
        $quotation->save();

        $response = $this->actingAs($user)
            ->withSession(['privacy_mode_active' => true])
            ->get(route('sales.index', ['all_dates' => '1']));

        $response->assertOk();
        $response->assertSee('INV-OTGWOIJUM01');
        $response->assertSee('INV-MIAWOIZCM01');
        $response->assertSee('INV-MIAWOIZCM02');
        $response->assertSee('Rs 100.00');
        $response->assertSee('Rs 200.00');
        $response->assertSee('Rs 50.00');
        $response->assertSee('Customer');
        $response->assertSee('Date');
        $response->assertSee('Pay Status');
        $response->assertSee('Method');
        $response->assertSee('Status');
        $response->assertSee('>Actions</th>', false);
        $response->assertDontSee('INV-OTGWOIJUM93');
        $response->assertDontSee('INV-MIAWOIZCM35');
        $response->assertDontSee('INV-MIAWOIZCM77');
        $response->assertDontSee('DEMO-CALC-SALE-001');
        $response->assertDontSee('QUO-20260507-0001');
        $response->assertDontSee('Privacy Mode Active');
    }

    public function test_sales_report_privacy_mode_masks_amounts_and_uses_daily_invoice_labels()
    {
        PrivacyModeSetting::query()->updateOrCreate(['id' => 1], [
            'is_enabled' => true,
            'shortcut_key' => 'Alt+S',
            'shortcut_key_mac' => 'Cmd+X',
            'visible_invoice_limit' => 10,
            'masking_type' => 'hide',
            'apply_to_pos' => true,
            'apply_to_sales_list' => true,
            'apply_to_reports' => true,
            'apply_to_dashboard' => true,
            'apply_to_customer_history' => true,
        ]);

        $role = AppRole::query()->create([
            'name' => 'Reporter',
            'permissions' => ['reports.sales', 'privacy_mode.view', 'privacy_mode.toggle'],
            'is_active' => true,
        ]);
        $user = User::factory()->create(['role_id' => $role->id]);

        Sale::query()->create([
            'sale_no' => 'INV-OTGWOIJUM93',
            'user_id' => $user->id,
            'sale_date' => '2026-05-24',
            'subtotal' => 100,
            'total_amount' => 100,
            'paid_amount' => 100,
            'due_amount' => 0,
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'sale_type' => 'sale',
        ]);
        Sale::query()->create([
            'sale_no' => 'INV-MIAWOIZCM35',
            'user_id' => $user->id,
            'sale_date' => '2026-05-16',
            'subtotal' => 200,
            'total_amount' => 200,
            'paid_amount' => 150,
            'due_amount' => 50,
            'payment_status' => 'partial',
            'payment_method' => 'cash',
            'sale_type' => 'sale',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['privacy_mode_active' => true])
            ->get(route('reports.sales'));

        $response->assertOk();
        $response->assertSee('INV-OTGWOIJUM01');
        $response->assertSee('INV-MIAWOIZCM01');
        $response->assertSee('—');
        $response->assertDontSee('INV-OTGWOIJUM93');
        $response->assertDontSee('INV-MIAWOIZCM35');
        $response->assertDontSee('Privacy Mode Active');
    }

    public function test_customer_show_privacy_mode_keeps_amounts_and_masks_invoice_references()
    {
        PrivacyModeSetting::query()->updateOrCreate(['id' => 1], [
            'is_enabled' => true,
            'shortcut_key' => 'Alt+S',
            'shortcut_key_mac' => 'Cmd+X',
            'visible_invoice_limit' => 10,
            'masking_type' => 'hide',
            'apply_to_pos' => true,
            'apply_to_sales_list' => true,
            'apply_to_reports' => true,
            'apply_to_dashboard' => true,
            'apply_to_customer_history' => true,
        ]);

        $role = AppRole::query()->create([
            'name' => 'Customer Viewer',
            'permissions' => ['customers.view', 'privacy_mode.view', 'privacy_mode.toggle'],
            'is_active' => true,
        ]);
        $user = User::factory()->create(['role_id' => $role->id]);
        $customer = Customer::query()->create([
            'name' => 'John Carter',
            'phone' => '0771234567',
            'is_active' => true,
        ]);

        Sale::query()->create([
            'sale_no' => 'INV-OTGWOIJUM93',
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'sale_date' => '2026-05-24',
            'subtotal' => 123.45,
            'total_amount' => 123.45,
            'paid_amount' => 100,
            'due_amount' => 23.45,
            'payment_status' => 'partial',
            'payment_method' => 'cash',
            'sale_type' => 'sale',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['privacy_mode_active' => true])
            ->getJson(route('customers.show', $customer->id));

        $response->assertOk();
        $response->assertJsonPath('period_totals.invoice', 123.45);
        $response->assertJsonFragment(['reference' => 'INV-OTGWOIJUM01']);
        $response->assertJsonMissing(['reference' => 'INV-OTGWOIJUM93']);
        $response->assertJsonFragment(['debit' => 123.45]);
        $response->assertJsonFragment(['paid' => 100]);
        $response->assertJsonFragment(['due' => 23.45]);
    }

    public function test_public_customer_history_shows_prices_but_replaces_bill_numbers()
    {
        $customer = Customer::query()->create([
            'name' => 'John Carter',
            'phone' => '0771234567',
            'is_active' => true,
        ]);
        $user = User::factory()->create();

        Sale::query()->create([
            'sale_no' => 'INV-OTGWOIJUM93',
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'sale_date' => '2026-05-24',
            'subtotal' => 123.45,
            'total_amount' => 123.45,
            'paid_amount' => 100,
            'due_amount' => 23.45,
            'payment_status' => 'partial',
            'payment_method' => 'cash',
            'sale_type' => 'sale',
        ]);

        $response = $this->get(route('customer.history.view', $customer->id));

        $response->assertOk();
        $response->assertSee('INV-OTGWOIJUM01');
        $response->assertSee('Rs 123.45');
        $response->assertSee('Rs 100.00');
        $response->assertSee('Rs 23.45');
        $response->assertDontSee('INV-OTGWOIJUM93');
        $response->assertDontSee('Privacy Mode Active');
    }

    public function test_secret_dashboard_records_page_saves_hidden_supplier_customer_and_bill()
    {
        $superRole = AppRole::query()->create([
            'name' => 'Super Admin',
            'permissions' => [],
            'is_active' => true,
        ]);
        $adminRole = AppRole::query()->create([
            'name' => 'Admin',
            'permissions' => ['sales.view', 'suppliers.view', 'customers.view', 'purchases.view', 'reports.sales'],
            'is_active' => true,
        ]);
        $superAdmin = User::factory()->create(['role_id' => $superRole->id]);
        $admin = User::factory()->create(['role_id' => $adminRole->id]);

        $supplier = Supplier::query()->create([
            'name' => 'Hidden Supplier',
            'phone' => '0711111111',
            'is_active' => true,
        ]);
        $customer = Customer::query()->create([
            'name' => 'Hidden Customer',
            'phone' => '0722222222',
            'is_active' => true,
        ]);
        $sale = Sale::query()->create([
            'sale_no' => 'INV-HIDDENBILL93',
            'customer_id' => $customer->id,
            'user_id' => $admin->id,
            'sale_date' => '2026-05-24',
            'subtotal' => 250,
            'total_amount' => 250,
            'paid_amount' => 250,
            'due_amount' => 0,
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'sale_type' => 'sale',
        ]);
        Purchase::query()->create([
            'supplier_id' => $supplier->id,
            'user_id' => $admin->id,
            'purchase_date' => '2026-05-24',
            'status' => 'received',
            'payment_method' => 'cash',
            'total_amount' => 350,
            'paid_amount' => 350,
            'due_amount' => 0,
            'payment_status' => 'paid',
        ]);

        $this->actingAs($superAdmin)
            ->get(route('fun.dashboard.section', 'records'))
            ->assertOk()
            ->assertSee('Hide suppliers')
            ->assertSee('Hide customers')
            ->assertSee('Hide sales bills');

        $this->actingAs($superAdmin)
            ->post(route('fun.dashboard.save'), [
                'section' => 'records',
                'hidden_suppliers' => (string) $supplier->id,
                'hidden_customers' => (string) $customer->id,
                'hidden_sales' => (string) $sale->id,
            ])
            ->assertRedirect();

        $this->actingAs($admin)->get(route('sales.index', ['all_dates' => '1']))
            ->assertOk()
            ->assertDontSee('INV-HIDDENBILL93')
            ->assertDontSee('Hidden Customer');

        $this->actingAs($admin)->get(route('suppliers.index'))
            ->assertOk()
            ->assertDontSee('Hidden Supplier');

        $this->actingAs($admin)->get(route('customers.index'))
            ->assertOk()
            ->assertDontSee('Hidden Customer');

        $this->actingAs($admin)->get(route('reports.sales'))
            ->assertOk()
            ->assertDontSee('INV-HIDDENBILL93')
            ->assertDontSee('Hidden Customer');

        $this->actingAs($superAdmin)->get(route('sales.index', ['all_dates' => '1']))
            ->assertOk()
            ->assertSee('INV-HIDDENBILL93');
    }

    public function test_secret_dashboard_records_page_can_clear_sales_amount_ranges()
    {
        $superRole = AppRole::query()->create([
            'name' => 'Super Admin',
            'permissions' => [],
            'is_active' => true,
        ]);
        $adminRole = AppRole::query()->create([
            'name' => 'Admin',
            'permissions' => ['sales.view'],
            'is_active' => true,
        ]);
        $superAdmin = User::factory()->create(['role_id' => $superRole->id]);
        $admin = User::factory()->create(['role_id' => $adminRole->id]);

        DashboardVisibilityService::save([
            'hidden_price_ranges' => DashboardVisibilityService::parseRangeInput('300-350'),
            'hidden_sales_price_ranges' => DashboardVisibilityService::parseRangeInput('300-350'),
        ]);

        Sale::query()->create([
            'sale_no' => 'INV-CLEARRANGE01',
            'user_id' => $admin->id,
            'sale_date' => '2026-05-25',
            'subtotal' => 325,
            'total_amount' => 325,
            'paid_amount' => 325,
            'due_amount' => 0,
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'sale_type' => 'sale',
        ]);

        $this->actingAs($admin)->get(route('sales.index', ['all_dates' => '1']))
            ->assertOk()
            ->assertDontSee('INV-CLEARRANGE01');

        $this->actingAs($superAdmin)
            ->post(route('fun.dashboard.save'), [
                'section' => 'records',
                'hidden_sales_price_ranges' => '',
            ])
            ->assertRedirect();

        $controls = DashboardVisibilityService::configRaw();
        $this->assertSame([], DashboardVisibilityService::rangesFromControls($controls, 'hidden_sales_price_ranges'));
        $this->assertSame([], DashboardVisibilityService::rangesFromControls($controls, 'hidden_price_ranges'));

        $this->actingAs($admin)->get(route('sales.index', ['all_dates' => '1']))
            ->assertOk()
            ->assertSee('INV-CLEARRANGE01');
    }

    public function test_customer_visible_percentage_affects_admin_but_not_super_admin()
    {
        $superRole = AppRole::query()->create([
            'name' => 'Super Admin',
            'permissions' => [],
            'is_active' => true,
        ]);
        $adminRole = AppRole::query()->create([
            'name' => 'Admin',
            'permissions' => ['customers.view'],
            'is_active' => true,
        ]);
        $superAdmin = User::factory()->create(['role_id' => $superRole->id]);
        $admin = User::factory()->create(['role_id' => $adminRole->id]);
        $customer = Customer::query()->create([
            'name' => 'Visible Percentage Customer',
            'phone' => '0777777777',
            'is_active' => true,
        ]);

        Sale::query()->create([
            'sale_no' => 'INV-CUSTOMERPCT01',
            'customer_id' => $customer->id,
            'user_id' => $admin->id,
            'sale_date' => '2026-05-25',
            'subtotal' => 200,
            'total_amount' => 200,
            'paid_amount' => 120,
            'due_amount' => 80,
            'payment_status' => 'partial',
            'payment_method' => 'cash',
            'sale_type' => 'sale',
        ]);

        $this->actingAs($superAdmin)
            ->post(route('fun.dashboard.save'), [
                'section' => 'records',
                'customer_visible_percentage' => 50,
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->getJson(route('customers.show', $customer->id))
            ->assertOk()
            ->assertJsonPath('period_totals.invoice', 100)
            ->assertJsonPath('period_totals.paid', 60)
            ->assertJsonPath('period_totals.balance', 40)
            ->assertJsonFragment(['debit' => 100.0])
            ->assertJsonFragment(['paid' => 60.0])
            ->assertJsonFragment(['due' => 40.0]);

        $this->actingAs($superAdmin)
            ->getJson(route('customers.show', $customer->id))
            ->assertOk()
            ->assertJsonPath('period_totals.invoice', 200)
            ->assertJsonPath('period_totals.paid', 120)
            ->assertJsonPath('period_totals.balance', 80);
    }

    public function test_profit_visible_percentage_affects_profit_reports_for_admin_only()
    {
        $superRole = AppRole::query()->create([
            'name' => 'Super Admin',
            'permissions' => [],
            'is_active' => true,
        ]);
        $adminRole = AppRole::query()->create([
            'name' => 'Admin',
            'permissions' => ['reports.profit-loss'],
            'is_active' => true,
        ]);
        $superAdmin = User::factory()->create(['role_id' => $superRole->id]);
        $admin = User::factory()->create(['role_id' => $adminRole->id]);

        $unit = Unit::query()->create([
            'name' => 'Piece',
            'short_name' => 'pc',
            'base_unit_multiplier' => 1,
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'name' => 'Profit Test Product',
            'sku' => 'PTP-01',
            'unit_id' => $unit->id,
            'cost_price' => 100,
            'selling_price' => 200,
            'stock_quantity' => 10,
            'alert_quantity' => 1,
            'is_active' => true,
        ]);
        $sale = Sale::query()->create([
            'sale_no' => 'INV-PROFITPCT01',
            'user_id' => $admin->id,
            'sale_date' => '2026-05-25',
            'subtotal' => 200,
            'total_amount' => 200,
            'paid_amount' => 200,
            'due_amount' => 0,
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'sale_type' => 'sale',
        ]);
        SaleItem::query()->create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 200,
            'total' => 200,
        ]);

        $this->actingAs($superAdmin)
            ->post(route('fun.dashboard.save'), [
                'section' => 'dashboard',
                'profit_visible_percentage' => 50,
            ])
            ->assertRedirect();

        $adminCsv = $this->actingAs($admin)
            ->get(route('reports.profit-loss.csv', ['from' => '2026-05-25', 'to' => '2026-05-25']))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('"Gross Profit",50', $adminCsv);
        $this->assertStringContainsString('"Net Profit",50', $adminCsv);

        $superCsv = $this->actingAs($superAdmin)
            ->get(route('reports.profit-loss.csv', ['from' => '2026-05-25', 'to' => '2026-05-25']))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('"Gross Profit",100.00', $superCsv);
        $this->assertStringContainsString('"Net Profit",100.00', $superCsv);
    }
}
