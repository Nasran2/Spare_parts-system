<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxPayment extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'payment_date' => 'date',
        'output_vat' => 'decimal:4',
        'input_vat' => 'decimal:4',
        'adjustments' => 'decimal:4',
        'previous_balance' => 'decimal:4',
        'payable_amount' => 'decimal:4',
        'paid_amount' => 'decimal:4',
        'remaining_amount' => 'decimal:4',
        'finalized_at' => 'datetime',
    ];
}
