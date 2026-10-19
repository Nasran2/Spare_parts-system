<?php

namespace Tests\Feature;

use App\Models\Accounting\AccountTransaction;
use App\Models\Accounting\BankAccount;
use App\Models\Accounting\ChartAccount;
use App\Models\Role;
use App\Models\User;
use App\Services\AccountBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BankBalanceHistoryTest extends TestCase
{
    use RefreshDatabase;

    private BankAccount $first;

    private BankAccount $second;

    private AccountBalanceService $balances;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 17)->startOfDay());
        $role = Role::create(['name' => 'Admin', 'permissions' => [], 'is_active' => true]);
        $this->actingAs(User::factory()->create(['role_id' => $role->id]));
        $chart = ChartAccount::create(['code' => '1200', 'name' => 'Bank', 'type' => 'asset', 'subtype' => 'bank', 'opening_balance' => 0, 'current_balance' => 1500, 'is_active' => true]);
        $this->first = BankAccount::create(['chart_account_id' => $chart->id, 'bank_name' => 'Bank One', 'account_name' => 'Main', 'account_number' => '111', 'opening_balance' => 1000, 'is_active' => true]);
        $this->second = BankAccount::create(['chart_account_id' => $chart->id, 'bank_name' => 'Bank Two', 'account_name' => 'Other', 'account_number' => '222', 'opening_balance' => 500, 'is_active' => true]);
        $this->balances = app(AccountBalanceService::class);
    }

    public function test_shared_bank_accounts_have_separate_balances_and_filtered_running_history(): void
    {
        $this->movement($this->first, '2026-09-01', 'in', 100, 'Earlier receipt');
        $out = $this->movement($this->first, '2026-10-02', 'out', 1300, 'Purchase PUR-500');
        $this->movement($this->second, '2026-10-02', 'in', 400, 'Other bank receipt');
        $this->movement($this->first, '2026-10-03', 'in', 50, 'Later sale receipt');
        $this->assertEquals(-150, $this->balances->balance($this->first));
        $this->assertEquals(900, $this->balances->balance($this->second));
        $response = $this->get(route('accounting.bank-balances', ['account' => $this->first->id, 'from' => '2026-10-01', 'to' => '2026-10-17', 'direction' => 'out']))->assertOk();
        $response->assertViewHas('totals', fn ($totals) => $totals['opening'] === 1100.0 && $totals['in'] === 50.0 && $totals['out'] === 1300.0 && $totals['closing'] === -150.0);
        $response->assertViewHas('history', fn ($history) => $history->total() === 1 && $history->first()->id === $out->id && $history->first()->running_balance === -200.0);
        $response->assertSee('Purchase PUR-500')->assertSee('overdrawn')->assertDontSee('Other bank receipt');
        $this->get(route('accounting.bank-book', ['bank_account_id' => $this->first->id]))->assertOk()->assertDontSee('Other bank receipt');
    }

    public function test_transfers_update_both_banks_and_cash_once_and_allow_negative_balances(): void
    {
        $data = $this->transferData((string) $this->first->id, (string) $this->second->id, 1200);
        $this->post(route('accounting.bank-transfers.store'), $data)->assertRedirect();
        $this->post(route('accounting.bank-transfers.store'), $data)->assertRedirect();
        $this->assertDatabaseCount('account_transfers', 1);
        $this->assertSame(2, AccountTransaction::where('source_type', 'account_transfer')->count());
        $this->assertEquals(-200, $this->balances->balance($this->first));
        $this->assertEquals(1700, $this->balances->balance($this->second));
        $this->assertEquals(1500, $this->first->chartAccount->fresh()->current_balance);
        $this->post(route('accounting.bank-transfers.store'), $this->transferData((string) $this->second->id, 'cash', 300))->assertRedirect();
        $this->assertEquals(1400, $this->balances->balance($this->second));
        $this->assertEquals(300, $this->balances->balance(null));
        $this->post(route('accounting.bank-transfers.store'), $this->transferData('cash', (string) $this->first->id, 500))->assertRedirect();
        $this->assertEquals(-200, $this->balances->balance(null));
        $this->assertEquals(300, $this->balances->balance($this->first));
        $this->get(route('accounting.bank-balances', ['account' => 'cash']))->assertOk()->assertSee('Cash withdrawal')->assertSee('-200.00');
    }

    public function test_invalid_transfers_are_atomic_and_viewers_cannot_move_money(): void
    {
        $data = $this->transferData((string) $this->first->id, (string) $this->first->id, 10);
        $this->postJson(route('accounting.bank-transfers.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('to_account');
        $this->second->update(['is_active' => false]);
        $this->postJson(route('accounting.bank-transfers.store'), $this->transferData((string) $this->first->id, (string) $this->second->id, 10))->assertUnprocessable();
        $this->assertDatabaseCount('account_transfers', 0);
        $this->assertDatabaseCount('account_transactions', 0);
        $role = Role::create(['name' => 'Bank Viewer', 'permissions' => ['accounting.bank-book'], 'is_active' => true]);
        $this->actingAs(User::factory()->create(['role_id' => $role->id]));
        $this->get(route('accounting.bank-balances'))->assertOk()->assertDontSee('Record Transfer');
        $this->post(route('accounting.bank-transfers.store'), $data)->assertForbidden();
    }

    public function test_opening_corrections_keep_a_dated_reason_and_do_not_repeat(): void
    {
        $data = ['opening_balance' => 1500, 'date' => '2026-10-05', 'reason' => 'Correct starting bank statement'];
        $this->post(route('accounting.banks.opening', $this->first), $data)->assertRedirect();
        $this->post(route('accounting.banks.opening', $this->first), $data)->assertRedirect();
        $this->assertSame(1, AccountTransaction::where('source_type', 'bank_opening_balance')->count());
        $this->assertEquals(1000, $this->balances->balance($this->first, '2026-10-04'));
        $this->assertEquals(1500, $this->balances->balance($this->first, '2026-10-05'));
        $this->assertEquals(500, $this->balances->balance($this->second));
        $this->get(route('accounting.bank-balances', ['account' => $this->first->id]))->assertOk()->assertSee('Correct starting bank statement');
        $this->post(route('accounting.cash.opening'), ['opening_balance' => 250, 'date' => '2026-10-01', 'reason' => 'Cash counted at opening'])->assertRedirect();
        $this->assertEquals(250, $this->balances->balance(null));
    }

    public function test_older_entries_are_not_counted_against_multiple_banks_and_can_be_assigned(): void
    {
        $entry = $this->movement(null, '2026-10-01', 'out', 200, 'Legacy bank purchase');
        $this->assertEquals(1000, $this->balances->balance($this->first));
        $this->assertEquals(500, $this->balances->balance($this->second));
        $this->getJson(route('accounting.banks.system-balance', ['bankAccount' => $this->second, 'month' => '2026-10']))->assertOk()->assertJsonPath('system_balance', 500);
        $this->get(route('accounting.bank-balances', ['account' => 'unassigned', 'all_dates' => 1]))->assertOk()->assertSee('Legacy bank purchase');
        $this->post(route('accounting.bank-entries.assign', $entry), ['bank_account_id' => $this->first->id, 'reason' => 'Matched to Bank One statement'])->assertRedirect();
        $this->assertEquals(800, $this->balances->balance($this->first));
        $this->assertEquals(500, $this->balances->balance($this->second));
        $this->assertSame(0, $this->balances->unassigned()->count());
        $this->assertDatabaseHas('activity_logs', ['description' => 'Bank entry assigned: Matched to Bank One statement']);
    }

    public function test_cash_history_includes_manual_cash_counterparts_without_duplicating_expenses(): void
    {
        $expense = ChartAccount::create(['code' => '5000', 'name' => 'Expense', 'type' => 'expense', 'opening_balance' => 0, 'current_balance' => 0, 'is_active' => true]);
        $cash = $this->balances->cashAccount();
        AccountTransaction::create(['account_id' => $expense->id, 'related_account_id' => $cash->id, 'transaction_date' => '2026-10-01', 'direction' => 'in', 'amount' => 100, 'payment_method' => 'cash', 'source_type' => 'manual', 'description' => 'Manual rent payment']);
        AccountTransaction::create(['account_id' => $cash->id, 'related_account_id' => $expense->id, 'transaction_date' => '2026-10-02', 'direction' => 'out', 'amount' => 50, 'payment_method' => 'cash', 'source_type' => 'expense', 'source_id' => 1, 'description' => 'Fuel expense']);
        AccountTransaction::create(['account_id' => $expense->id, 'related_account_id' => $cash->id, 'transaction_date' => '2026-10-02', 'direction' => 'in', 'amount' => 50, 'payment_method' => 'cash', 'source_type' => 'expense', 'source_id' => 1, 'description' => 'Fuel expense']);
        $this->assertEquals(-150, $this->balances->balance(null));
        $this->get(route('accounting.bank-balances', ['account' => 'cash']))->assertOk()
            ->assertViewHas('history', fn ($history) => $history->total() === 2)
            ->assertViewHas('totals', fn ($totals) => $totals['out'] === 150.0 && $totals['closing'] === -150.0);
    }

    public function test_adding_another_bank_preserves_the_first_banks_older_history(): void
    {
        $this->second->delete();
        $entry = $this->movement(null, '2026-10-01', 'in', 100, 'Older receipt');
        $this->assertEquals(1100, $this->balances->balance($this->first));
        $this->post(route('accounting.banks.store'), ['bank_name' => 'New Bank', 'account_name' => 'New Account', 'opening_balance' => 500])->assertRedirect();
        $this->assertSame($this->first->id, $entry->fresh()->bank_account_id);
        $this->assertEquals(1100, $this->balances->balance($this->first));
        $this->assertEquals(500, $this->balances->balance(BankAccount::where('bank_name', 'New Bank')->firstOrFail()));
    }

    public function test_account_payments_update_the_opening_balance_and_show_where_money_went(): void
    {
        $category = \App\Models\ExpenseCategory::create(['name' => 'Rent', 'is_active' => true]);
        $this->post(route('expenses.store'), ['expense_category_id' => $category->id, 'expense_date' => '2026-10-02', 'amount' => 1200, 'payment_method' => 'bank_transfer', 'bank_account_id' => $this->first->id, 'description' => 'Shop rent'])->assertRedirect();
        $this->assertEquals(-200, $this->balances->balance($this->first));
        $customer = \App\Models\Customer::create(['name' => 'Receipt Customer', 'phone' => '0771234567']);
        $this->post(route('customers.add-advance', $customer), ['amount' => 300, 'payment_method' => 'bank_transfer', 'bank_account_id' => $this->first->id, 'payment_date' => '2026-10-03'])->assertRedirect();
        $this->assertEquals(100, $this->balances->balance($this->first));
        $this->assertEquals(500, $this->balances->balance($this->second));
        $this->get(route('accounting.bank-balances', ['account' => $this->first->id]))->assertOk()->assertSee('Shop rent')
            ->assertViewHas('totals', fn ($totals) => $totals['opening'] === 1000.0 && $totals['in'] === 300.0 && $totals['out'] === 1200.0 && $totals['closing'] === 100.0);
    }

    public function test_history_pages_do_not_reset_running_balances_or_period_totals(): void
    {
        for ($i = 0; $i < 55; $i++) {
            $this->movement($this->first, '2026-10-01', 'out', 10, 'Payment '.$i);
        }
        $this->get(route('accounting.bank-balances', ['account' => $this->first->id, 'page' => 2]))->assertOk()
            ->assertViewHas('history', fn ($history) => $history->total() === 55 && $history->first()->running_balance === 490.0 && $history->last()->running_balance === 450.0)
            ->assertViewHas('totals', fn ($totals) => $totals['out'] === 550.0 && $totals['closing'] === 450.0);
    }

    private function movement(?BankAccount $bank, string $date, string $direction, float $amount, string $reason): AccountTransaction
    {
        return AccountTransaction::create(['account_id' => $bank?->chart_account_id ?? $this->first->chart_account_id, 'bank_account_id' => $bank?->id, 'transaction_date' => $date, 'direction' => $direction, 'amount' => $amount, 'payment_method' => 'bank_transfer', 'source_type' => 'manual', 'description' => $reason]);
    }

    private function transferData(string $from, string $to, float $amount): array
    {
        return ['from_account' => $from, 'to_account' => $to, 'amount' => $amount, 'transfer_date' => '2026-10-10', 'note' => 'Cash withdrawal / account transfer', 'request_token' => (string) Str::uuid()];
    }
}
