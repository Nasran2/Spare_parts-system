<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CustomerLedgerOpeningBalanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $role = Role::create(['name' => 'Admin', 'permissions' => [], 'is_active' => true]);
        $this->actingAs(User::factory()->create(['role_id' => $role->id]));
    }

    #[DataProvider('reportTypes')]
    public function test_opening_balance_is_labelled_separately_and_zero_is_omitted(string $type, float $opening): void
    {
        $customer = Customer::create(['name' => 'Ledger Customer', 'phone' => '0771234567', 'opening_balance' => $opening]);
        $this->renderPdfAsHtml(function (array $data) use ($opening) {
            $this->assertEquals($opening, $data['overallTotals']['opening_balance']);
            $this->assertEquals(0, $data['overallTotals']['sales_due']);
            $this->assertEquals($opening, $data['overallTotals']['balance']);
        });

        $response = $this->get(route('customers.export-pdf', ['customer' => $customer->id, 'type' => $type]))->assertOk();
        if ($opening > 0) {
            $response->assertSee('Opening Balance:');
        } else {
            $response->assertDontSee('Opening Balance:');
        }
        $response->assertDontSee('Sales Due:');
    }

    public static function reportTypes(): array
    {
        return [
            'ledger with opening debt' => ['ledger', 100000.0],
            'ledger without opening debt' => ['ledger', 0.0],
            'payments with opening debt' => ['payments', 100000.0],
            'payments without opening debt' => ['payments', 0.0],
        ];
    }

    public function test_invoice_debt_and_opening_payments_are_not_mixed_in_the_summary(): void
    {
        $customer = Customer::create(['name' => 'Ledger Customer', 'phone' => '0771234567', 'opening_balance' => 500]);
        Sale::create([
            'sale_no' => 'INV-LEDGER', 'customer_id' => $customer->id, 'user_id' => auth()->id(),
            'sale_date' => '2026-10-10', 'subtotal' => 1000, 'total_amount' => 1000,
            'paid_amount' => 200, 'due_amount' => 800, 'sale_type' => 'sale', 'payment_status' => 'partial',
        ]);
        Payment::create(['customer_id' => $customer->id, 'amount' => 100, 'payment_date' => '2026-10-11', 'payment_method' => 'cash']);
        Payment::create(['customer_id' => $customer->id, 'amount' => 50, 'payment_date' => '2026-11-01', 'payment_method' => 'cash']);

        $this->renderPdfAsHtml(function (array $data) {
            $this->assertEquals(500, $data['overallTotals']['opening_balance']);
            $this->assertEquals(800, $data['overallTotals']['sales_due']);
            $this->assertEquals(300, $data['overallTotals']['paid']);
            $this->assertEquals(1200, $data['overallTotals']['balance']);
        });

        $this->get(route('customers.export-pdf', ['customer' => $customer->id, 'type' => 'ledger', 'start_date' => '2026-10-01', 'end_date' => '2026-10-31']))
            ->assertOk()->assertSee('Opening Balance:')->assertSee('Sales Due:');
    }

    private function renderPdfAsHtml(callable $check): void
    {
        Pdf::shouldReceive('loadView')->once()->andReturnUsing(function (string $view, array $data) use ($check) {
            $check($data);
            $pdf = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
            $pdf->shouldReceive('download')->once()->andReturn(response(view($view, $data)->render()));

            return $pdf;
        });
    }
}
