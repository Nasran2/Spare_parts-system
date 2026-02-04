<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_no',
        'customer_id',
        'user_id',
        'sale_date',
        'subtotal',
        'tax',
        'discount',
        'total_amount',
        'paid_amount',
        'due_amount',
        'payment_status',
        'payment_method',
        'sale_type',
        'notes',
    ];

    protected $casts = [
        'sale_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_amount' => 'decimal:2',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function returns()
    {
        return $this->hasMany(SaleReturn::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($sale) {
            if (empty($sale->sale_no)) {
                $sale->sale_no = static::generateNumber($sale->sale_type ?? 'sale');
            }
        });
    }

    /**
     * Generate next number with separate daily sequence per sale_type.
     * Format: PREFIX-YYYYMMDD-#### where #### resets each day per type.
     */
    public static function generateNumber(string $type): string
    {
        $prefix = $type === 'quotation' ? 'QUO' : 'INV';
        $date = date('Ymd');
        // Count existing of this type today for sequence
        $count = static::where('sale_type', $type)->whereDate('created_at', date('Y-m-d'))->count() + 1;
        return sprintf('%s-%s-%s', $prefix, $date, str_pad($count, 4, '0', STR_PAD_LEFT));
    }
}
