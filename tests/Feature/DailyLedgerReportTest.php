<?php

namespace Tests\Feature;

use App\Models\Accounting\AccountTransaction;
use App\Models\Accounting\ChartAccount;
use App\Models\ChequePayment;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use App\Services\DailyLedgerReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Mockery;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class DailyLedgerReportTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Carbon\Carbon::parse('2026-10-17 12:00:00'));
        $role = Role::create(['name' => 'Admin', 'permissions' => [], 'is_active' => true]);
        $this->actingAs(User::factory()->create(['role_id' => $role->id]));
        $this->customer = Customer::create(['name' => 'Ledger Customer', 'phone' => '0771234567']);
    }

    public function test_sales_totals_split_invoices_and_exclude_quotations_supplier_payments_and_other_dates(): void
    {
        $cash = $this->sale(['total_amount' => 100, 'paid_amount' => 100]);
        $cashPayment = $this->payment($cash, 100, 'cash');
        app(\App\Services\SalePaymentAccountingService::class)->recordSalePayment($cashPayment, $cash, auth()->id());
        $mixed = $this->sale(['total_amount' => 200, 'paid_amount' => 50, 'held_cheque_amount' => 80, 'due_amount' => 70]);
        $this->payment($mixed, 50, 'cash');
        $this->cheque($mixed, 80);
        $this->sale(['total_amount' => 300, 'due_amount' => 300]);
        $other = $this->sale(['total_amount' => 40, 'paid_amount' => 40]);
        $this->payment($other, 40, 'card');
        $this->sale(['sale_type' => 'quotation', 'total_amount' => 9999]);
        $this->sale(['sale_date' => '2026-10-16', 'total_amount' => 8888]);
        Payment::create(['amount' => 5000, 'payment_method' => 'cash', 'payment_date' => today()]);
        $data = $this->data();
        $this->assertEquals(['cash' => 150, 'cheque' => 80, 'other' => 40, 'credit' => 370, 'total' => 640, 'count' => 4], $data['salesTotals']);
        $this->assertCount(4, $data['salesRows']);
        $this->assertEquals(100, $data['salesReceipts']);
        $this->get(route('reports.daily-ledger'))->assertOk()->assertSee('Total Cash Sales')->assertSee('Total Cheque Sales')->assertSee('Credit Sales')->assertSee('Total Transactions')->assertSee($mixed->sale_no);
    }

    public function test_cleared_cheques_count_once_and_later_payments_do_not_change_historical_credit(): void
    {
        $sale = $this->sale(['total_amount' => 200, 'sale_date' => '2026-09-10', 'paid_amount' => 150]);
        $payment = $this->payment($sale, 100, 'cheque', '2026-10-17');
        $cheque = $this->cheque($sale, 100, ['payment_id' => $payment->id, 'status' => 'passed', 'processed_at' => now()]);
        $cheque->forceFill(['created_at' => '2026-09-10 12:00:00'])->save();
        $this->payment($sale, 50, 'cash', '2026-10-17');
        $historical = $this->data(['from' => '2026-09-01', 'to' => '2026-09-30']);
        $this->assertEquals(100, $historical['salesTotals']['cheque']);
        $this->assertEquals(100, $historical['salesTotals']['credit']);
        $this->assertEquals(0, $historical['salesTotals']['cash']);
        $current = $this->data(['from' => '2026-09-01', 'to' => '2026-10-17']);
        $this->assertEquals(100, $current['salesTotals']['cheque']);
        $this->assertEquals(50, $current['salesTotals']['cash']);
        $this->assertEquals(50, $current['salesTotals']['credit']);
    }

    public function test_cash_manual_counterparts_and_cheque_clearance_are_included_in_running_balances(): void
    {
        $cash = ChartAccount::create(['code' => '1100', 'name' => 'Cash', 'type' => 'asset', 'subtype' => 'cash', 'opening_balance' => 1000, 'current_balance' => 1000]);
        $ar = ChartAccount::create(['code' => '1300', 'name' => 'Customer Receivable', 'type' => 'asset', 'opening_balance' => 0, 'current_balance' => 0]);
        $expense = ChartAccount::create(['code' => '5000', 'name' => 'Expense', 'type' => 'expense']);
        $bank = ChartAccount::create(['code' => '1200', 'name' => 'Bank', 'type' => 'asset']);
        AccountTransaction::create(['account_id' => $expense->id, 'related_account_id' => $cash->id, 'transaction_date' => today(), 'direction' => 'in', 'amount' => 50, 'source_type' => 'manual', 'description' => 'Manual cash expense']);
        $sale = $this->sale(['total_amount' => 200, 'held_cheque_amount' => 200]);
        $cheque = $this->cheque($sale, 200);
        AccountTransaction::create(['account_id' => $ar->id, 'transaction_date' => today(), 'direction' => 'in', 'amount' => 200, 'source_type' => 'cheque_payment_hold', 'source_id' => $cheque->id]);
        AccountTransaction::create(['account_id' => $bank->id, 'related_account_id' => $ar->id, 'transaction_date' => today(), 'direction' => 'in', 'amount' => 200, 'source_type' => 'cheque_payment', 'source_id' => $cheque->id]);
        $data = $this->data();
        $this->assertEquals(1000, $data['ledgerSections']['main']['opening']);
        $this->assertEquals(950, $data['ledgerSections']['main']['closing']);
        $this->assertEquals(50, $data['periodExpenses']);
        $this->assertEquals(200, $data['ledgerSections']['cheques']['in']);
        $this->assertEquals(200, $data['ledgerSections']['cheques']['out']);
        $this->assertEquals(0, $data['ledgerSections']['cheques']['closing']);
    }

    public function test_pdf_exports_only_selected_sections_and_defaults_to_all_sections(): void
    {
        Pdf::shouldReceive('loadView')->twice()->withArgs(function ($view, $data) {
            $this->assertSame('reports.pdf.daily_ledger', $view);
            $html = view($view, $data)->render();
            foreach (DailyLedgerReportService::SECTIONS as $key => $title) {
                if (in_array($key, $data['selectedSections'])) {
                    $this->assertStringContainsString($title, $html);
                } else {
                    $this->assertStringNotContainsString($title, $html);
                }
            }
            $this->assertStringContainsString('2026-10-17', $html);

            return true;
        })->andReturn($pdf = Mockery::mock(\Barryvdh\DomPDF\PDF::class));
        $pdf->shouldReceive('setPaper')->twice()->with('a4', 'landscape')->andReturnSelf();
        $pdf->shouldReceive('download')->twice()->with('daily-ledger.pdf')->andReturn(response('pdf'));
        $this->get(route('reports.daily-ledger.pdf', ['sections' => ['main', 'sales'], 'section_selection' => 1]))->assertOk();
        $this->get(route('reports.daily-ledger.pdf'))->assertOk();
    }

    public function test_excel_contains_selected_sheets_dates_totals_and_literal_text(): void
    {
        $sale = $this->sale(['total_amount' => 123, 'due_amount' => 123, 'notes' => '=1+1']);
        $response = $this->get(route('reports.daily-ledger.excel', ['sections' => ['sales', 'petty'], 'section_selection' => 1]))->assertOk()->assertDownload('daily-ledger.xlsx');
        \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder(new \PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder);
        $workbook = IOFactory::load($response->baseResponse->getFile()->getPathname());
        $this->assertSame(['Petty Cash', 'Sales Transactions'], $workbook->getSheetNames());
        $sheet = $workbook->getSheetByName('Sales Transactions');
        $this->assertSame('2026-10-17', $sheet->getCell('B2')->getValue());
        $this->assertEquals(123, $sheet->getCell('B9')->getValue());
        $this->assertEquals(1, $sheet->getCell('B10')->getValue());
        $this->assertSame($sale->sale_no, $sheet->getCell('B14')->getValue());
        $this->assertSame('=1+1', $sheet->getCell('I14')->getValue());
        $this->assertSame('s', $sheet->getCell('I14')->getDataType());
        $this->assertSame('n', $sheet->getCell('H14')->getDataType());
    }

    public function test_export_rejects_empty_invalid_sections_and_reversed_dates(): void
    {
        foreach (['pdf', 'excel', 'csv'] as $format) {
            $url = route('reports.daily-ledger.'.$format);
            $this->getJson($url.'?section_selection=1')->assertUnprocessable()->assertJsonValidationErrors('sections');
            $this->getJson($url.'?sections[]=unknown')->assertUnprocessable()->assertJsonValidationErrors('sections.0');
            $this->getJson($url.'?from=2026-10-17&to=2026-10-01')->assertUnprocessable()->assertJsonValidationErrors('to');
        }
    }

    public function test_csv_matches_selected_sections_and_includes_opening_closing_dates(): void
    {
        $this->sale(['total_amount' => 25, 'due_amount' => 25]);
        $response = $this->get(route('reports.daily-ledger.csv', ['sections' => ['sales']]))->assertOk();
        $response->assertSee('Sales Transactions')->assertSee('Total Cash Sales')->assertSee('2026-10-17')->assertDontSee('Main Account (Cash) Transactions')->assertDontSee('Petty Cash Transactions');
    }

    public function test_actual_pdf_and_export_permissions(): void
    {
        $this->sale(['total_amount' => 50, 'due_amount' => 50]);
        $this->get(route('reports.daily-ledger.pdf'))->assertOk()->assertDownload('daily-ledger.pdf')->assertHeader('content-type', 'application/pdf');
        $role = Role::create(['name' => 'No Report Access', 'permissions' => [], 'is_active' => true]);
        $this->actingAs(User::factory()->create(['role_id' => $role->id]));
        foreach (['pdf', 'excel', 'csv'] as $format) {
            $this->get(route('reports.daily-ledger.'.$format))->assertForbidden();
        }
    }

    public function test_all_time_filter_preserves_history_and_legacy_held_cheques_are_not_cash(): void
    {
        $this->sale(['sale_date' => '2026-09-01', 'total_amount' => 100, 'paid_amount' => 20, 'held_cheque_amount' => 30, 'due_amount' => 50, 'payment_method' => 'cash']);
        $data = $this->data(['from' => '', 'to' => '']);
        $this->assertSame('2026-09-01', $data['from']);
        $this->assertSame('2026-10-17', $data['to']);
        $this->assertEquals(20, $data['salesTotals']['cash']);
        $this->assertEquals(30, $data['salesTotals']['cheque']);
        $this->assertEquals(50, $data['salesTotals']['credit']);
        $this->get(route('reports.daily-ledger', ['from' => '', 'to' => '']))->assertOk()->assertSee('2026-09-01');
    }

    private function data(array $filters = []): array
    {
        return app(DailyLedgerReportService::class)->build(Request::create('/', 'GET', $filters));
    }

    private function sale(array $data = []): Sale
    {
        return Sale::create($data + ['sale_type' => 'sale', 'sale_date' => today(), 'customer_id' => $this->customer->id, 'user_id' => auth()->id(), 'total_amount' => 0, 'paid_amount' => 0, 'due_amount' => 0, 'payment_method' => 'cash']);
    }

    private function payment(Sale $sale, float $amount, string $method, ?string $date = null): Payment
    {
        return Payment::create(['sale_id' => $sale->id, 'customer_id' => $this->customer->id, 'amount' => $amount, 'payment_method' => $method, 'payment_date' => $date ?: today()]);
    }

    private function cheque(Sale $sale, float $amount, array $data = []): ChequePayment
    {
        return ChequePayment::create($data + ['sale_id' => $sale->id, 'customer_id' => $this->customer->id, 'amount' => $amount, 'type' => 'customer', 'status' => 'pending', 'cheque_date' => today(), 'cheque_number' => '000123', 'bank_name' => 'Bank Example']);
    }
}
