<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PettyCashFund extends Model
{
    use HasFactory;

    protected $fillable = [
        'chart_account_id',
        'name',
        'opening_balance',
        'current_balance',
        'is_active',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function chartAccount()
    {
        return $this->belongsTo(ChartAccount::class, 'chart_account_id');
    }

    public function expenses()
    {
        return $this->hasMany(PettyCashExpense::class);
    }
}
