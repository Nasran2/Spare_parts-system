<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreStock extends Model
{
    use HasFactory;

    protected $fillable = ['store_id', 'product_id', 'product_price_id', 'quantity'];

    protected $casts = [
        'quantity' => 'decimal:3',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    protected static function booted()
    {
        static::addGlobalScope('hiddenStores', function (\Illuminate\Database\Eloquent\Builder $builder) {
            if (auth()->hasUser()) {
                $hiddenStoreIds = \App\Services\DashboardVisibilityService::hiddenStoreIdsForUser(auth()->user());
                if (!empty($hiddenStoreIds)) {
                    $builder->where(function ($q) use ($hiddenStoreIds) {
                        $q->whereNotIn('store_stocks.store_id', $hiddenStoreIds)
                          ->orWhereNull('store_stocks.store_id');
                    });
                }
            }
        });
    }
}
