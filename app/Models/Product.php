<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'sku',
        'category_id',
        'brand_id',
        'unit_id',
        'visible_units',
        'description',
        'cost_price',
        'selling_price',
        'stock_quantity',
        'alert_quantity',
        'image',
        'barcode',
        'is_active',
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'stock_quantity' => 'integer',
        'alert_quantity' => 'integer',
        'is_active' => 'boolean',
        'base_unit' => 'string',
        'unit_factors' => 'array',
        'visible_units' => 'array',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'category_product');
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function brands()
    {
        return $this->belongsToMany(Brand::class, 'brand_product');
    }

    /**
     * Get conversion factor for a unit relative to base unit.
     */
    public function unitFactor(string $unitCode): float
    {
        $factors = $this->unit_factors ?? [];
        if (isset($factors[$unitCode])) {
            return (float)$factors[$unitCode];
        }
        // base unit defaults to 1
        if ($unitCode === ($this->base_unit ?? '')) {
            return 1.0;
        }
        return 1.0;
    }

    /**
     * Convert a price from base to target unit.
     */
    public function priceForUnit(float $basePrice, string $unitCode): float
    {
        return round($basePrice * $this->unitFactor($unitCode), 2);
    }

    /**
     * Derive base price from a target unit price.
     */
    public function basePriceFromUnit(float $unitPrice, string $unitCode): float
    {
        $factor = $this->unitFactor($unitCode);
        if ($factor <= 0) return $unitPrice;
        return round($unitPrice / $factor, 2);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function purchaseItems()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function isLowStock()
    {
        return $this->stock_quantity <= $this->alert_quantity;
    }
}
