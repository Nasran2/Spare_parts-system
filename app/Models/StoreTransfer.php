<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'transfer_no',
        'from_store_id',
        'to_store_id',
        'transfer_date',
        'reference_no',
        'notes',
        'shipping_cost',
        'additional_expense',
    ];

    protected $casts = [
        'transfer_date' => 'date',
        'shipping_cost' => 'decimal:2',
        'additional_expense' => 'decimal:2',
    ];

    public function fromStore()
    {
        return $this->belongsTo(Store::class, 'from_store_id');
    }

    public function toStore()
    {
        return $this->belongsTo(Store::class, 'to_store_id');
    }

    public function items()
    {
        return $this->hasMany(StoreStockTransfer::class, 'store_transfer_id');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($transfer) {
            if (empty($transfer->transfer_no)) {
                $transfer->transfer_no = 'TRN-'.date('Ymd').'-'.str_pad(static::count() + 1, 4, '0', STR_PAD_LEFT);
            }
        });
    }
}
