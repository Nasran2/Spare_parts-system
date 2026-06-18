<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_id',
        'supplier_id',
        'sale_id',
        'customer_id',
        'amount',
        'payment_method',
        'payment_date',
        'notes',
        'reference_no',
        'store_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    protected static function booted()
    {
        static::addGlobalScope('hiddenStores', function (\Illuminate\Database\Eloquent\Builder $builder) {
            if (auth()->hasUser()) {
                $hiddenStoreIds = \App\Services\DashboardVisibilityService::hiddenStoreIdsForUser(auth()->user());
                if (!empty($hiddenStoreIds)) {
                    $builder->where(function ($q) use ($hiddenStoreIds) {
                        $q->whereNotIn('payments.store_id', $hiddenStoreIds)
                          ->orWhereNull('payments.store_id');
                    });
                }
            }
        });
    }
}