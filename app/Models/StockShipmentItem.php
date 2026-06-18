<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockShipmentItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_shipment_id',
        'product_id',
        'quantity',
        'unit_cost',
        'landed_unit_cost',
        'selling_price',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_cost' => 'decimal:2',
        'landed_unit_cost' => 'decimal:2',
        'selling_price' => 'decimal:2',
    ];

    public function shipment()
    {
        return $this->belongsTo(StockShipment::class, 'stock_shipment_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function allocations()
    {
        return $this->hasMany(StockShipmentAllocation::class);
    }
}
