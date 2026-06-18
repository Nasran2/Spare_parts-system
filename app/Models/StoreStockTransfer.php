<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreStockTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_transfer_id',
        'from_store_id',
        'to_store_id',
        'product_id',
        'quantity',
        'transfer_date',
        'reference_no',
        'notes',
        'shipping_cost',
        'additional_expense',
    ];

    public function transfer()
    {
        return $this->belongsTo(StoreTransfer::class, 'store_transfer_id');
    }

    protected $casts = [
        'quantity' => 'decimal:3',
        'transfer_date' => 'date',
        'shipping_cost' => 'decimal:2',
        'additional_expense' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function fromStore()
    {
        return $this->belongsTo(Store::class, 'from_store_id');
    }

    public function toStore()
    {
        return $this->belongsTo(Store::class, 'to_store_id');
    }
}
