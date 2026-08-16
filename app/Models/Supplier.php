<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'tin',
        'company_name',
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


    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get the total due amount for this supplier
     */
    public function getDueAmountAttribute()
    {
        $purchasesDue = $this->purchases()->sum('due_amount') ?? 0;
        $genericPayments = $this->payments()->whereNull('purchase_id')->sum('amount') ?? 0;
        return $purchasesDue + ($this->opening_balance ?? 0) - $genericPayments;
    }
}
