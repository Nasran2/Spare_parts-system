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

    /**
     * Get the total due amount for this customer
     */
    public function getDueAmountAttribute()
    {
        $salesDue = $this->sales()->sum('due_amount') ?? 0;
        $genericPayments = $this->payments()->whereNull('sale_id')->sum('amount') ?? 0;
        return $salesDue + ($this->opening_balance ?? 0) - $genericPayments;
    }
}
