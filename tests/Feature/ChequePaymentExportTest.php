<?php

namespace Tests\Feature;

use App\Models\ChequePayment;
use App\Models\Customer;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ChequePaymentExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $role = Role::create(['name' => 'Admin', 'permissions' => [], 'is_active' => true]);
        $this->actingAs(User::factory()->create(['role_id' => $role->id]));
    }

    public function test_excel_download_contains_all_filtered_rows_and_preserves_cheque_numbers(): void
    {
        $this->cheque(['cheque_number' => '0002222', 'account_name' => '=1+1']);
        for ($i = 1; $i <= 20; $i++) {
            $this->cheque(['cheque_number' => 'FILTER-'.$i]);
        }
        $this->cheque(['cheque_number' => 'OTHER-STATUS', 'status' => 'returned']);
        $this->cheque(['cheque_number' => 'OTHER-DATE', 'cheque_date' => '2026-09-01']);
        $this->cheque(['cheque_number' => 'OTHER-BANK', 'bank_name' => 'Other Bank']);

        $filters = ['search' => 'Sample Bank', 'status' => 'pending', 'date_from' => '2026-10-01', 'date_to' => '2026-10-31'];
        $list = $this->get(route('cheque-payments.index', $filters + ['page' => 2]))->assertOk();
        $list->assertSee('href="'.e(route('cheque-payments.export.excel', $filters)).'"', false);
        $list->assertSee('href="'.e(route('cheque-payments.export.pdf', $filters)).'"', false);

        $response = $this->get(route('cheque-payments.export.excel', $filters + ['page' => 2]))->assertOk();
        $response->assertDownload('cheque-payments-'.now()->format('Y-m-d').'.xlsx');
        $sheet = IOFactory::load($response->baseResponse->getFile()->getPathname())->getActiveSheet();
        $this->assertSame(22, $sheet->getHighestDataRow());
        $this->assertSame('Cheque Number', $sheet->getCell('B1')->getValue());
        $this->assertSame('0002222', $sheet->getCell('B22')->getValue());
        $this->assertSame('=1+1', $sheet->getCell('D22')->getValue());
        $this->assertSame('s', $sheet->getCell('D22')->getDataType());
        $this->assertEquals(100000, $sheet->getCell('H22')->getValue());
        $this->assertSame('n', $sheet->getCell('H22')->getDataType());
    }

    public function test_pdf_uses_the_same_filters_and_includes_customer_and_own_cheque_details(): void
    {
        $supplier = Supplier::create(['name' => 'Supplier Example', 'phone' => '0770000000']);
        $this->cheque(['cheque_number' => 'CUSTOMER-1', 'status' => 'passed', 'supplier_id' => $supplier->id, 'auto_passed' => true]);
        $this->cheque(['cheque_number' => 'OWN-1', 'type' => 'own', 'status' => 'passed', 'supplier_id' => $supplier->id, 'sale_id' => null]);
        $this->cheque(['cheque_number' => 'EXCLUDED', 'status' => 'pending']);

        Pdf::shouldReceive('loadView')->once()->withArgs(function ($view, $data) {
            $this->assertSame('cheque-payments.pdf', $view);
            $this->assertCount(2, $data['rows']);
            $this->assertEquals(200000, $data['rows']->sum('amount'));
            $html = view($view, $data)->render();
            $this->assertStringContainsString('Supplier Example', $html);
            $this->assertStringContainsString('Own Cheque', $html);
            $this->assertStringContainsString('Endorsed to:', $html);
            $this->assertStringContainsString('Auto passed', $html);
            $this->assertStringNotContainsString('EXCLUDED', $html);

            return true;
        })->andReturn($pdf = Mockery::mock(\Barryvdh\DomPDF\PDF::class));
        $pdf->shouldReceive('setPaper')->once()->with('a4', 'landscape')->andReturnSelf();
        $pdf->shouldReceive('download')->once()->andReturn(response('PDF download'));

        $this->get(route('cheque-payments.export.pdf', ['status' => 'passed']))->assertOk();
    }

    public function test_pdf_download_renders_with_no_filters_and_with_no_matching_records(): void
    {
        $this->cheque();
        foreach ([[], ['search' => 'NO-MATCH']] as $filters) {
            $response = $this->get(route('cheque-payments.export.pdf', $filters))->assertOk();
            $response->assertHeader('content-type', 'application/pdf');
            $response->assertDownload('cheque-payments-'.now()->format('Y-m-d').'.pdf');
            $this->assertStringStartsWith('%PDF-', $response->getContent());
        }
    }

    public function test_export_routes_require_cheque_view_permission(): void
    {
        $role = Role::create(['name' => 'No Cheque Access', 'permissions' => [], 'is_active' => true]);
        $this->actingAs(User::factory()->create(['role_id' => $role->id]));
        $this->get(route('cheque-payments.export.pdf'))->assertForbidden();
        $this->get(route('cheque-payments.export.excel'))->assertForbidden();
    }

    private function cheque(array $attributes = []): ChequePayment
    {
        $customer = Customer::firstOrCreate(['name' => 'Customer Example'], ['phone' => '0771234567']);
        $sale = Sale::firstOrCreate(['sale_no' => 'INV-CHEQUE'], [
            'customer_id' => $customer->id, 'user_id' => auth()->id(), 'sale_date' => '2026-10-01', 'sale_type' => 'sale',
        ]);

        return ChequePayment::create(array_merge([
            'sale_id' => $sale->id, 'customer_id' => $customer->id, 'type' => 'customer',
            'cheque_date' => '2026-10-10', 'cheque_number' => '000333', 'bank_name' => 'Sample Bank',
            'amount' => 100000, 'status' => 'pending',
        ], $attributes));
    }
}
