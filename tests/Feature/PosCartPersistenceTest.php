<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Store;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosCartPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_cart_and_bill_selections_survive_reload_and_visiting_another_page(): void
    {
        $role = Role::create(['name' => 'Admin', 'permissions' => [], 'is_active' => true]);
        $this->actingAs(User::factory()->create(['role_id' => $role->id]));
        Setting::set('use_price_wise_stock', false, 'boolean');
        $unit = Unit::create(['name' => 'Piece', 'short_name' => 'pc', 'is_active' => true]);
        $product = Product::create(['name' => 'Saved Part', 'sku' => 'SAVED', 'unit_id' => $unit->id, 'selling_price' => 100, 'cost_price' => 50, 'stock_quantity' => 10, 'is_active' => true]);
        $customer = Customer::create(['name' => 'Bill Customer', 'phone' => '0771234567']);
        $store = Store::create(['name' => 'Main', 'code' => 'MAIN', 'is_active' => true]);
        $added = $this->postJson(route('pos.cart.add'), ['product_id' => $product->id, 'quantity' => 2])->assertOk();
        $this->postJson(route('pos.cart.context'), ['customer_id' => $customer->id, 'store_id' => $store->id])->assertOk();
        $discounted = $this->postJson(route('pos.cart.discount'), ['type' => 'fixed', 'value' => 10])->assertOk();
        $items = $discounted->json('items');
        $this->get(route('pos.index'))->assertOk();
        $this->get(route('customers.index'))->assertOk();
        $this->get(route('pos.index'))->assertOk();
        $current = $this->getJson(route('pos.cart.current'))->assertOk();
        $this->assertSame($items, $current->json('items'));
        $current->assertJsonPath('context.customer_id', $customer->id)->assertJsonPath('context.store_id', $store->id)->assertJsonPath('discount.value', 10);
        $this->assertSame('/pos/cart/clear', parse_url(route('pos.cart.clear'), PHP_URL_PATH));
        $this->assertSame('/pos/cancel-edit', parse_url(route('pos.cancel_edit'), PHP_URL_PATH));
        $this->postJson(route('pos.cart.clear'))->assertOk()->assertJsonPath('items', []);
        $this->getJson(route('pos.cart.current'))->assertOk()->assertJsonPath('items', []);
    }
}
