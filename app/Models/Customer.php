<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'tin',
        'email',
        'phone',
        'address',
        'city',
        'country',
        'opening_balance',
        'is_active',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function preOrders()
    {
        return $this->hasMany(PreOrder::class);
    }

    /**
     * Get the total due amount for this customer
     */
    public function getDueAmountAttribute()
    {
        return $this->sales_due_amount + $this->pre_order_due_amount;
    }

    public function getSalesDueAmountAttribute()
    {
        $salesDue = $this->sales()->sum('due_amount') ?? 0;
        $genericPayments = $this->payments()->whereNull('sale_id')->whereNull('pre_order_id')->sum('amount') ?? 0;
        
        $rawDue = $salesDue + ($this->opening_balance ?? 0) - $genericPayments;
        return max(0, $rawDue);
    }

    public function getAdvanceBalanceAttribute()
    {
        $salesDue = $this->sales()->sum('due_amount') ?? 0;
        $genericPayments = $this->payments()->whereNull('sale_id')->whereNull('pre_order_id')->sum('amount') ?? 0;
        
        $rawDue = $salesDue + ($this->opening_balance ?? 0) - $genericPayments;
        return $rawDue < 0 ? abs($rawDue) : 0;
    }

    public function getPreOrderDueAmountAttribute()
    {
        return $this->preOrders()->where('status', 'pending')->sum('due_amount') ?? 0;
    }
}
