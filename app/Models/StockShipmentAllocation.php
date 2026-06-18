<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockShipmentAllocation extends Model
{
    use HasFactory;

    protected $fillable = ['stock_shipment_item_id', 'store_id', 'quantity'];

    protected $casts = [
        'quantity' => 'decimal:3',
    ];
}
