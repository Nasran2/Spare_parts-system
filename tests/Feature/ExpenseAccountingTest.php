<?php

namespace Tests\Feature;

use App\Http\Middleware\RequirePermission;
use App\Models\Accounting\AccountTransaction;
use App\Models\Accounting\BankAccount;
use App\Models\Accounting\ChartAccount;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Services\ExpenseAccountingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseAccountingTest extends TestCase
{
    use RefreshDatabase;

    public function test_expense_payment_method_updates_cash_or_bank_accounting(): void
    {
        $user = User::factory()->create();
        $category = ExpenseCategory::create(['name' => 'Rent', 'is_active' => true]);
        $expense = Expense::create([
            'expense_category_id' => $category->id,
            'user_id' => $user->id,
            'expense_date' => '2026-06-08',
            'amount' => 200,
            'payment_method' => 'bank_transfer',
            'description' => 'Shop rent',
        ]);

        $service = app(ExpenseAccountingService::class);
        $service->sync($expense->load('category'), $user->id);

        $this->assertSame(-200.0, (float) ChartAccount::where('code', '1200')->value('current_balance'));
        $this->assertSame(200.0, (float) ChartAccount::where('code', '5000')->value('current_balance'));
        $this->assertSame(2, AccountTransaction::where('source_type', 'expense')->where('source_id', $expense->id)->count());

        $expense->update([
            'amount' => 50,
            'payment_method' => 'cash',
        ]);
        $service->sync($expense->fresh('category'), $user->id);

        $this->assertSame(0.0, (float) ChartAccount::where('code', '1200')->value('current_balance'));
        $this->assertSame(-50.0, (float) ChartAccount::where('code', '1100')->value('current_balance'));
        $this->assertSame(50.0, (float) ChartAccount::where('code', '5000')->value('current_balance'));

        $service->reverse($expense);

        $this->assertSame(0.0, (float) ChartAccount::where('code', '1100')->value('current_balance'));
        $this->assertSame(0.0, (float) ChartAccount::where('code', '5000')->value('current_balance'));
        $this->assertSame(0, AccountTransaction::where('source_type', 'expense')->where('source_id', $expense->id)->count());
    }

    public function test_bank_system_balance_is_calculated_for_selected_statement_month(): void
    {
        $this->withoutMiddleware(RequirePermission::class);

        $user = User::factory()->create();
        $bankChartAccount = ChartAccount::create([
            'code' => '1200',
            'name' => 'Bank',
            'type' => 'asset',
            'subtype' => 'bank',
            'opening_balance' => 0,
            'current_balance' => 0,
            'is_system' => true,
            'is_active' => true,
        ]);
        $bankAccount = BankAccount::create([
            'chart_account_id' => $bankChartAccount->id,
            'bank_name' => 'Test Bank',
            'account_name' => 'Main',
            'opening_balance' => 1000,
            'statement_balance' => 1000,
            'is_active' => true,
        ]);

        AccountTransaction::create([
            'account_id' => $bankChartAccount->id,
            'user_id' => $user->id,
            'transaction_date' => '2026-01-10',
            'direction' => 'in',
            'payment_method' => 'bank_deposit',
            'amount' => 500,
            'source_type' => 'manual',
            'description' => 'January deposit',
        ]);
        AccountTransaction::create([
            'account_id' => $bankChartAccount->id,
            'user_id' => $user->id,
            'transaction_date' => '2026-02-05',
            'direction' => 'out',
            'payment_method' => 'bank_transfer',
            'amount' => 200,
            'source_type' => 'manual',
            'description' => 'February expense',
        ]);

        $this->actingAs($user)
            ->getJson(route('accounting.banks.system-balance', ['bankAccount' => $bankAccount, 'month' => '2026-01']))
            ->assertOk()
            ->assertJson([
                'statement_month' => '2026-01',
                'statement_date' => '2026-01-31',
                'system_balance' => 1500,
            ]);

        $this->actingAs($user)
            ->getJson(route('accounting.banks.system-balance', ['bankAccount' => $bankAccount, 'month' => '2026-02']))
            ->assertOk()
            ->assertJson([
                'statement_month' => '2026-02',
                'statement_date' => '2026-02-28',
                'system_balance' => 1300,
            ]);
    }

    public function test_manual_bank_transaction_is_attached_to_selected_bank_for_reconciliation(): void
    {
        $this->withoutMiddleware(RequirePermission::class);

        $user = User::factory()->create();
        $bankChartAccount = ChartAccount::create([
            'code' => '1200',
            'name' => 'Bank',
            'type' => 'asset',
            'subtype' => 'bank',
            'opening_balance' => 0,
            'current_balance' => 0,
            'is_system' => true,
            'is_active' => true,
        ]);
        $selectedBank = BankAccount::create([
            'chart_account_id' => $bankChartAccount->id,
            'bank_name' => 'Main Bank',
            'account_name' => 'Current',
            'opening_balance' => 1000,
            'statement_balance' => 1000,
            'is_active' => true,
        ]);
        $otherBank = BankAccount::create([
            'chart_account_id' => $bankChartAccount->id,
            'bank_name' => 'Savings Bank',
            'account_name' => 'Savings',
            'opening_balance' => 2000,
            'statement_balance' => 2000,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('accounting.transactions.store'), [
                'account_id' => $bankChartAccount->id,
                'bank_account_id' => $selectedBank->id,
                'transaction_date' => '2026-06-08',
                'direction' => 'in',
                'payment_method' => 'bank_transfer',
                'amount' => 500,
                'reference_no' => 'BT-1001',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('account_transactions', [
            'bank_account_id' => $selectedBank->id,
            'payment_method' => 'bank_transfer',
            'amount' => 500,
        ]);

        $this->actingAs($user)
            ->getJson(route('accounting.banks.system-balance', ['bankAccount' => $selectedBank, 'month' => '2026-06']))
            ->assertOk()
            ->assertJson([
                'system_balance' => 1500,
            ]);

        $this->actingAs($user)
            ->getJson(route('accounting.banks.system-balance', ['bankAccount' => $otherBank, 'month' => '2026-06']))
            ->assertOk()
            ->assertJson([
                'system_balance' => 2000,
            ]);
    }
}
