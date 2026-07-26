<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionTaxLine extends Model
{
    protected $guarded = [];

    protected $casts = [
        'original_unit_price' => 'decimal:4',
        'quantity' => 'decimal:4',
        'gross_amount' => 'decimal:4',
        'discount_amount' => 'decimal:4',
        'taxable_amount' => 'decimal:4',
        'tax_rate' => 'decimal:4',
        'vat_amount' => 'decimal:4',
        'total_amount' => 'decimal:4',
        'rounding_adjustment' => 'decimal:4',
        'input_vat_claimable' => 'boolean',
        'setting_snapshot' => 'array',
    ];
}
