<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'chart_account_id',
        'bank_name',
        'account_name',
        'account_number',
        'opening_balance',
        'statement_balance',
        'is_active',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'statement_balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function chartAccount()
    {
        return $this->belongsTo(ChartAccount::class, 'chart_account_id');
    }

    public function reconciliations()
    {
        return $this->hasMany(BankReconciliation::class);
    }
}
