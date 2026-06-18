<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_no',
        'reference_no',
        'supplier_id',
        'store_id',
        'stock_shipment_id',
        'user_id',
        'purchase_date',
        'status',
        'discount_type',
        'discount_amount',
        'tax_id',
        'tax_amount',
        'shipping_cost',
        'shipping_type',
        'payment_method',
        'cheque_number',
        'bank_reference',
        'total_amount',
        'paid_amount',
        'due_amount',
        'payment_status',
        'document_path',
        'notes',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function store()
    {
        return $this->belongsTo(\App\Models\Store::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($purchase) {
            if (empty($purchase->purchase_no)) {
                $purchase->purchase_no = 'PUR-'.date('Ymd').'-'.str_pad(static::count() + 1, 4, '0', STR_PAD_LEFT);
            }
        });

        static::addGlobalScope('hiddenStores', function (\Illuminate\Database\Eloquent\Builder $builder) {
            if (auth()->hasUser()) {
                $hiddenStoreIds = \App\Services\DashboardVisibilityService::hiddenStoreIdsForUser(auth()->user());
                if (!empty($hiddenStoreIds)) {
                    $builder->where(function ($q) use ($hiddenStoreIds) {
                        $q->whereNotIn('purchases.store_id', $hiddenStoreIds)
                          ->orWhereNull('purchases.store_id');
                    });
                }
            }
        });
    }
}
