<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Accounting\ChartAccount;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\TaxLedgerEntry;
use App\Models\TaxPayment;
use App\Models\TaxSetting;
use App\Models\TransactionTaxLine;
use App\Models\Unit;
use App\Models\User;
use App\Services\DecimalMath;
use App\Services\TaxCalculationService;
use App\Services\TaxInvoiceNumberService;
use App\Services\TaxPostingService;
use App\Services\TaxReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TaxManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_posting_is_idempotent_and_historical_snapshot_does_not_change(): void
    {
        [$user, $product, $customer, $settings] = $this->salesFixture();
        $sale = Sale::create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'sale_date' => '2026-07-01',
            'subtotal' => '1180.00',
            'tax' => '180.00',
            'total_amount' => '1180.00',
            'paid_amount' => '0',
            'due_amount' => '1180.00',
            'payment_status' => 'unpaid',
            'payment_method' => 'credit',
            'sale_type' => 'sale',
        ]);
        $item = SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => '1180.00',
            'total' => '1180.00',
        ]);
        $tax = app(TaxCalculationService::class)->calculateLine($this->taxInput('1180.00', 'inclusive'));
        $posting = app(TaxPostingService::class);

        $posting->postSale($sale, [['model' => $item, 'tax' => $tax]], $settings);
        $posting->postSale($sale, [['model' => $item, 'tax' => $tax]], $settings);
        $settings->update(['default_vat_rate' => '20.0000']);

        $this->assertDatabaseCount('transaction_tax_lines', 1);
        $this->assertDatabaseCount('tax_ledger_entries', 1);
        $this->assertDatabaseCount('account_transactions', 1);
        $this->assertSame('18.0000', TransactionTaxLine::first()->tax_rate);
        $this->assertSame('18.0000', (string) Sale::find($sale->id)->tax_snapshot['default_vat_rate']);
        $this->assertSame('180.0000', TaxLedgerEntry::first()->tax_amount);
    }

    public function test_partial_sale_return_uses_original_tax_snapshot(): void
    {
        [$user, $product, $customer, $settings] = $this->salesFixture();
        $sale = Sale::create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'sale_date' => '2026-07-01',
            'subtotal' => '2360.00',
            'tax' => '360.00',
            'total_amount' => '2360.00',
            'paid_amount' => '2360.00',
            'due_amount' => 0,
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'sale_type' => 'sale',
        ]);
        $item = SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => '1180.00',
            'total' => '2360.00',
        ]);
        $tax = app(TaxCalculationService::class)->calculateLine([
            ...$this->taxInput('1180.00', 'inclusive'),
            'quantity' => '2',
        ]);
        app(TaxPostingService::class)->postSale($sale, [['model' => $item, 'tax' => $tax]], $settings);
        $settings->update(['default_vat_rate' => '20.0000']);

        $return = SaleReturn::create([
            'sale_id' => $sale->id,
            'user_id' => $user->id,
            'return_date' => '2026-07-10',
            'total_refund' => '1180.00',
        ]);
        SaleReturnItem::create([
            'sale_return_id' => $return->id,
            'sale_item_id' => $item->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => '1180.00',
            'total' => '1180.00',
        ]);
        app(TaxReturnService::class)->recordSaleReturn($return);

        $line = TransactionTaxLine::where('transaction_type', 'sale_return')->firstOrFail();
        $this->assertSame('-180.0000', $line->vat_amount);
        $this->assertSame('18.0000', $line->tax_rate);
        $this->assertSame('-180.0000', TaxLedgerEntry::where('transaction_type', 'sale_return')->value('tax_amount'));
    }

    public function test_non_claimable_purchase_vat_is_preserved_but_not_deducted_or_posted_to_receivable(): void
    {
        $user = User::factory()->create();
        $unit = Unit::create(['name' => 'Piece', 'short_name' => 'pc', 'base_unit_multiplier' => 1]);
        $product = Product::create(['name' => 'Part', 'sku' => 'PART-2', 'unit_id' => $unit->id]);
        $supplier = Supplier::create(['name' => 'Supplier', 'tin' => '123456789', 'phone' => '0110000000']);
        $settings = $this->enabledSettings();
        $purchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'user_id' => $user->id,
            'purchase_date' => '2026-07-01',
            'total_amount' => '1180.00',
            'paid_amount' => 0,
            'due_amount' => '1180.00',
            'payment_status' => 'unpaid',
            'input_vat_claimable' => false,
            'supplier_tax_invoice_number' => 'SUP-100',
        ]);
        $item = PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_cost' => '1180.00',
            'selling_price' => '1500.00',
            'total' => '1180.00',
        ]);
        $tax = app(TaxCalculationService::class)->calculateLine($this->taxInput('1180.00', 'inclusive'));

        app(TaxPostingService::class)->postPurchase($purchase, [['model' => $item, 'tax' => $tax]], $settings);

        $this->assertSame('180.0000', TransactionTaxLine::first()->vat_amount);
        $this->assertSame('0.0000', TaxLedgerEntry::first()->tax_amount);
        $this->assertDatabaseMissing('account_transactions', ['source_type' => 'tax_input']);
    }

    public function test_tax_invoice_number_is_unique_and_pdf_contains_database_backed_totals(): void
    {
        [$user, $product, $customer, $settings] = $this->salesFixture(true);
        foreach (['shop_name' => 'Fasmir Auto Parts', 'shop_address' => 'Colombo', 'shop_phone' => '0110000000'] as $key => $value) {
            Setting::set($key, $value);
        }
        $sale = Sale::create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'sale_date' => '2026-07-01',
            'subtotal' => '1180.00',
            'tax' => '180.00',
            'total_amount' => '1180.00',
            'paid_amount' => '1180.00',
            'due_amount' => 0,
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'sale_type' => 'sale',
        ]);
        $item = SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => '1180.00',
            'total' => '1180.00',
        ]);
        $tax = app(TaxCalculationService::class)->calculateLine($this->taxInput('1180.00', 'inclusive'));
        app(TaxPostingService::class)->postSale($sale, [['model' => $item, 'tax' => $tax]], $settings);

        $number = app(TaxInvoiceNumberService::class)->issue($sale);
        $this->assertSame('TAX-CMB-100', $number);
        $this->assertSame($number, app(TaxInvoiceNumberService::class)->issue($sale->refresh()));

        $response = $this->actingAs($user)->get(route('sales.tax-invoice', $sale));
        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $html = view('tax.pdf.invoice', [
            'sale' => $sale->fresh(['customer']),
            'settings' => $sale->fresh()->tax_snapshot,
            'shop' => ['name' => 'Fasmir Auto Parts', 'address' => 'Colombo', 'phone' => '0110000000'],
            'rows' => collect([[
                'reference' => 'BRAKE-1',
                'description' => 'Brake Pad',
                'quantity' => 1,
                'unit_price' => '1180.00',
                'taxable_amount' => '1000.00',
            ]]),
            'taxable' => '1000.00',
            'vat' => '180.00',
            'total' => '1180.00',
            'amountWords' => 'One thousand one hundred eighty Sri Lankan Rupees only',
        ])->render();
        $this->assertStringContainsString('1,000.00', $html);
        $this->assertStringContainsString('180.00', $html);
        $this->assertStringContainsString('1,180.00', $html);
        $this->assertSame(
            DecimalMath::parse($sale->fresh()->total_amount),
            DecimalMath::parse(TransactionTaxLine::where('transaction_type', 'sale')->value('total_amount'))
        );

        $settings->refresh()->update(['next_invoice_number' => 100]);
        $other = $sale->replicate(['tax_invoice_number']);
        $other->sale_no = null;
        $other->save();
        $otherItem = SaleItem::create([
            'sale_id' => $other->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => '1180.00',
            'total' => '1180.00',
        ]);
        app(TaxPostingService::class)->postSale(
            $other,
            [['model' => $otherItem, 'tax' => $tax]],
            $settings->fresh()
        );
        $this->expectException(ValidationException::class);
        app(TaxInvoiceNumberService::class)->issue($other);
    }

    public function test_regular_inclusive_invoice_hides_separate_vat_without_changing_total(): void
    {
        [$user, $product, $customer, $settings] = $this->salesFixture(true);
        $sale = Sale::create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'sale_date' => '2026-07-01',
            'subtotal' => '1180.00',
            'tax' => '180.00',
            'tax_snapshot' => [
                ...$settings->snapshot(),
                'customer_invoice_vat_display' => 'hide_inclusive',
                'regular_invoice_vat_note' => false,
            ],
            'total_amount' => '1180.00',
            'paid_amount' => '1180.00',
            'due_amount' => 0,
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'sale_type' => 'sale',
        ]);
        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => '1180.00',
            'total' => '1180.00',
        ]);

        $response = $this->actingAs($user)->get(route('sales.print', $sale));

        $response->assertOk();
        $response->assertDontSee('VAT (18');
        $response->assertDontSee('Prices are inclusive of applicable VAT.');
        $response->assertSee('1,180.00');
    }

    public function test_tax_routes_require_permission(): void
    {
        $role = Role::create(['name' => 'Cashier', 'permissions' => [], 'is_active' => true]);
        $user = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($user)->get(route('tax.dashboard'))->assertForbidden();
        $role->update(['permissions' => ['tax.view']]);
        $user->unsetRelation('role');
        $this->actingAs($user)->get(route('tax.dashboard'))->assertOk();
    }

    public function test_effective_dated_rate_changes_only_apply_on_or_after_the_new_date(): void
    {
        $current = $this->enabledSettings();
        $current->update(['effective_to' => '2026-07-31']);
        TaxSetting::create([
            ...collect($current->getAttributes())->only($current->getFillable())->except([
                'version',
                'effective_from',
                'effective_to',
                'created_by',
            ])->all(),
            'version' => 2,
            'default_vat_rate' => '20.0000',
            'effective_from' => '2026-08-01',
        ]);

        $this->assertSame('18.0000', TaxSetting::current('2026-07-31')->default_vat_rate);
        $this->assertSame('20.0000', TaxSetting::current('2026-08-01')->default_vat_rate);
    }

    public function test_partial_vat_payments_reduce_the_period_balance_and_carry_forward(): void
    {
        $role = Role::create([
            'name' => 'Tax Cashier',
            'permissions' => ['tax.view', 'tax.payment.create'],
            'is_active' => true,
        ]);
        $user = User::factory()->create(['role_id' => $role->id]);
        $account = ChartAccount::create([
            'code' => '1100-TAX-TEST',
            'name' => 'Tax Cash',
            'type' => 'asset',
            'subtype' => 'cash',
            'opening_balance' => 1000,
            'current_balance' => 1000,
            'is_active' => true,
        ]);
        TaxLedgerEntry::create([
            'entry_date' => now()->toDateString(),
            'tax_period' => now()->format('Y-m'),
            'transaction_type' => 'sale',
            'transaction_id' => 1001,
            'invoice_number' => 'OUT-1',
            'direction' => 'output',
            'taxable_amount' => 1000,
            'tax_amount' => 180,
            'status' => 'posted',
            'created_by' => $user->id,
        ]);
        TaxLedgerEntry::create([
            'entry_date' => now()->toDateString(),
            'tax_period' => now()->format('Y-m'),
            'transaction_type' => 'purchase',
            'transaction_id' => 1002,
            'invoice_number' => 'IN-1',
            'direction' => 'input',
            'taxable_amount' => 555.5556,
            'tax_amount' => 100,
            'status' => 'posted',
            'created_by' => $user->id,
        ]);

        foreach ([['VAT-PAY-1', '50'], ['VAT-PAY-2', '30']] as [$reference, $amount]) {
            $this->actingAs($user)->post(route('tax.payments.store'), [
                'tax_period' => now()->format('Y-m'),
                'payment_date' => now()->toDateString(),
                'reference' => $reference,
                'payment_method' => 'cash',
                'account_id' => $account->id,
                'paid_amount' => $amount,
                'finalize' => 1,
            ])->assertRedirect();
        }

        $first = TaxPayment::where('reference', 'VAT-PAY-1')->firstOrFail();
        $second = TaxPayment::where('reference', 'VAT-PAY-2')->firstOrFail();
        $this->assertSame('80.0000', $first->payable_amount);
        $this->assertSame('30.0000', $first->remaining_amount);
        $this->assertSame('30.0000', $second->payable_amount);
        $this->assertSame('0.0000', $second->remaining_amount);
        $this->assertSame(920.0, (float) $account->fresh()->current_balance);
    }

    public function test_tax_report_pdf_and_excel_exports_use_the_same_filtered_rows_and_totals(): void
    {
        $role = Role::create([
            'name' => 'Tax Reporter',
            'permissions' => ['tax.reports.view', 'tax.reports.export'],
            'is_active' => true,
        ]);
        $user = User::factory()->create(['role_id' => $role->id]);
        TaxLedgerEntry::create([
            'entry_date' => now()->toDateString(),
            'tax_period' => now()->format('Y-m'),
            'transaction_type' => 'sale',
            'transaction_id' => 2001,
            'invoice_number' => 'REPORT-1',
            'direction' => 'output',
            'taxable_amount' => '1000.0000',
            'tax_amount' => '180.0000',
            'status' => 'posted',
            'created_by' => $user->id,
        ]);
        $query = [
            'type' => 'output-vat',
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->endOfMonth()->toDateString(),
            'invoice' => 'REPORT-1',
        ];

        $page = $this->actingAs($user)->get(route('tax.reports', $query));
        $page->assertOk()->assertSee('REPORT-1')->assertSee('1,000.00')->assertSee('180.00');

        $pdf = $this->actingAs($user)->get(route('tax.reports.export', [...$query, 'format' => 'pdf']));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $excel = $this->actingAs($user)->get(route('tax.reports.export', [...$query, 'format' => 'xlsx']));
        $excel->assertOk();
        $this->assertStringContainsString('.xlsx', (string) $excel->headers->get('content-disposition'));
        $this->assertStringStartsWith('PK', file_get_contents($excel->baseResponse->getFile()->getPathname()));
    }

    private function salesFixture(bool $withPermission = false): array
    {
        $role = Role::create([
            'name' => 'VAT Manager',
            'permissions' => $withPermission ? ['tax.invoice.print', 'tax.view', 'sales.view'] : [],
            'is_active' => true,
        ]);
        $user = User::factory()->create(['role_id' => $role->id]);
        $unit = Unit::create(['name' => 'Piece', 'short_name' => 'pc', 'base_unit_multiplier' => 1]);
        $product = Product::create(['name' => 'Brake Pad', 'sku' => 'BRAKE-1', 'unit_id' => $unit->id]);
        $customer = Customer::create([
            'name' => 'VAT Customer',
            'tin' => '987654321',
            'phone' => '0770000000',
            'address' => 'Kandy',
        ]);

        return [$user, $product, $customer, $this->enabledSettings()];
    }

    private function enabledSettings(): TaxSetting
    {
        $settings = TaxSetting::query()->firstOrFail();
        $settings->update([
            'vat_enabled' => true,
            'vat_registered' => true,
            'supplier_tin' => '123456789',
            'default_vat_rate' => '18.0000',
            'default_sale_price_mode' => 'inclusive',
            'default_purchase_price_mode' => 'inclusive',
            'invoice_prefix' => 'TAX',
            'branch_code' => 'CMB',
            'starting_invoice_number' => 100,
            'next_invoice_number' => 100,
            'effective_from' => '2025-01-01',
        ]);

        return $settings->fresh();
    }

    private function taxInput(string $price, string $mode): array
    {
        return [
            'unit_price' => $price,
            'quantity' => '1',
            'tax_status' => 'standard',
            'vat_rate' => '18',
            'price_mode' => $mode,
            'vat_allowed' => true,
            'vat_enabled' => true,
        ];
    }
}
