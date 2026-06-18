<?php

namespace Tests\Feature;

use App\Models\Accounting\AccountTransaction;
use App\Models\Accounting\ChartAccount;
use App\Models\ChequePayment;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;
use App\Services\ChequePaymentService;
use App\Services\SalePaymentAccountingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalePaymentAccountingTest extends TestCase
{
    use RefreshDatabase;

    public function test_mixed_cash_and_cheque_payment_holds_cheque_then_releases_it_to_bank_when_passed(): void
    {
        $user = User::factory()->create();
        $customer = Customer::create([
            'name' => 'Nasran',
            'phone' => '',
            'is_active' => true,
        ]);
        $sale = Sale::create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'sale_date' => now()->toDateString(),
            'subtotal' => 4150,
            'total_amount' => 4150,
            'paid_amount' => 1150,
            'held_cheque_amount' => 3000,
            'tendered_amount' => 4150,
            'due_amount' => 0,
            'payment_status' => 'partial',
            'payment_method' => 'cheque',
        ]);
        $cashPayment = Payment::create([
            'sale_id' => $sale->id,
            'customer_id' => $customer->id,
            'amount' => 1150,
            'payment_method' => 'cash',
            'payment_date' => now()->toDateString(),
        ]);
        $cheque = ChequePayment::create([
            'sale_id' => $sale->id,
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'cheque_date' => now()->addDay()->toDateString(),
            'cheque_number' => '56789',
            'bank_name' => 'COM Bank',
            'account_name' => 'Nasran',
            'amount' => 3000,
            'status' => 'pending',
        ]);

        $accounting = app(SalePaymentAccountingService::class);
        $accounting->recordSalePayment($cashPayment, $sale, $user->id);
        $accounting->recordChequeHold($cheque, $sale, $user->id);

        $this->assertSame(1150.0, (float) ChartAccount::where('code', '1100')->value('current_balance'));
        $this->assertSame(3000.0, (float) ChartAccount::where('code', '1300')->value('current_balance'));

        app(ChequePaymentService::class)->pass($cheque, $user->id);
        $sale->refresh();

        $this->assertSame(4150.0, (float) $sale->paid_amount);
        $this->assertSame(0.0, (float) $sale->held_cheque_amount);
        $this->assertSame(0.0, (float) $sale->due_amount);
        $this->assertSame('paid', $sale->payment_status);
        $this->assertSame(3000.0, (float) ChartAccount::where('code', '1200')->value('current_balance'));
        $this->assertSame(0.0, (float) ChartAccount::where('code', '1300')->value('current_balance'));
        $this->assertTrue(AccountTransaction::where('source_type', 'payment')->where('source_id', $cashPayment->id)->exists());
        $this->assertTrue(AccountTransaction::where('source_type', 'cheque_payment_hold')->where('source_id', $cheque->id)->exists());
        $this->assertTrue(AccountTransaction::where('source_type', 'cheque_payment')->where('source_id', $cheque->id)->exists());
        $this->assertTrue(Payment::where('sale_id', $sale->id)->where('payment_method', 'cheque')->where('amount', 3000)->exists());
    }

    public function test_returned_held_cheque_becomes_customer_due_without_moving_money_to_bank(): void
    {
        $user = User::factory()->create();
        $customer = Customer::create([
            'name' => 'Nasran',
            'phone' => '',
            'is_active' => true,
        ]);
        $sale = Sale::create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'sale_date' => now()->toDateString(),
            'subtotal' => 4150,
            'total_amount' => 4150,
            'paid_amount' => 1150,
            'held_cheque_amount' => 3000,
            'tendered_amount' => 4150,
            'due_amount' => 0,
            'payment_status' => 'partial',
            'payment_method' => 'cheque',
        ]);
        $cheque = ChequePayment::create([
            'sale_id' => $sale->id,
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'cheque_date' => now()->addDay()->toDateString(),
            'cheque_number' => '56790',
            'bank_name' => 'COM Bank',
            'account_name' => 'Nasran',
            'amount' => 3000,
            'status' => 'pending',
        ]);

        app(SalePaymentAccountingService::class)->recordChequeHold($cheque, $sale, $user->id);
        app(ChequePaymentService::class)->return($cheque, $user->id);
        $sale->refresh();

        $this->assertSame(1150.0, (float) $sale->paid_amount);
        $this->assertSame(0.0, (float) $sale->held_cheque_amount);
        $this->assertSame(3000.0, (float) $sale->due_amount);
        $this->assertSame('partial', $sale->payment_status);
        $this->assertSame('returned', ChequePayment::find($cheque->id)->status);
        $this->assertSame(0.0, (float) (ChartAccount::where('code', '1200')->value('current_balance') ?? 0));
    }
}
