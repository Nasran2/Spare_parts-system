<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockShipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_no',
        'grn_no',
        'supplier_id',
        'shipment_date',
        'received_date',
        'status',
        'freight_cost',
        'duty_cost',
        'other_cost',
        'notes',
    ];

    protected $casts = [
        'shipment_date' => 'date',
        'received_date' => 'date',
        'freight_cost' => 'decimal:2',
        'duty_cost' => 'decimal:2',
        'other_cost' => 'decimal:2',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(StockShipmentItem::class);
    }
}
