<?php

namespace Tests\Feature;

use App\Models\Accounting\ChartAccount;
use App\Models\Customer;
use App\Models\PreOrder;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Unit;
use App\Models\User;
use App\Services\PreOrderImageService;
use App\Services\PreOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PreOrderManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_01_create_preorder_with_product_in_stock(): void
    {
        [$user, $customer, $store] = $this->context();
        [$product, $price] = $this->product($store, 10, 'Brake Pad');
        $order = $this->order($user, $customer, $store, $product, $price);

        $this->assertSame('pending', $order->status);
        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    public function test_02_create_preorder_with_zero_stock_product_succeeds(): void
    {
        [$user, $customer, $store] = $this->context();
        [$product, $price] = $this->product($store, 0, 'Zero Stock Part');
        $order = $this->order($user, $customer, $store, $product, $price);

        $this->assertSame('linked', $order->items->first()->sync_status);
        $this->assertSame(0.0, app(PreOrderService::class)->currentStock($product, $store->id, $price->id));
    }

    public function test_03_create_temporary_unlinked_product_succeeds(): void
    {
        [$user, $customer, $store] = $this->context();
        $order = $this->order($user, $customer, $store, null, null, 250, 'Toyota Axio Front Brake Pad');

        $this->assertNull($order->items->first()->product_id);
        $this->assertSame('unlinked', $order->items->first()->sync_status);
    }

    public function test_04_pdf_omits_empty_delivery_date(): void
    {
        [$user, $customer, $store] = $this->context();
        [$product, $price] = $this->product($store, 5);
        $order = $this->order($user, $customer, $store, $product, $price);
        $html = $this->pdfHtml($order);

        $this->assertStringNotContainsString('Expected Delivery:', $html);
    }

    public function test_05_quotation_contains_vehicle_image(): void
    {
        [$user, $customer, $store] = $this->context();
        [$product, $price] = $this->product($store, 5);
        $image = app(PreOrderImageService::class)->store(UploadedFile::fake()->image('vehicle.jpg', 320, 200));
        $order = $this->order($user, $customer, $store, $product, $price, 100, 'Part', ['vehicle_image' => $image]);

        $this->assertStringContainsString($order->vehicle_image_absolute_path, $this->pdfHtml($order));
        app(PreOrderImageService::class)->delete($image);
    }

    public function test_06_temporary_item_can_be_manually_synced_after_stock_arrives(): void
    {
        [$user, $customer, $store] = $this->context();
        $order = $this->order($user, $customer, $store, null, null, 250, 'Toyota Axio Front Brake Pad');
        [$product, $price] = $this->product($store, 4, 'Brake Pad Toyota Axio 2014-2018');
        app(PreOrderService::class)->syncProduct($order, $order->items->first(), $product, $price->id, $user->id);

        $item = $order->fresh('items')->items->first();
        $this->assertSame($product->id, $item->product_id);
        $this->assertSame('Toyota Axio Front Brake Pad', $item->original_product_name);
    }

    public function test_07_complete_with_full_cash_payment(): void
    {
        [$user, $customer, $store] = $this->context();
        [$product, $price] = $this->product($store, 5);
        $order = $this->order($user, $customer, $store, $product, $price, 500);
        $order = app(PreOrderService::class)->complete($order, [['method' => 'cash', 'amount' => 500]], $user->id);

        $this->assertSame('completed', $order->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame(4, $product->fresh()->stock_quantity);
    }

    public function test_08_complete_with_cash_bank_and_cheque(): void
    {
        [$user, $customer, $store] = $this->context();
        [$product, $price] = $this->product($store, 5);
        $order = $this->order($user, $customer, $store, $product, $price, 500);
        $order = app(PreOrderService::class)->complete($order, [
            ['method' => 'cash', 'amount' => 100],
            ['method' => 'bank_transfer', 'amount' => 100, 'reference' => 'BANK-1'],
            ['method' => 'cheque', 'amount' => 150, 'cheque_number' => 'CH-1', 'cheque_date' => now()->addDay()->toDateString(), 'bank_name' => 'Test Bank'],
        ], $user->id);

        $this->assertCount(2, $order->sale->payments);
        $this->assertCount(1, $order->sale->chequePayments);
        $this->assertSame(150.0, (float) $order->due_amount);
    }

    public function test_09_complete_with_partial_payment_and_due(): void
    {
        [$user, $customer, $store] = $this->context();
        [$product, $price] = $this->product($store, 5);
        $order = $this->order($user, $customer, $store, $product, $price, 500);
        $order = app(PreOrderService::class)->complete($order, [['method' => 'cash', 'amount' => 200]], $user->id);

        $this->assertSame('partial', $order->payment_status);
        $this->assertSame(300.0, (float) $order->due_amount);
    }

    public function test_10_receive_another_payment_later_updates_due_and_history(): void
    {
        [$user, $customer, $store] = $this->context();
        [$product, $price] = $this->product($store, 5);
        $order = app(PreOrderService::class)->complete($this->order($user, $customer, $store, $product, $price, 500), [['method' => 'cash', 'amount' => 200]], $user->id);
        $order = app(PreOrderService::class)->addPayment($order, ['amount' => 300, 'payment_method' => 'bank_deposit', 'payment_date' => now()->toDateString(), 'reference_no' => 'DEP-2'], $user->id);

        $this->assertSame('paid', $order->payment_status);
        $this->assertSame(0.0, (float) $order->due_amount);
        $this->assertCount(2, $order->sale->payments);
    }

    public function test_11_cancel_pending_order_records_confirmation_metadata(): void
    {
        [$user, $customer, $store] = $this->context();
        [$product, $price] = $this->product($store, 2);
        $order = app(PreOrderService::class)->cancel($this->order($user, $customer, $store, $product, $price), 'Customer declined', $user->id);

        $this->assertSame('cancelled', $order->status);
        $this->assertNotNull($order->cancelled_at);
        $this->assertSame('Customer declined', $order->cancellation_reason);
    }

    public function test_12_reopen_cancelled_order(): void
    {
        [$user, $customer, $store] = $this->context();
        [$product, $price] = $this->product($store, 2);
        $order = app(PreOrderService::class)->cancel($this->order($user, $customer, $store, $product, $price), null, $user->id);
        $order = app(PreOrderService::class)->reopen($order, 'Cancelled by mistake', $user->id);

        $this->assertSame('pending', $order->status);
        $this->assertTrue($order->activities()->where('action', 'reopened')->exists());
    }

    public function test_13_reopen_completed_order_reverses_sale_stock_payments_tax_and_accounting(): void
    {
        [$user, $customer, $store] = $this->context();
        [$product, $price] = $this->product($store, 5);
        $order = app(PreOrderService::class)->complete($this->order($user, $customer, $store, $product, $price, 500), [['method' => 'cash', 'amount' => 500]], $user->id);
        $saleId = $order->sale_id;
        $this->assertSame(500.0, (float) ChartAccount::where('code', '1100')->value('current_balance'));
        $order = app(PreOrderService::class)->reopen($order, 'Completion mistake', $user->id);

        $this->assertSame('pending', $order->status);
        $this->assertNull($order->sale_id);
        $this->assertNull(Sale::withoutGlobalScopes()->find($saleId));
        $this->assertSame(5, $product->fresh()->stock_quantity);
        $this->assertSame(0.0, (float) ChartAccount::where('code', '1100')->value('current_balance'));
    }

    public function test_14_customer_search_shows_all_their_preorders(): void
    {
        [$user, $customer, $store] = $this->context();
        [$product, $price] = $this->product($store, 5);
        $first = $this->order($user, $customer, $store, $product, $price, 100, 'Part A');
        $second = $this->order($user, $customer, $store, $product, $price, 120, 'Part B');

        $this->actingAs($user)->get(route('preorders.index', ['customer_id' => $customer->id]))
            ->assertOk()->assertSee($first->pre_order_number)->assertSee($second->pre_order_number);
    }

    public function test_15_status_filters_return_pending_completed_and_cancelled(): void
    {
        [$user, $customer, $store] = $this->context();
        [$product, $price] = $this->product($store, 10);
        $pending = $this->order($user, $customer, $store, $product, $price, 100, 'Pending Part');
        $completed = app(PreOrderService::class)->complete($this->order($user, $customer, $store, $product, $price, 100, 'Completed Part'), [], $user->id);
        $cancelled = app(PreOrderService::class)->cancel($this->order($user, $customer, $store, $product, $price, 100, 'Cancelled Part'), null, $user->id);

        $this->actingAs($user)->get(route('preorders.status', 'pending'))->assertSee($pending->pre_order_number)->assertDontSee($completed->pre_order_number);
        $this->get(route('preorders.status', 'completed'))->assertSee($completed->pre_order_number)->assertDontSee($cancelled->pre_order_number);
        $this->get(route('preorders.status', 'cancelled'))->assertSee($cancelled->pre_order_number)->assertDontSee($pending->pre_order_number);
    }

    public function test_16_completed_preorder_appears_in_normal_sales_report(): void
    {
        [$user, $customer, $store] = $this->context();
        [$product, $price] = $this->product($store, 5);
        $order = app(PreOrderService::class)->complete($this->order($user, $customer, $store, $product, $price), [], $user->id);

        $this->actingAs($user)->get(route('reports.sales', ['from' => now()->toDateString(), 'to' => now()->toDateString()]))
            ->assertOk()->assertSee($order->sale->sale_no);
    }

    public function test_17_pending_preorder_does_not_appear_as_sale(): void
    {
        [$user, $customer, $store] = $this->context();
        [$product, $price] = $this->product($store, 5);
        $order = $this->order($user, $customer, $store, $product, $price);

        $this->assertNull($order->sale_id);
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_18_cashier_without_complete_permission_gets_403_even_by_direct_url(): void
    {
        [$admin, $customer, $store] = $this->context();
        [$product, $price] = $this->product($store, 5);
        $order = $this->order($admin, $customer, $store, $product, $price);
        $cashierRole = Role::create(['name' => 'Cashier', 'permissions' => ['preorder_view'], 'is_active' => true]);
        $cashier = User::factory()->create(['role_id' => $cashierRole->id, 'is_active' => true]);

        $this->actingAs($cashier)->post(route('preorders.complete', $order), ['payments' => []])->assertForbidden();
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_19_admin_automatically_has_all_preorder_permissions(): void
    {
        [$admin] = $this->context();

        $this->assertTrue($admin->hasPermission('preorder_complete'));
        $this->assertTrue($admin->hasPermission('future.permission.not.stored.in_json'));
    }

    public function test_20_admin_role_and_last_administrator_are_protected(): void
    {
        [$admin] = $this->context();
        $role = $admin->role;

        $this->actingAs($admin)->delete(route('roles.destroy', $role))->assertSessionHas('error');
        $this->assertDatabaseHas('roles', ['id' => $role->id, 'is_active' => true]);
        $otherRole = Role::create(['name' => 'Staff', 'permissions' => [], 'is_active' => true]);
        $this->put(route('users.update', $admin), [
            'name' => $admin->name, 'username' => 'admin', 'email' => $admin->email,
            'role_id' => $otherRole->id,
        ])->assertSessionHasErrors('is_active');
        $this->assertSame($role->id, $admin->fresh()->role_id);
    }

    public function test_21_vehicle_image_works_in_public_upload_directory_without_storage_link(): void
    {
        $service = app(PreOrderImageService::class);
        $relative = $service->store(UploadedFile::fake()->image('vehicle.png', 200, 120));

        $this->assertStringStartsWith('uploads/preorders/', $relative);
        $this->assertFileExists(public_path($relative));
        $this->assertStringNotContainsString('storage/', $relative);
        $service->delete($relative);
        $this->assertFileDoesNotExist(public_path($relative));
    }

    private function context(): array
    {
        $role = Role::create(['name' => 'Admin', 'permissions' => [], 'is_active' => true]);
        $user = User::factory()->create(['username' => 'admin', 'role_id' => $role->id, 'is_active' => true]);
        $customer = Customer::create(['name' => 'Test Customer', 'phone' => '0771234567', 'email' => 'customer@example.test', 'address' => 'Colombo', 'opening_balance' => 0, 'is_active' => true]);
        $store = Store::create(['name' => 'Main Store', 'code' => 'MAIN', 'is_default' => true, 'is_active' => true]);

        return [$user, $customer, $store];
    }

    private function product(Store $store, int $stock, string $name = 'Test Part'): array
    {
        $unit = Unit::firstOrCreate(['name' => 'Piece'], ['short_name' => 'pc', 'base_unit_multiplier' => 1, 'is_active' => true]);
        $product = Product::create(['name' => $name, 'sku' => uniqid('SKU-'), 'unit_id' => $unit->id, 'cost_price' => 50, 'selling_price' => 100, 'stock_quantity' => $stock, 'alert_quantity' => 1, 'is_active' => true]);
        $price = ProductPrice::create(['product_id' => $product->id, 'cost_price' => 50, 'selling_price' => 100, 'stock_qty' => $stock, 'is_default' => true, 'status' => 'active']);
        StoreStock::create(['store_id' => $store->id, 'product_id' => $product->id, 'product_price_id' => $price->id, 'quantity' => $stock]);

        return [$product, $price];
    }

    private function order(User $user, Customer $customer, Store $store, ?Product $product, ?ProductPrice $price, float $unitPrice = 100, string $name = 'Test Part', array $overrides = []): PreOrder
    {
        $data = array_merge([
            'customer_id' => $customer->id, 'store_id' => $store->id,
            'pre_order_date' => now()->toDateString(), 'document_type' => 'quotation',
            'vehicle_name' => 'Toyota Axio', 'registration_number' => null,
            'chassis_number' => null, 'vehicle_description' => null, 'instructions' => null,
            'notes' => null, 'expected_delivery_date' => null, 'bill_discount_type' => 'fixed',
            'bill_discount_value' => 0,
            'items' => [[
                'product_id' => $product?->id, 'product_price_id' => $price?->id,
                'original_product_name' => $name, 'description' => null, 'quantity' => 1,
                'unit_price' => $unitPrice, 'discount_type' => 'fixed', 'discount_value' => 0, 'notes' => null,
            ]],
        ], $overrides);

        return app(PreOrderService::class)->save($data, $user->id);
    }

    private function pdfHtml(PreOrder $order): string
    {
        $order->load(['customer', 'store', 'creator', 'items.product', 'sale.payments', 'sale.chequePayments']);

        return view('preorders.pdf', [
            'preOrder' => $order, 'kind' => 'quotation', 'currency' => 'Rs ',
            'shop' => ['name' => 'Vehicle POS', 'tagline' => '', 'address' => '', 'phone' => '', 'email' => '', 'logo' => null, 'terms' => 'Terms'],
        ])->render();
    }
}
