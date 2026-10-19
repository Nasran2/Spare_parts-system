<?php

namespace Tests\Feature;

use App\Models\Accounting\AccountTransaction;
use App\Models\Accounting\BankAccount;
use App\Models\Accounting\ChartAccount;
use App\Models\ChequePayment;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Models\PreOrder;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentBankSelectionTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private BankAccount $firstBank;

    private BankAccount $secondBank;

    protected function setUp(): void
    {
        parent::setUp();
        $role = Role::create(['name' => 'Admin', 'permissions' => [], 'is_active' => true]);
        $this->actingAs(User::factory()->create(['role_id' => $role->id]));
        $this->customer = Customer::create(['name' => 'Advance Customer', 'phone' => '0771234567', 'opening_balance' => 0]);
        $this->firstBank = $this->bank('1201', 'Bank One');
        $this->secondBank = $this->bank('1202', 'Bank Two');
    }

    public function test_advance_cheque_requires_details_and_stays_pending_until_cleared(): void
    {
        $url = route('customers.add-advance', $this->customer);
        $data = ['amount' => 500, 'payment_method' => 'cheque', 'payment_date' => today()->toDateString()];
        $this->postJson($url, $data)->assertUnprocessable()->assertJsonValidationErrors(['cheque_number', 'cheque_date', 'bank_name']);
        $this->assertDatabaseCount('payments', 0);

        $this->post($url, $data + ['cheque_number' => '000123', 'cheque_date' => today()->toDateString(), 'bank_name' => 'Customer Cheque Bank', 'account_name' => 'Customer Account', 'bank_account_id' => $this->secondBank->id])
            ->assertRedirect();
        $cheque = ChequePayment::firstOrFail();
        $this->assertSame('000123', $cheque->cheque_number);
        $this->assertSame('Customer Account', $cheque->account_name);
        $this->assertSame('pending', $cheque->status);
        $this->assertDatabaseCount('payments', 0);
        $this->assertEquals(0, $this->customer->fresh()->advance_balance);

        $this->post(route('cheque-payments.pass', $cheque), ['passed_bank_id' => $this->secondBank->id])->assertRedirect();
        $this->assertSame('passed', $cheque->fresh()->status);
        $this->assertEquals(500, $this->customer->fresh()->advance_balance);
        $payment = Payment::firstOrFail();
        $this->assertSame($this->secondBank->id, $payment->bank_account_id);
        $transaction = AccountTransaction::where('source_type', 'payment')->where('source_id', $payment->id)->firstOrFail();
        $this->assertSame($this->secondBank->id, $transaction->bank_account_id);
        $this->assertSame($this->secondBank->chart_account_id, $transaction->account_id);
        $this->assertEquals(500, $this->secondBank->chartAccount->fresh()->current_balance);
        $this->assertEquals(0, $this->firstBank->chartAccount->fresh()->current_balance);
        $this->post(route('cheque-payments.pass', $cheque), ['passed_bank_id' => $this->secondBank->id])->assertRedirect();
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_returned_advance_cheque_does_not_create_available_credit(): void
    {
        $cheque = ChequePayment::create(['customer_id' => $this->customer->id, 'type' => 'customer', 'amount' => 200, 'cheque_date' => today(), 'cheque_number' => 'RETURN-1', 'status' => 'pending']);
        $this->post(route('cheque-payments.return', $cheque))->assertRedirect();
        $this->assertSame('returned', $cheque->fresh()->status);
        $this->assertDatabaseCount('payments', 0);
        $this->assertEquals(0, $this->customer->fresh()->advance_balance);
    }

    public function test_bank_payment_requires_an_active_account_and_credits_the_selected_account(): void
    {
        $url = route('customers.add-advance', $this->customer);
        $data = ['amount' => 300, 'payment_method' => 'bank_transfer', 'payment_date' => today()->toDateString()];
        $this->postJson($url, $data)->assertUnprocessable()->assertJsonValidationErrors('bank_account_id');
        $this->firstBank->update(['is_active' => false]);
        $this->postJson($url, $data + ['bank_account_id' => $this->firstBank->id])->assertUnprocessable()->assertJsonValidationErrors('bank_account_id');
        $this->assertDatabaseCount('payments', 0);
        $this->post($url, $data + ['bank_account_id' => $this->secondBank->id])->assertRedirect();
        $this->assertEquals(300, $this->customer->fresh()->advance_balance);
        $this->assertEquals(300, $this->secondBank->chartAccount->fresh()->current_balance);
        $this->assertDatabaseHas('account_transactions', ['bank_account_id' => $this->secondBank->id, 'account_id' => $this->secondBank->chart_account_id, 'direction' => 'in', 'amount' => 300]);
    }

    public function test_bulk_customer_and_supplier_bank_payments_retain_the_account(): void
    {
        $this->customer->update(['opening_balance' => 500]);
        $data = ['payment_method' => 'bank_transfer', 'bank_account_id' => $this->secondBank->id, 'payment_date' => today()->toDateString(), 'allocations' => [['type' => 'opening_balance', 'amount' => 100]]];
        $this->post(route('customers.bulk-payment.store', $this->customer), $data)->assertRedirect();
        $supplier = Supplier::create(['name' => 'Supplier Example', 'phone' => '0770000000', 'opening_balance' => 500]);
        $this->post(route('suppliers.bulk-payment.store', $supplier), $data)->assertRedirect();
        $this->assertSame(2, Payment::where('bank_account_id', $this->secondBank->id)->count());
        $this->assertSame(2, AccountTransaction::where('bank_account_id', $this->secondBank->id)->count());
        $this->assertEquals(0, $this->secondBank->chartAccount->fresh()->current_balance);
    }

    public function test_expense_uses_selected_bank_and_edit_reverses_the_original_bank(): void
    {
        $category = ExpenseCategory::create(['name' => 'Rent', 'is_active' => true]);
        $data = ['expense_category_id' => $category->id, 'expense_date' => today()->toDateString(), 'amount' => 150, 'payment_method' => 'bank_transfer', 'bank_account_id' => $this->secondBank->id];
        $this->post(route('expenses.store'), $data)->assertRedirect();
        $expense = Expense::firstOrFail();
        $this->assertSame($this->secondBank->id, $expense->bank_account_id);
        $this->assertEquals(-150, $this->secondBank->chartAccount->fresh()->current_balance);
        $data['bank_account_id'] = $this->firstBank->id;
        $this->put(route('expenses.update', $expense), $data)->assertRedirect();
        $this->assertEquals(0, $this->secondBank->chartAccount->fresh()->current_balance);
        $this->assertEquals(-150, $this->firstBank->chartAccount->fresh()->current_balance);
    }

    public function test_purchase_payment_and_own_cheque_use_the_chosen_bank(): void
    {
        $supplier = Supplier::create(['name' => 'Supplier Example', 'phone' => '0770000000']);
        $unit = Unit::create(['name' => 'Piece', 'short_name' => 'pc', 'base_unit_multiplier' => 1, 'is_active' => true]);
        $product = Product::create(['name' => 'Part', 'sku' => 'PART', 'unit_id' => $unit->id, 'cost_price' => 100, 'selling_price' => 200, 'stock_quantity' => 0, 'alert_quantity' => 1, 'is_active' => true]);
        $store = Store::create(['name' => 'Main', 'code' => 'MAIN', 'is_default' => true, 'is_active' => true]);
        $data = ['supplier_id' => $supplier->id, 'store_ids' => [$store->id], 'purchase_date' => today()->toDateString(), 'items' => [['product_id' => $product->id, 'quantity' => 2, 'unit_cost' => 100, 'selling_price' => 200]], 'payments' => [
            ['method' => 'bank_transfer', 'amount' => 100, 'bank_account_id' => $this->secondBank->id],
            ['method' => 'own_cheque', 'amount' => 100, 'bank_account_id' => $this->secondBank->id, 'cheque_number' => 'OWN-123', 'cheque_date' => today()->toDateString(), 'bank_name' => 'Bank Two'],
        ]];
        $this->post(route('purchases.store'), $data)->assertRedirect();
        $purchase = Purchase::firstOrFail();
        $this->assertDatabaseHas('payments', ['purchase_id' => $purchase->id, 'bank_account_id' => $this->secondBank->id, 'amount' => 100]);
        $cheque = ChequePayment::firstOrFail();
        $this->assertSame($this->secondBank->id, $cheque->bank_account_id);
        $this->post(route('cheque-payments.pass', $cheque), ['passed_bank_id' => $this->secondBank->id])->assertRedirect();
        $this->assertEquals(-200, $this->secondBank->chartAccount->fresh()->current_balance);
        $this->assertSame('paid', $purchase->fresh()->payment_status);
    }

    public function test_payment_forms_have_bank_options_and_a_direct_add_bank_link(): void
    {
        $this->get(route('customers.index'))->assertOk()->assertSee('advance-cheque-fields', false)
            ->assertSee('Bank One')->assertSee('Bank Two')->assertSee('#add-bank-account', false);
        $this->post(route('accounting.banks.store'), ['bank_name' => 'New Bank', 'account_name' => 'Business', 'account_number' => '123456'])->assertRedirect();
        $this->get(route('customers.index'))->assertOk()->assertSee('New Bank');
    }

    public function test_sales_payment_and_reversal_keep_the_selected_bank(): void
    {
        $sale = Sale::create(['customer_id' => $this->customer->id, 'user_id' => auth()->id(), 'sale_date' => today(), 'total_amount' => 500, 'due_amount' => 500, 'sale_type' => 'sale']);
        $this->post(route('sales.payment.store'), ['sale_id' => $sale->id, 'amount' => 200, 'payment_method' => 'bank_transfer', 'bank_account_id' => $this->secondBank->id, 'payment_date' => today()->toDateString(), 'notes' => 'Bank receipt'])->assertRedirect();
        $payment = Payment::where('sale_id', $sale->id)->firstOrFail();
        $this->assertSame($this->secondBank->id, $payment->bank_account_id);
        app(\App\Services\SalePaymentAccountingService::class)->reversePayment($payment, $sale, auth()->id());
        $this->assertDatabaseHas('account_transactions', ['source_type' => 'payment_reversal', 'bank_account_id' => $this->secondBank->id]);
        $this->assertEquals(0, $this->secondBank->chartAccount->fresh()->current_balance);
    }

    public function test_preorder_cheque_can_clear_before_a_sale_is_created(): void
    {
        $preorder = PreOrder::create(['pre_order_number' => 'PRE-BANK', 'customer_id' => $this->customer->id, 'pre_order_date' => today(), 'vehicle_name' => 'Toyota', 'grand_total' => 1000, 'due_amount' => 1000, 'created_by' => auth()->id(), 'status' => 'pending']);
        app(\App\Services\PreOrderService::class)->addPayment($preorder, ['amount' => 100, 'payment_method' => 'bank_transfer', 'bank_account_id' => $this->secondBank->id, 'payment_date' => today()->toDateString()], auth()->id());
        app(\App\Services\PreOrderService::class)->addPayment($preorder, ['amount' => 200, 'payment_method' => 'cheque', 'bank_account_id' => $this->secondBank->id, 'payment_date' => today()->toDateString(), 'cheque_number' => 'PRE-CHQ', 'cheque_date' => today()->toDateString()], auth()->id());
        $cheque = ChequePayment::where('pre_order_id', $preorder->id)->firstOrFail();
        $this->post(route('cheque-payments.pass', $cheque), ['passed_bank_id' => $this->secondBank->id])->assertRedirect();
        $this->assertEquals(300, $preorder->fresh()->paid_amount);
        $this->assertEquals(0, $preorder->fresh()->held_cheque_amount);
        $this->assertEquals(300, $this->secondBank->chartAccount->fresh()->current_balance);
        $this->assertSame(2, Payment::where('pre_order_id', $preorder->id)->where('bank_account_id', $this->secondBank->id)->count());
    }

    public function test_pos_split_payments_credit_each_selected_bank(): void
    {
        \App\Models\Setting::set('use_price_wise_stock', false, 'boolean');
        $unit = Unit::create(['name' => 'Piece', 'short_name' => 'pc', 'base_unit_multiplier' => 1, 'is_active' => true]);
        $product = Product::create(['name' => 'POS Part', 'sku' => 'POS-PART', 'unit_id' => $unit->id, 'cost_price' => 50, 'selling_price' => 100, 'stock_quantity' => 5, 'alert_quantity' => 1, 'is_active' => true]);
        $this->withSession(['pos.cart' => ['items' => ['part' => ['id' => $product->id, 'name' => $product->name, 'sku' => $product->sku, 'price' => 100, 'qty' => 1]]]])
            ->postJson(route('pos.checkout'), ['customer_id' => $this->customer->id, 'paid_amount' => 100, 'payment_method' => 'bank_transfer', 'payments' => [
                ['method' => 'bank_transfer', 'amount' => 40, 'bank_account_id' => $this->firstBank->id],
                ['method' => 'bank_transfer', 'amount' => 60, 'bank_account_id' => $this->secondBank->id],
            ]])->assertOk();
        $this->assertEquals(40, $this->firstBank->chartAccount->fresh()->current_balance);
        $this->assertEquals(60, $this->secondBank->chartAccount->fresh()->current_balance);
        $this->assertSame(2, Payment::whereNotNull('bank_account_id')->count());
    }

    public function test_manual_cheque_pass_requires_an_explicit_active_bank_and_uses_it_instead_of_the_suggested_bank(): void
    {
        $cheque = ChequePayment::create(['customer_id' => $this->customer->id, 'type' => 'customer', 'amount' => 200, 'cheque_date' => today(), 'cheque_number' => 'CHOOSE-BANK', 'status' => 'pending', 'bank_account_id' => $this->firstBank->id]);
        $url = route('cheque-payments.pass', $cheque);
        $this->postJson($url)->assertUnprocessable()->assertJsonValidationErrors('passed_bank_id');
        $this->secondBank->update(['is_active' => false]);
        $this->postJson($url, ['passed_bank_id' => $this->secondBank->id])->assertUnprocessable()->assertJsonValidationErrors('passed_bank_id');
        $this->assertSame('pending', $cheque->fresh()->status);
        $this->assertDatabaseCount('payments', 0);
        $this->secondBank->update(['is_active' => true]);
        $this->post($url, ['passed_bank_id' => $this->secondBank->id])->assertRedirect();
        $this->assertSame($this->secondBank->id, $cheque->fresh()->bank_account_id);
        $this->assertEquals(200, app(\App\Services\AccountBalanceService::class)->balance($this->secondBank));
        $this->assertEquals(0, app(\App\Services\AccountBalanceService::class)->balance($this->firstBank));
        $this->get(route('cheque-payments.index'))->assertOk()->assertSee('Deposited to')->assertSee('Bank Two');
    }

    public function test_cheque_popup_lists_active_banks_and_keeps_supplier_endorsement_separate(): void
    {
        $this->firstBank->update(['is_active' => false]);
        $this->get(route('cheque-payments.index'))->assertOk()->assertSee('Which bank received this cheque?')
            ->assertSee('Bank Two')->assertDontSee('Bank One')->assertSee('cheque-payments.js')->assertSee('Add a bank account');
        $sale = Sale::create(['customer_id' => $this->customer->id, 'user_id' => auth()->id(), 'sale_date' => today(), 'total_amount' => 200, 'held_cheque_amount' => 200, 'due_amount' => 0, 'sale_type' => 'sale']);
        $supplier = Supplier::create(['name' => 'Endorsed Supplier', 'phone' => '0771234568', 'opening_balance' => 500]);
        $cheque = ChequePayment::create(['sale_id' => $sale->id, 'customer_id' => $this->customer->id, 'type' => 'customer', 'amount' => 200, 'cheque_date' => today(), 'cheque_number' => 'ENDORSE', 'status' => 'pending']);
        $this->post(route('cheque-payments.pass', $cheque), ['supplier_id' => $supplier->id])->assertRedirect();
        $this->assertSame('passed', $cheque->fresh()->status);
        $this->assertNull($cheque->fresh()->bank_account_id);
        $this->assertEquals(0, app(\App\Services\AccountBalanceService::class)->balance($this->secondBank));
    }

    private function bank(string $code, string $name): BankAccount
    {
        $chart = ChartAccount::create(['code' => $code, 'name' => $name, 'type' => 'asset', 'subtype' => 'bank', 'opening_balance' => 0, 'current_balance' => 0, 'is_active' => true]);

        return BankAccount::create(['chart_account_id' => $chart->id, 'bank_name' => $name, 'account_name' => 'Business', 'account_number' => $code, 'opening_balance' => 0, 'is_active' => true]);
    }
}
