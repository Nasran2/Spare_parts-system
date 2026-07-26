<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxType extends Model
{
    protected $fillable = [
        'name', 'code', 'rate', 'is_enabled', 'effective_from', 'effective_to', 'created_by',
    ];

    protected $casts = [
        'rate' => 'decimal:4',
        'is_enabled' => 'boolean',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];
}
