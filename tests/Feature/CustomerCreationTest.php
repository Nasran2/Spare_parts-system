<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_be_created_with_name_only(): void
    {
        $this->withoutMiddleware();

        $response = $this->postJson(route('customers.store'), [
            'name' => 'Nasran',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'customer' => [
                    'name' => 'Nasran',
                    'phone' => '',
                ],
            ]);

        $customer = Customer::query()->firstOrFail();

        $this->assertSame('Nasran', $customer->name);
        $this->assertSame('', $customer->phone);
        $this->assertNull($customer->email);
        $this->assertNull($customer->address);
    }
}
