<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OwnerEquityMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'owner_name',
        'movement_date',
        'payment_account',
        'amount',
        'reference_no',
        'note',
        'asset_account_id',
        'equity_account_id',
        'asset_transaction_id',
        'equity_transaction_id',
        'user_id',
    ];

    protected $casts = [
        'movement_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function assetAccount()
    {
        return $this->belongsTo(ChartAccount::class, 'asset_account_id');
    }

    public function equityAccount()
    {
        return $this->belongsTo(ChartAccount::class, 'equity_account_id');
    }

    public function assetTransaction()
    {
        return $this->belongsTo(AccountTransaction::class, 'asset_transaction_id');
    }

    public function equityTransaction()
    {
        return $this->belongsTo(AccountTransaction::class, 'equity_transaction_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
