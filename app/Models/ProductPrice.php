<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'cost_price',
        'selling_price',
        'stock_qty',
        'is_default',
        'status',
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'stock_qty' => 'decimal:3',
        'is_default' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function purchaseItems()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public static function ensureDefaultForProduct(int $productId): void
    {
        $default = static::query()
            ->where('product_id', $productId)
            ->active()
            ->where('is_default', true)
            ->oldest()
            ->first();

        if ($default) {
            static::query()
                ->where('product_id', $productId)
                ->where('id', '!=', $default->id)
                ->update(['is_default' => false]);

            return;
        }

        $fallback = static::query()
            ->where('product_id', $productId)
            ->active()
            ->oldest()
            ->first();

        if ($fallback) {
            $fallback->update(['is_default' => true]);
        }
    }
}
