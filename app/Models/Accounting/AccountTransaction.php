<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'related_account_id',
        'bank_account_id',
        'user_id',
        'transaction_date',
        'direction',
        'payment_method',
        'amount',
        'cheque_number',
        'reference_no',
        'source_type',
        'source_id',
        'description',
        'is_reconciled',
        'reconciled_at',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'decimal:2',
        'is_reconciled' => 'boolean',
        'reconciled_at' => 'datetime',
    ];

    public function account()
    {
        return $this->belongsTo(ChartAccount::class, 'account_id');
    }

    public function relatedAccount()
    {
        return $this->belongsTo(ChartAccount::class, 'related_account_id');
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
