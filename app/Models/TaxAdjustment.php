<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxAdjustment extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'adjustment_date' => 'date',
        'amount' => 'decimal:4',
        'approved_at' => 'datetime',
    ];
}
