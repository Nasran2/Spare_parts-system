<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'expense_category_id',
        'user_id',
        'expense_date',
        'amount',
        'payment_method',
        'description',
        'receipt',
        'store_id',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted()
    {
        static::addGlobalScope('hiddenStores', function (\Illuminate\Database\Eloquent\Builder $builder) {
            if (auth()->hasUser()) {
                $hiddenStoreIds = \App\Services\DashboardVisibilityService::hiddenStoreIdsForUser(auth()->user());
                if (!empty($hiddenStoreIds)) {
                    $builder->where(function ($q) use ($hiddenStoreIds) {
                        $q->whereNotIn('expenses.store_id', $hiddenStoreIds)
                          ->orWhereNull('expenses.store_id');
                    });
                }
            }
        });
    }
}
