<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\Setting;
use App\Models\TaxSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaxInvoiceNumberService
{
    public function issue(Sale $sale): string
    {
        if ($sale->tax_invoice_number) {
            $snapshotVersion = data_get($sale->tax_snapshot, 'version');
            $settings = $snapshotVersion
                ? TaxSetting::query()->where('version', $snapshotVersion)->first()
                : TaxSetting::current($sale->sale_date);
            $this->assertEligible($sale, $settings ?? TaxSetting::current($sale->sale_date));

            return $sale->tax_invoice_number;
        }

        return DB::transaction(function () use ($sale) {
            $snapshotVersion = data_get($sale->tax_snapshot, 'version');
            $baseSettings = $snapshotVersion
                ? TaxSetting::query()->where('version', $snapshotVersion)->first()
                : TaxSetting::current($sale->sale_date);
            $settings = TaxSetting::query()
                ->whereKey($baseSettings?->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertEligible($sale, $settings);

            $parts = array_filter([
                trim((string) $settings->invoice_prefix),
                trim((string) $settings->branch_code),
                (string) $settings->next_invoice_number,
            ], fn ($part) => $part !== '');
            $number = implode('-', $parts);
            if (preg_match('/\s/', $number)) {
                throw ValidationException::withMessages([
                    'tax_invoice_number' => 'Tax Invoice numbers cannot contain spaces.',
                ]);
            }
            if (Sale::query()->where('tax_invoice_number', $number)->exists()) {
                throw ValidationException::withMessages([
                    'tax_invoice_number' => 'The generated Tax Invoice number already exists.',
                ]);
            }

            $settings->increment('next_invoice_number');
            $sale->forceFill([
                'tax_invoice_number' => $number,
                'tax_template_version' => $settings->active_template_version,
            ])->save();

            return $number;
        });
    }

    public function assertEligible(Sale $sale, TaxSetting $settings): void
    {
        $sale->loadMissing(['customer', 'taxLines']);
        $errors = [];

        if (! $settings->vat_enabled) {
            $errors[] = 'VAT is disabled.';
        }
        if (! $settings->vat_registered) {
            $errors[] = 'The business is not marked as VAT registered.';
        }
        if (! trim((string) $settings->supplier_tin)) {
            $errors[] = 'The business Supplier TIN is missing.';
        }
        if (! trim((string) Setting::get('shop_name', ''))
            || ! trim((string) Setting::get('shop_address', ''))
            || ! trim((string) Setting::get('shop_phone', ''))) {
            $errors[] = 'Supplier name, address and telephone number are required in Business Settings.';
        }
        if (! $sale->customer || ! trim((string) $sale->customer->tin)) {
            $errors[] = 'Purchaser TIN is required for an official Tax Invoice.';
        }
        if (! $sale->customer
            || ! trim((string) $sale->customer->name)
            || ! trim((string) $sale->customer->address)
            || ! trim((string) $sale->customer->phone)) {
            $errors[] = 'Purchaser name, address and telephone number are required.';
        }
        if (! $sale->sale_date) {
            $errors[] = 'Invoice date is missing.';
        }
        if ($sale->sale_type !== 'sale') {
            $errors[] = 'Only completed sales can have an official Tax Invoice.';
        }
        $standardVat = 0;
        $storedVat = 0;
        $storedTotal = 0;
        foreach ($sale->taxLines->where('tax_status', 'standard') as $line) {
            $standardVat += DecimalMath::parse($line->vat_amount);
        }
        foreach ($sale->taxLines as $line) {
            $storedVat += DecimalMath::parse($line->vat_amount);
            $storedTotal += DecimalMath::parse($line->total_amount);
        }
        if ($standardVat <= 0) {
            $errors[] = 'The transaction has no VAT-applicable supply.';
        }
        if (abs($storedVat - DecimalMath::parse($sale->tax)) > 100
            || abs($storedTotal - DecimalMath::parse($sale->total_amount)) > 100) {
            $errors[] = 'Stored VAT totals do not reconcile with the completed sale.';
        }

        if ($errors) {
            throw ValidationException::withMessages(['tax_invoice' => implode(' ', $errors)]);
        }
    }
}
