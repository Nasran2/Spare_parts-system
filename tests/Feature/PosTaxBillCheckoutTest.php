<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosTaxBillCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_walk_in_customer_cannot_checkout_with_a_tax_bill(): void
    {
        $role = Role::create([
            'name' => 'Tax Cashier',
            'permissions' => ['pos.access', 'tax.invoice.print'],
            'is_active' => true,
        ]);
        $user = User::factory()->create(['role_id' => $role->id]);

        $response = $this
            ->actingAs($user)
            ->withSession([
                'pos.cart' => [
                    'items' => [
                        'test-item' => [
                            'id' => 999999,
                            'name' => 'Test Item',
                            'price' => 100,
                            'qty' => 1,
                            'tax' => [
                                'tax_status' => 'exempt',
                                'vat_rate' => 0,
                                'price_mode' => 'exclusive',
                                'vat_allowed' => false,
                            ],
                        ],
                    ],
                    'discount' => ['type' => 'fixed', 'value' => 0],
                    'tax_rate' => 0,
                ],
            ])
            ->postJson(route('pos.checkout'), [
                'bill_type' => 'tax',
                'customer_id' => null,
                'paid_amount' => 100,
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Select a customer first to generate a Tax Bill.');
    }
}
