<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Support\BusinessExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class BusinessExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $role = Role::create(['name' => 'Admin', 'permissions' => [], 'is_active' => true]);
        $this->actingAs(User::factory()->create(['role_id' => $role->id]));
    }

    public static function exportPages(): array
    {
        return array_map(fn ($page) => [$page[0]], BusinessExport::PAGES);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('exportPages')]
    public function test_every_supported_screen_downloads_real_excel_and_pdf_even_when_empty(string $module): void
    {
        $route = $module;
        $excel = $this->get(route('business.export', ['module' => $module, 'format' => 'excel']))->assertOk();
        $excel->assertDownload($module.'-'.today()->toDateString().'.xlsx');
        Cell::setValueBinder(new DefaultValueBinder);
        $book = IOFactory::load($excel->baseResponse->getFile()->getPathname());
        $this->assertGreaterThan(0, $book->getSheetCount(), $route);
        $this->assertGreaterThanOrEqual(5, $book->getActiveSheet()->getHighestDataRow(), $route);
        $book->disconnectWorksheets();
        $pdf = $this->get(route('business.export', ['module' => $module, 'format' => 'pdf']))->assertOk();
        $pdf->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent(), $route);
        unset($book, $excel, $pdf);
        gc_collect_cycles();
    }

    public function test_products_export_all_filtered_pages_preserving_identifiers_and_literal_text(): void
    {
        $unit = Unit::create(['name' => 'Piece', 'short_name' => 'pcs']);
        for ($i = 0; $i < 24; $i++) {
            Product::create(['name' => 'Matching Part '.$i, 'sku' => '000'.str_pad($i, 4, '0', STR_PAD_LEFT), 'unit_id' => $unit->id, 'cost_price' => 10, 'selling_price' => 25, 'stock_quantity' => 5, 'alert_quantity' => 1, 'is_active' => true]);
        }
        Product::create(['name' => '=1+1', 'sku' => 'FORMULA', 'unit_id' => $unit->id, 'cost_price' => 10, 'selling_price' => 25, 'stock_quantity' => 5, 'is_active' => true]);
        $response = $this->get(route('business.export', ['module' => 'products', 'format' => 'excel', 'search' => 'Matching', 'page' => 2]))->assertOk();
        Cell::setValueBinder(new DefaultValueBinder);
        $sheet = IOFactory::load($response->baseResponse->getFile()->getPathname())->getActiveSheet();
        $this->assertSame(29, $sheet->getHighestDataRow());
        $text = json_encode($sheet->toArray());
        $this->assertStringContainsString('0000000', $text);
        $this->assertStringContainsString('Matching Part 23', $text);
        $this->assertStringNotContainsString('FORMULA', $text);
        $this->assertStringNotContainsString('Actions', json_encode($sheet->toArray()));
    }

    public function test_product_exports_keep_formulas_literal_and_apply_visibility_rules(): void
    {
        $unit = Unit::create(['name' => 'Piece', 'short_name' => 'pcs']);
        $visible = Product::create(['name' => '=1+1', 'sku' => '0000123', 'unit_id' => $unit->id, 'cost_price' => 984.12, 'selling_price' => 1234.56, 'stock_quantity' => 0, 'is_active' => true]);
        $hidden = Product::create(['name' => 'Hidden Export Product', 'sku' => 'HIDDEN-SKU', 'unit_id' => $unit->id, 'cost_price' => 500, 'selling_price' => 600, 'stock_quantity' => 10, 'is_active' => true]);
        \App\Services\DashboardVisibilityService::save(['hidden_products' => [$hidden->id], 'hide_actual_purchase_price' => true, 'price_visible_percentage' => 50]);
        $response = $this->get(route('business.export', ['module' => 'products', 'format' => 'excel']))->assertOk();
        Cell::setValueBinder(new DefaultValueBinder);
        $sheet = IOFactory::load($response->baseResponse->getFile()->getPathname())->getActiveSheet();
        $this->assertSame('=1+1', $sheet->getCell('A6')->getValue());
        $this->assertSame('s', $sheet->getCell('A6')->getDataType());
        $this->assertSame('0000123', $sheet->getCell('B6')->getValue());
        $this->assertSame('s', $sheet->getCell('B6')->getDataType());
        $this->assertEquals(617, $sheet->getCell('F6')->getValue());
        $this->assertSame('n', $sheet->getCell('F6')->getDataType());
        $this->assertSame('—', $sheet->getCell('G6')->getValue());
        $this->assertEquals(0, $sheet->getCell('H6')->getValue());
        $this->assertSame('n', $sheet->getCell('H6')->getDataType());
        $this->assertStringNotContainsString('HIDDEN-SKU', json_encode($sheet->toArray()));
    }

    public function test_every_export_requires_its_screen_permission(): void
    {
        $role = Role::create(['name' => 'No Export Access', 'permissions' => [], 'is_active' => true]);
        $this->actingAs(User::factory()->create(['role_id' => $role->id]));
        foreach (BusinessExport::PAGES as [$module]) {
            foreach (['pdf', 'excel'] as $format) {
                $this->get(route('business.export', compact('module', 'format')))->assertForbidden();
            }
        }
        $this->get(route('accounting.export', ['section' => 'accounts', 'format' => 'excel']))->assertForbidden();
    }

    public function test_unsold_report_custom_dates_and_quotations_are_handled_correctly(): void
    {
        $unit = Unit::create(['name' => 'Piece', 'short_name' => 'pcs']);
        $product = Product::create(['name' => 'Unsold Genuine Part', 'sku' => 'UNSOLD', 'unit_id' => $unit->id, 'selling_price' => 100, 'stock_quantity' => 5, 'is_active' => true]);
        $customer = Customer::create(['name' => 'Quotation Customer', 'phone' => '0771234567']);
        $sale = \App\Models\Sale::create(['customer_id' => $customer->id, 'user_id' => auth()->id(), 'sale_date' => '2026-08-01', 'sale_type' => 'quotation', 'total_amount' => 100, 'paid_amount' => 0, 'due_amount' => 100, 'payment_status' => 'unpaid']);
        \App\Models\SaleItem::create(['sale_id' => $sale->id, 'product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100, 'total' => 100]);
        $this->get(route('reports.never-sold'))->assertOk()->assertSee('Unsold Genuine Part');
        $this->get(route('quotations.index'))->assertOk()->assertSee($sale->sale_no);
        $sale->update(['sale_type' => 'sale']);
        $export = $this->get(route('business.export', ['module' => 'sales-report', 'format' => 'excel']))->assertOk();
        Cell::setValueBinder(new DefaultValueBinder);
        $report = IOFactory::load($export->baseResponse->getFile()->getPathname());
        $this->assertSame('Period: 2026-08-01 to 2026-08-01', $report->getActiveSheet()->getCell('A2')->getValue());
        $this->assertEquals(100, $report->getActiveSheet()->getCell('B6')->getValue());
        $this->assertSame('n', $report->getActiveSheet()->getCell('B6')->getDataType());
        $this->assertGreaterThanOrEqual(3, $report->getSheetCount());

        $this->get(route('reports.never-sold'))->assertOk()->assertDontSee('Unsold Genuine Part');
        $this->get(route('reports.unsold-recently', ['period' => 'custom', 'custom_from' => '2026-09-01', 'custom_to' => '2026-09-30']))->assertOk()->assertSee('Unsold Genuine Part');
        $this->get(route('reports.unsold-recently', ['period' => 'custom', 'custom_from' => '2026-08-01', 'custom_to' => '2026-08-31']))->assertOk()->assertDontSee('Unsold Genuine Part');
    }

    public function test_accounting_excel_contains_all_filtered_transactions_and_old_url_is_real_excel(): void
    {
        $account = \App\Models\Accounting\ChartAccount::create(['code' => '5100', 'name' => 'Shop Expenses', 'type' => 'expense', 'is_active' => true]);
        for ($i = 0; $i < 23; $i++) {
            \App\Models\Accounting\AccountTransaction::create(['account_id' => $account->id, 'user_id' => auth()->id(), 'transaction_date' => '2026-10-10', 'direction' => 'out', 'amount' => 15.25, 'payment_method' => 'cash', 'reference_no' => 'MATCH-'.$i, 'description' => '=A1']);
        }
        \App\Models\Accounting\AccountTransaction::create(['account_id' => $account->id, 'user_id' => auth()->id(), 'transaction_date' => '2026-09-01', 'direction' => 'out', 'amount' => 999, 'payment_method' => 'cash', 'reference_no' => 'OTHER-PERIOD']);
        $filters = ['from' => '2026-10-01', 'to' => '2026-10-31', 'account_id' => $account->id];
        foreach ([route('business.export', ['module' => 'account-transactions', 'format' => 'excel'] + $filters), route('accounting.export', ['section' => 'transactions', 'format' => 'excel'] + $filters)] as $url) {
            $response = $this->get($url)->assertOk();
            Cell::setValueBinder(new DefaultValueBinder);
            $sheet = IOFactory::load($response->baseResponse->getFile()->getPathname())->getActiveSheet();
            $this->assertSame(28, $sheet->getHighestDataRow());
            $this->assertStringNotContainsString('OTHER-PERIOD', json_encode($sheet->toArray()));
            $this->assertSame('=A1', $sheet->getCell('H6')->getValue());
            $this->assertSame('s', $sheet->getCell('H6')->getDataType());
            $this->assertEquals(15.25, $sheet->getCell('I6')->getValue());
            $this->assertStringContainsString('2026-10-01 to 2026-10-31', $sheet->getCell('A2')->getValue());
        }
    }

    public function test_purchase_download_honors_search_and_preorder_scope(): void
    {
        $supplier = \App\Models\Supplier::create(['name' => 'Matching Supplier', 'phone' => '0770000000']);
        foreach ([['PUR-MATCH', false], ['PUR-PREORDER', true]] as [$number, $preorder]) {
            \App\Models\Purchase::create(['purchase_no' => $number, 'supplier_id' => $supplier->id, 'user_id' => auth()->id(), 'purchase_date' => today(), 'total_amount' => 123.45, 'due_amount' => 123.45, 'payment_status' => 'unpaid', 'status' => 'received', 'is_pre_order' => $preorder]);
        }
        $response = $this->get(route('business.export', ['module' => 'purchases', 'format' => 'excel', 'search' => 'Matching Supplier']))->assertOk();
        Cell::setValueBinder(new DefaultValueBinder);
        $sheet = IOFactory::load($response->baseResponse->getFile()->getPathname())->getActiveSheet();
        $this->assertSame(6, $sheet->getHighestDataRow());
        $this->assertSame('PUR-MATCH', $sheet->getCell('A6')->getValue());
        $this->assertEquals(123.45, $sheet->getCell('D6')->getValue());
        $this->assertSame('n', $sheet->getCell('D6')->getDataType());
        $this->assertStringNotContainsString('PUR-PREORDER', json_encode($sheet->toArray()));
    }

    public function test_petty_cash_excel_contains_a_full_statement_with_running_balances(): void
    {
        $account = \App\Models\Accounting\ChartAccount::create(['code' => '1110', 'name' => 'Petty Cash', 'type' => 'asset', 'subtype' => 'petty_cash', 'current_balance' => 1000, 'is_active' => true]);
        $fund = \App\Models\Accounting\PettyCashFund::create(['chart_account_id' => $account->id, 'name' => 'Petty Fund', 'opening_balance' => 1000, 'current_balance' => 1000, 'is_active' => true]);
        $fund->forceFill(['created_at' => '2026-09-01'])->save();
        \App\Models\Accounting\AccountTransaction::create(['account_id' => $account->id, 'user_id' => auth()->id(), 'transaction_date' => '2026-10-10', 'direction' => 'out', 'amount' => 125, 'payment_method' => 'cash', 'reference_no' => 'EXP-001', 'description' => 'Delivery expense']);
        $response = $this->get(route('accounting.export', ['section' => 'petty-cash', 'format' => 'excel', 'from' => '2026-10-01', 'to' => '2026-10-31']))->assertOk();
        $response->assertDownload('petty-cash-history.xlsx');
        Cell::setValueBinder(new DefaultValueBinder);
        $sheet = IOFactory::load($response->baseResponse->getFile()->getPathname())->getActiveSheet();
        $this->assertEquals(1000, $sheet->getCell('G6')->getValue());
        $this->assertSame('EXP-001', $sheet->getCell('C7')->getValue());
        $this->assertEquals(125, $sheet->getCell('F7')->getValue());
        $this->assertEquals(875, $sheet->getCell('G7')->getValue());
        $this->assertEquals(875, $sheet->getCell('G9')->getValue());
    }

    public function test_preorder_status_screen_keeps_its_status_in_download_links(): void
    {
        $this->get(route('preorders.status', 'pending'))->assertOk()->assertSee(
            'href="'.e(route('business.export', ['module' => 'preorders', 'format' => 'excel', 'status' => 'pending'])).'"', false
        );
    }

    public function test_export_cannot_bypass_the_source_permission_or_export_unlisted_screens(): void
    {
        $role = Role::create(['name' => 'Limited', 'permissions' => ['products.view'], 'is_active' => true]);
        $this->actingAs(User::factory()->create(['role_id' => $role->id]));
        $this->get(route('business.export', ['module' => 'purchases', 'format' => 'excel']))->assertForbidden();
        $this->get(route('business.export', ['module' => 'account-transactions', 'format' => 'pdf']))->assertForbidden();
        $this->get(route('business.export', ['module' => 'users', 'format' => 'excel']))->assertNotFound();
        $this->get(route('business.export', ['module' => 'products', 'format' => 'excel']))->assertOk();
        $this->get(route('business.export', ['module' => 'products', 'format' => 'csv']))->assertNotFound();
    }
}
