<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxLedgerEntry extends Model
{
    protected $guarded = [];

    protected $casts = [
        'entry_date' => 'date',
        'taxable_amount' => 'decimal:4',
        'tax_amount' => 'decimal:4',
        'adjustment_amount' => 'decimal:4',
    ];
}
