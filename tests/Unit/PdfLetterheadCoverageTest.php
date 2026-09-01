<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PdfLetterheadCoverageTest extends TestCase
{
    #[DataProvider('businessPdfViews')]
    public function test_business_pdf_views_use_the_shared_letterhead(string $relativePath): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/'.$relativePath);

        $this->assertIsString($contents);
        $this->assertStringContainsString("pdf.partials.letterhead", $contents, $relativePath);
        $this->assertStringContainsString("'documentTitle'", $contents, $relativePath);
    }

    public function test_letterhead_uses_business_identity_and_pdf_appearance_settings(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/pdf/partials/letterhead.blade.php');

        foreach ([
            'shop_name',
            'shop_tagline',
            'shop_address',
            'shop_phone',
            'shop_email',
            'shop_logo',
            'preorder_pdf_line_color',
            'preorder_pdf_text_color',
            'preorder_pdf_heading_color',
            'preorder_pdf_logo_shape',
        ] as $setting) {
            $this->assertStringContainsString("'{$setting}'", $contents);
        }

        $this->assertStringContainsString('$documentReference', $contents);
        $this->assertStringContainsString('$documentMeta', $contents);
    }

    public static function businessPdfViews(): array
    {
        return array_map(static fn (string $path): array => [$path], [
            'resources/views/reports/pdf/customer-due.blade.php',
            'resources/views/reports/pdf/daily_ledger.blade.php',
            'resources/views/reports/pdf/debit.blade.php',
            'resources/views/reports/pdf/due-bills.blade.php',
            'resources/views/reports/pdf/expense.blade.php',
            'resources/views/reports/pdf/profit-loss.blade.php',
            'resources/views/reports/pdf/purchase.blade.php',
            'resources/views/reports/pdf/receive.blade.php',
            'resources/views/reports/pdf/sales.blade.php',
            'resources/views/reports/pdf/stock.blade.php',
            'resources/views/reports/pdf/vat-day.blade.php',
            'resources/views/reports/pdf/vat.blade.php',
            'resources/views/customers/pdf-ledger.blade.php',
            'resources/views/customers/pdf-payments.blade.php',
            'resources/views/customers/pdf-preorders-detailed.blade.php',
            'resources/views/accounting/export.blade.php',
            'resources/views/accounting/export_cashbook.blade.php',
            'resources/views/sales/export_pdf.blade.php',
            'resources/views/quotations/pdf.blade.php',
            'resources/views/preorders/pdf.blade.php',
            'resources/views/tax/pdf/report.blade.php',
        ]);
    }
}
