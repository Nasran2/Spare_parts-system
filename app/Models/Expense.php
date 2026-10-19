<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_account_id',
        'expense_category_id',
        'user_id',
        'expense_date',
        'amount',
        'payment_method',
        'description',
        'receipt',
        'store_id',
    ];

    public function bankAccount()
    {
        return $this->belongsTo(\App\Models\Accounting\BankAccount::class);
    }

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
        static::saving(function (self $expense) {
            if (! $expense->exists || $expense->isDirty(['payment_method', 'bank_account_id'])) {
                $expense->bank_account_id = \App\Support\PaymentBankAccount::resolve(
                    $expense->payment_method ?? 'cash', $expense->bank_account_id
                )?->id;
            }
        });

        static::addGlobalScope('hiddenStores', function (\Illuminate\Database\Eloquent\Builder $builder) {
            if (auth()->hasUser()) {
                $hiddenStoreIds = \App\Services\DashboardVisibilityService::hiddenStoreIdsForUser(auth()->user());
                if (! empty($hiddenStoreIds)) {
                    $builder->where(function ($q) use ($hiddenStoreIds) {
                        $q->whereNotIn('expenses.store_id', $hiddenStoreIds)
                            ->orWhereNull('expenses.store_id');
                    });
                }
            }
        });
    }
}
