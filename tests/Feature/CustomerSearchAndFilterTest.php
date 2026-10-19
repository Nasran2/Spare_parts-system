<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerSearchAndFilterTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_customer_index_renders_search_and_filter_elements(): void
    {
        $this->withoutMiddleware();

        $response = $this->actingAs($this->user)->get(route('customers.index'));

        $response->assertOk();
        $response->assertSee('Search Customers');
        $response->assertSee('customerSearchInput', false);
        $response->assertSee('statusFilterSelect', false);
        $response->assertSee('balanceFilterSelect', false);
        $response->assertSee('sortFilterSelect', false);
        $response->assertSee('Total Customers');
    }

    public function test_search_by_name_and_phone(): void
    {
        $this->withoutMiddleware();

        Customer::create(['name' => 'Alice Walker', 'phone' => '0771234567', 'is_active' => true]);
        Customer::create(['name' => 'Bob Marley', 'phone' => '0719876543', 'is_active' => true]);

        // Search for Alice
        $resAlice = $this->actingAs($this->user)->get(route('customers.index', ['search' => 'Alice']));
        $resAlice->assertOk();
        $resAlice->assertSee('Alice Walker');
        $resAlice->assertDontSee('Bob Marley');

        // Search for Bob by phone
        $resBob = $this->actingAs($this->user)->get(route('customers.index', ['search' => '0719876543']));
        $resBob->assertOk();
        $resBob->assertSee('Bob Marley');
        $resBob->assertDontSee('Alice Walker');
    }

    public function test_filter_by_status(): void
    {
        $this->withoutMiddleware();

        Customer::create(['name' => 'Active Person', 'phone' => '0770000001', 'is_active' => true]);
        Customer::create(['name' => 'Inactive Person', 'phone' => '0770000002', 'is_active' => false]);

        $resActive = $this->actingAs($this->user)->get(route('customers.index', ['status' => 'active']));
        $resActive->assertOk();
        $resActive->assertSee('Active Person');
        $resActive->assertDontSee('Inactive Person');

        $resInactive = $this->actingAs($this->user)->get(route('customers.index', ['status' => 'inactive']));
        $resInactive->assertOk();
        $resInactive->assertSee('Inactive Person');
        $resInactive->assertDontSee('Active Person');
    }

    public function test_filter_by_balance_due(): void
    {
        $this->withoutMiddleware();

        $c1 = Customer::create(['name' => 'Customer With Due', 'phone' => '0770000003', 'is_active' => true]);
        $c2 = Customer::create(['name' => 'Customer Clean', 'phone' => '0770000004', 'is_active' => true]);

        // Create a sale with due for c1
        Sale::create([
            'sale_no' => 'INV-TEST-001',
            'customer_id' => $c1->id,
            'user_id' => $this->user->id,
            'subtotal' => 1000,
            'total_amount' => 1000,
            'paid_amount' => 200,
            'due_amount' => 800,
            'sale_type' => 'sale',
            'payment_status' => 'partial',
            'sale_date' => now()->toDateString(),
        ]);

        $resDue = $this->actingAs($this->user)->get(route('customers.index', ['balance_type' => 'due']));
        $resDue->assertOk();
        $resDue->assertSee('Customer With Due');
        $resDue->assertDontSee('Customer Clean');

        $resZero = $this->actingAs($this->user)->get(route('customers.index', ['balance_type' => 'zero']));
        $resZero->assertOk();
        $resZero->assertSee('Customer Clean');
        $resZero->assertDontSee('Customer With Due');
    }

    public function test_sort_by_name(): void
    {
        $this->withoutMiddleware();

        Customer::create(['name' => 'Zachary', 'phone' => '0770000005', 'is_active' => true]);
        Customer::create(['name' => 'Aaron', 'phone' => '0770000006', 'is_active' => true]);

        $resAsc = $this->actingAs($this->user)->get(route('customers.index', ['sort_by' => 'name_asc']));
        $resAsc->assertOk();
        $this->assertStringStartsBefore(
            'Aaron',
            'Zachary',
            $resAsc->getContent()
        );

        $resDesc = $this->actingAs($this->user)->get(route('customers.index', ['sort_by' => 'name_desc']));
        $resDesc->assertOk();
        $this->assertStringStartsBefore(
            'Zachary',
            'Aaron',
            $resDesc->getContent()
        );
    }

    private function assertStringStartsBefore(string $first, string $second, string $subject): void
    {
        $posFirst = strpos($subject, $first);
        $posSecond = strpos($subject, $second);

        $this->assertNotFalse($posFirst, "Could not find '{$first}'");
        $this->assertNotFalse($posSecond, "Could not find '{$second}'");
        $this->assertTrue($posFirst < $posSecond, "Expected '{$first}' to appear before '{$second}'");
    }
}
