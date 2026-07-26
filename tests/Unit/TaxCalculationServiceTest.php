<?php

namespace Tests\Unit;

use App\Services\TaxCalculationService;
use PHPUnit\Framework\TestCase;

class TaxCalculationServiceTest extends TestCase
{
    private TaxCalculationService $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new TaxCalculationService;
    }

    public function test_vat_inclusive_amount_is_not_taxed_twice(): void
    {
        $line = $this->line('1180.00', 'inclusive');

        $this->assertSame('1000.0000', $line['taxable_amount']);
        $this->assertSame('180.0000', $line['vat_amount']);
        $this->assertSame('1180.0000', $line['total_amount']);
    }

    public function test_vat_exclusive_amount_adds_vat_once(): void
    {
        $line = $this->line('1000.00', 'exclusive');

        $this->assertSame('1000.0000', $line['taxable_amount']);
        $this->assertSame('180.0000', $line['vat_amount']);
        $this->assertSame('1180.0000', $line['total_amount']);
    }

    public function test_disabled_zero_rated_and_exempt_lines_do_not_generate_vat(): void
    {
        $disabled = $this->line('100.00', 'exclusive', ['vat_enabled' => false]);
        $zeroRated = $this->line('100.00', 'exclusive', ['tax_status' => 'zero_rated', 'vat_rate' => '0']);
        $exempt = $this->line('100.00', 'exclusive', ['tax_status' => 'exempt']);

        $this->assertSame('0.0000', $disabled['vat_amount']);
        $this->assertSame('100.0000', $disabled['total_amount']);
        $this->assertSame('0.0000', $zeroRated['vat_amount']);
        $this->assertSame('zero_rated', $zeroRated['tax_status']);
        $this->assertSame('0.0000', $exempt['vat_amount']);
        $this->assertSame('exempt', $exempt['tax_status']);
    }

    public function test_fixed_percentage_and_bill_discounts_are_applied_before_vat(): void
    {
        $fixed = $this->calculator->calculateLine([
            ...$this->base('100.00', 'exclusive'),
            'quantity' => '2',
            'line_discount_type' => 'fixed',
            'line_discount_value' => '20.00',
        ]);
        $percentage = $this->calculator->calculateLine([
            ...$this->base('100.00', 'exclusive'),
            'quantity' => '2',
            'line_discount_type' => 'percent',
            'line_discount_value' => '10',
        ]);
        $invoice = $this->calculator->calculateInvoice([
            $this->base('100.00', 'exclusive'),
            $this->base('100.00', 'exclusive'),
        ], 'fixed', '20.00');

        foreach ([$fixed, $percentage] as $line) {
            $this->assertSame('180.0000', $line['taxable_amount']);
            $this->assertSame('32.4000', $line['vat_amount']);
            $this->assertSame('212.4000', $line['total_amount']);
        }
        $this->assertSame('180.00', $invoice['totals']['taxable']);
        $this->assertSame('32.40', $invoice['totals']['vat']);
        $this->assertSame('212.40', $invoice['totals']['total']);
    }

    public function test_mixed_taxable_and_exempt_invoice_and_difficult_rounding_reconcile(): void
    {
        $mixed = $this->calculator->calculateInvoice([
            $this->base('99.99', 'exclusive'),
            [...$this->base('333.33', 'inclusive'), 'quantity' => '3'],
            [...$this->base('50.00', 'exclusive'), 'tax_status' => 'exempt'],
        ], 'percent', '7.5');

        $sum = array_sum(array_column($mixed['lines'], '_total_minor'));

        $this->assertSame($mixed['totals']['_total_minor'], $sum);
        $this->assertSame(
            $mixed['totals']['_total_minor'],
            $mixed['totals']['_taxable_minor'] + $mixed['totals']['_vat_minor']
        );
        $this->assertSame('exempt', $mixed['lines'][2]['tax_status']);
        $this->assertSame('0.0000', $mixed['lines'][2]['vat_amount']);
    }

    private function line(string $price, string $mode, array $overrides = []): array
    {
        return $this->calculator->calculateLine([
            ...$this->base($price, $mode),
            ...$overrides,
        ]);
    }

    private function base(string $price, string $mode): array
    {
        return [
            'unit_price' => $price,
            'quantity' => '1',
            'line_discount_type' => 'fixed',
            'line_discount_value' => '0',
            'tax_status' => 'standard',
            'vat_rate' => '18',
            'price_mode' => $mode,
            'vat_allowed' => true,
            'vat_enabled' => true,
        ];
    }
}
