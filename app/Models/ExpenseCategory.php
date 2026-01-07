<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExpenseCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'is_active',
        'limit',
        'reset_frequency',
        'reset_date',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'limit' => 'decimal:2',
        'reset_date' => 'integer',
    ];

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }
}
