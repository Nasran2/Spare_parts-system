<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PreOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'pre_order_id', 'product_id', 'product_price_id', 'original_product_name',
        'description', 'quantity', 'quoted_price', 'final_price', 'discount_type',
        'discount_value', 'gross_amount', 'discount_amount', 'tax_amount',
        'line_total', 'sync_status', 'tax_snapshot', 'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'quoted_price' => 'decimal:2',
        'final_price' => 'decimal:2',
        'discount_value' => 'decimal:2',
        'gross_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'line_total' => 'decimal:2',
        'tax_snapshot' => 'array',
    ];

    public function preOrder()
    {
        return $this->belongsTo(PreOrder::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function productPrice()
    {
        return $this->belongsTo(ProductPrice::class);
    }
}
