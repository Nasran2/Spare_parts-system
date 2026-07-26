<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class TaxSetting extends Model
{
    protected $fillable = [
        'version',
        'vat_enabled',
        'vat_registered',
        'supplier_tin',
        'default_vat_rate',
        'default_sale_price_mode',
        'default_purchase_price_mode',
        'customer_invoice_vat_display',
        'regular_invoice_vat_note',
        'allow_product_override',
        'invoice_prefix',
        'starting_invoice_number',
        'next_invoice_number',
        'branch_code',
        'active_template_version',
        'effective_from',
        'effective_to',
        'created_by',
    ];

    protected $casts = [
        'vat_enabled' => 'boolean',
        'vat_registered' => 'boolean',
        'default_vat_rate' => 'decimal:4',
        'regular_invoice_vat_note' => 'boolean',
        'allow_product_override' => 'boolean',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public static function current(Carbon|string|null $date = null): self
    {
        $date = $date ? Carbon::parse($date)->toDateString() : now()->toDateString();

        return static::query()
            ->whereDate('effective_from', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date);
            })
            ->orderByDesc('effective_from')
            ->orderByDesc('version')
            ->first()
            ?? static::query()->orderByDesc('version')->firstOrFail();
    }

    public function snapshot(): array
    {
        return [
            'version' => $this->version,
            'vat_enabled' => $this->vat_enabled,
            'vat_registered' => $this->vat_registered,
            'supplier_tin' => $this->supplier_tin,
            'default_vat_rate' => $this->default_vat_rate,
            'default_sale_price_mode' => $this->default_sale_price_mode,
            'default_purchase_price_mode' => $this->default_purchase_price_mode,
            'customer_invoice_vat_display' => $this->customer_invoice_vat_display,
            'regular_invoice_vat_note' => $this->regular_invoice_vat_note,
            'invoice_prefix' => $this->invoice_prefix,
            'branch_code' => $this->branch_code,
            'template_version' => $this->active_template_version,
            'effective_from' => $this->effective_from?->toDateString(),
        ];
    }
}
