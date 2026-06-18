<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code', 'phone', 'address', 'is_default', 'is_active'];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function stocks()
    {
        return $this->hasMany(StoreStock::class);
    }

    protected static function booted()
    {
        static::addGlobalScope('hiddenStores', function (\Illuminate\Database\Eloquent\Builder $builder) {
            if (auth()->hasUser()) {
                $hiddenStoreIds = \App\Services\DashboardVisibilityService::hiddenStoreIdsForUser(auth()->user());
                if (!empty($hiddenStoreIds)) {
                    $builder->whereNotIn('stores.id', $hiddenStoreIds);
                }
            }
        });
    }
}
