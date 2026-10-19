<?php

namespace Tests\Feature;

use App\Models\Accounting\AccountTransaction;
use App\Models\Accounting\ChartAccount;
use App\Models\Accounting\PettyCashExpense;
use App\Models\Accounting\PettyCashFund;
use App\Models\ExpenseCategory;
use App\Models\Role;
use App\Models\User;
use App\Services\PettyCashStatementService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

class PettyCashStatementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $role = Role::create(['name' => 'Admin', 'permissions' => [], 'is_active' => true]);
        $this->actingAs(User::factory()->create(['role_id' => $role->id]));
    }

    public function test_selected_period_has_brought_forward_chronological_balances_and_carried_forward(): void
    {
        $fund = $this->fund('Shop Fund', 1000);
        $this->entry($fund, '2026-08-01', 'in', 1000, 'Opening Balance', ['source_type' => 'petty_cash_transfer', 'source_id' => $fund->id]);
        $this->entry($fund, '2026-08-31', 'out', 100, 'Previous expense');
        $this->entry($fund, '2026-09-10', 'out', 300, 'Rent', ['reference_no' => 'V-003']);
        $this->entry($fund, '2026-09-01', 'in', 200, 'Cash top up', ['reference_no' => 'TOP-001']);
        $this->entry($fund, '2026-10-01', 'out', 99, 'After period');
        $data = $this->statement(['petty_cash_fund_id' => $fund->id, 'from' => '2026-09-01', 'to' => '2026-09-30']);
        $this->assertEquals(900, $data['opening']);
        $this->assertEquals(200, $data['moneyIn']);
        $this->assertEquals(300, $data['moneyOut']);
        $this->assertEquals(800, $data['closing']);
        $this->assertSame(['Cash top up', 'Rent'], $data['rows']->pluck('description')->all());
        $this->assertEquals([1100, 800], $data['rows']->pluck('balance')->all());
        $html = view('accounting.export_petty_cash', $data)->render();
        $this->assertStringContainsString('Balance Brought Forward', $html);
        $this->assertStringContainsString('Balance Carried Forward', $html);
        $this->assertStringNotContainsString('After period', $html);
        $this->assertStringContainsString('V-003', $html);

        Pdf::shouldReceive('loadView')->once()->withArgs(fn ($view, $report) => $view === 'accounting.export_petty_cash' && $report['opening'] == 900 && $report['closing'] == 800)
            ->andReturn($pdf = Mockery::mock(\Barryvdh\DomPDF\PDF::class));
        $pdf->shouldReceive('setPaper')->with('a4', 'landscape')->once()->andReturnSelf();
        $pdf->shouldReceive('download')->with('petty-cash-history.pdf')->once()->andReturn(response('pdf'));
        $this->get(route('accounting.export', ['section' => 'petty-cash', 'format' => 'pdf', 'petty_cash_fund_id' => $fund->id, 'from' => '2026-09-01', 'to' => '2026-09-30']))->assertOk();
    }

    public function test_no_activity_carries_previous_balance_forward_and_legacy_opening_is_included_once(): void
    {
        $fund = $this->fund('Legacy Fund', 500);
        $this->entry($fund, '2026-08-20', 'out', 75, 'Old expense');
        $data = $this->statement(['from' => '2026-09-01', 'to' => '2026-09-30']);
        $this->assertEquals(425, $data['opening']);
        $this->assertEquals(425, $data['closing']);
        $this->assertCount(0, $data['rows']);
        $this->assertEquals(0, $data['moneyIn']);
        $all = $this->statement();
        $this->assertSame('Opening balance', $all['rows']->first()['description']);
        $this->assertEquals(500, $all['moneyIn']);
        $this->assertEquals(425, $all['closing']);
    }

    public function test_all_funds_and_single_fund_do_not_count_transfer_counterparts_twice(): void
    {
        $first = $this->fund('First Fund', 1000);
        $second = $this->fund('Second Fund', 200);
        $this->entry($first, '2026-08-01', 'in', 1000, 'Opening Balance', ['source_type' => 'petty_cash_transfer', 'source_id' => $first->id]);
        $this->entry($second, '2026-08-01', 'in', 200, 'Opening Balance', ['source_type' => 'petty_cash_transfer', 'source_id' => $second->id]);
        $main = ChartAccount::create(['code' => '1100', 'name' => 'Main Cash', 'type' => 'asset', 'opening_balance' => 0, 'current_balance' => 0]);
        $this->entry($first, '2026-09-01', 'in', 100, 'Transfer from main cash', ['related_account_id' => $main->id]);
        AccountTransaction::create(['account_id' => $main->id, 'related_account_id' => $first->chart_account_id, 'transaction_date' => '2026-09-01', 'direction' => 'out', 'amount' => 100, 'payment_method' => 'cash']);
        $this->entry($second, '2026-09-02', 'out', 300, 'More than balance');
        $all = $this->statement(['from' => '2026-09-01']);
        $this->assertEquals(1200, $all['opening']);
        $this->assertEquals(100, $all['moneyIn']);
        $this->assertEquals(300, $all['moneyOut']);
        $this->assertEquals(1000, $all['closing']);
        $single = $this->statement(['petty_cash_fund_id' => $second->id, 'from' => '2026-09-01']);
        $this->assertEquals(-100, $single['closing']);
        $this->assertCount(1, $single['rows']);
    }

    public function test_voucher_details_and_filters_keep_the_actual_period_balances(): void
    {
        $fund = $this->fund('Office Fund', 1000);
        $category = ExpenseCategory::create(['name' => 'Office Supplies', 'is_active' => true]);
        $expense = PettyCashExpense::create(['petty_cash_fund_id' => $fund->id, 'expense_category_id' => $category->id, 'expense_date' => '2026-09-02', 'amount' => 80, 'voucher_no' => 'PC-008', 'description' => 'Paper']);
        $this->entry($fund, '2026-09-01', 'out', 20, 'Delivery');
        $this->entry($fund, '2026-09-02', 'out', 80, 'Paper', ['source_type' => 'petty_cash_expense', 'source_id' => $expense->id]);
        $data = $this->statement(['from' => '2026-09-01', 'search' => 'PC-008', 'expense_category_id' => $category->id]);
        $this->assertCount(1, $data['rows']);
        $this->assertEquals(100, $data['moneyOut']);
        $this->assertEquals(900, $data['closing']);
        $this->assertEquals(900, $data['rows']->first()['balance']);
        $this->assertSame('PC-008', $data['rows']->first()['reference']);
        $this->assertSame('Expense / Office Supplies', $data['rows']->first()['details']);
        $this->assertStringContainsString('including entries hidden by these filters', view('accounting.export_petty_cash', $data)->render());
    }

    public function test_pdf_renders_empty_data_and_invalid_period_is_rejected(): void
    {
        $this->get(route('accounting.export', ['section' => 'petty-cash', 'format' => 'pdf']))->assertOk()->assertDownload('petty-cash-history.pdf')->assertHeader('content-type', 'application/pdf');
        $this->getJson(route('accounting.export', ['section' => 'petty-cash', 'format' => 'pdf', 'from' => '2026-09-30', 'to' => '2026-09-01']))->assertUnprocessable()->assertJsonValidationErrors('to');
    }

    public function test_blank_dates_use_earliest_activity_through_today_even_when_search_has_no_matches(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-17 12:00:00'));
        $fund = $this->fund('Daily Cash', 1000);
        $this->entry($fund, '2026-09-01', 'out', 100, 'Shop expense');
        $this->entry($fund, '2026-11-01', 'out', 500, 'Future expense');
        $data = $this->statement(['search' => 'NO-MATCH']);
        $this->assertSame('2026-08-01', $data['from']);
        $this->assertSame('2026-10-17', $data['to']);
        $this->assertEquals(900, $data['closing']);
        $this->assertCount(0, $data['rows']);
        $html = view('accounting.export_petty_cash', $data)->render();
        $this->assertStringContainsString('From Date', $html);
        $this->assertStringContainsString('To Date', $html);
        $this->assertStringContainsString('2026-08-01', $html);
        $this->assertStringNotContainsString('Latest entry', $html);
        $this->assertStringNotContainsString('Beginning', $html);
        $this->travelBack();
    }

    public function test_empty_report_and_partial_date_filters_show_concrete_dates(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-17 12:00:00'));
        $empty = $this->statement();
        $this->assertSame('2026-10-17', $empty['from']);
        $this->assertSame('2026-10-17', $empty['to']);
        $until = $this->statement(['to' => '2026-09-30']);
        $this->assertSame('2026-09-30', $until['from']);
        $this->assertSame('2026-09-30', $until['to']);
        $from = $this->statement(['from' => '2026-09-01']);
        $this->assertSame('2026-09-01', $from['from']);
        $this->assertSame('2026-10-17', $from['to']);
        $this->travelBack();
    }

    private function statement(array $filters = []): array
    {
        return app(PettyCashStatementService::class)->build(Request::create('/statement', 'GET', $filters));
    }

    private function fund(string $name, float $opening): PettyCashFund
    {
        $chart = ChartAccount::create(['code' => '111'.ChartAccount::count(), 'name' => $name, 'type' => 'asset', 'subtype' => 'cash', 'opening_balance' => $opening, 'current_balance' => $opening]);
        $fund = PettyCashFund::create(['chart_account_id' => $chart->id, 'name' => $name, 'opening_balance' => $opening, 'current_balance' => $opening, 'is_active' => true]);
        $fund->forceFill(['created_at' => '2026-08-01 10:00:00'])->save();

        return $fund;
    }

    private function entry(PettyCashFund $fund, string $date, string $direction, float $amount, string $description, array $extra = []): AccountTransaction
    {
        return AccountTransaction::create($extra + ['account_id' => $fund->chart_account_id, 'transaction_date' => $date, 'direction' => $direction, 'amount' => $amount, 'description' => $description, 'payment_method' => 'cash', 'source_type' => 'manual']);
    }
}
