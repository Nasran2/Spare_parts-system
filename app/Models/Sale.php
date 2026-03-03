<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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
        'tendered_amount',
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
        'tendered_amount' => 'decimal:2',
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

    public function exchangeReturns()
    {
        return $this->hasMany(SaleReturn::class, 'exchange_sale_id');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($sale) {
            if (empty($sale->sale_no)) {
                $forDate = null;
                if (!empty($sale->sale_date)) {
                    try {
                        $forDate = Carbon::parse($sale->sale_date);
                    } catch (\Throwable $e) {
                        $forDate = null;
                    }
                }

                $sale->sale_no = static::generateNumber($sale->sale_type ?? 'sale', $forDate);
            }
        });
    }

    /**
     * Generate next number with separate daily sequence per sale_type.
     * Format: PREFIX-YYYYMMDD-#### where #### resets each day per type.
     *
     * IMPORTANT: Do not use daily COUNT() to determine next sequence.
     * If any sale is deleted, COUNT() can shrink and cause duplicate numbers.
     */
    public static function generateNumber(string $type, ?Carbon $forDate = null): string
    {
        $prefix = $type === 'quotation' ? 'QUO' : 'INV';
        $date = ($forDate ?? now())->format('Ymd');
        $base = $prefix . '-' . $date . '-';

        $driver = DB::connection()->getDriverName();
        $lockName = "sale_no_{$type}_{$date}";
        $locked = false;

        if ($driver === 'mysql') {
            try {
                $row = DB::selectOne('SELECT GET_LOCK(?, 5) AS l', [$lockName]);
                $locked = (int)($row->l ?? 0) === 1;
            } catch (\Throwable $e) {
                $locked = false;
            }
        }

        try {
            $last = static::query()
                ->where('sale_type', $type)
                ->where('sale_no', 'like', $base . '%')
                ->orderByDesc('sale_no')
                ->value('sale_no');

            $next = 1;
            if (is_string($last) && strlen($last) >= 4) {
                $suffix = (int) substr($last, -4);
                $next = max(1, $suffix + 1);
            }

            return $base . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
        } finally {
            if ($driver === 'mysql' && $locked) {
                try {
                    DB::selectOne('SELECT RELEASE_LOCK(?) AS r', [$lockName]);
                } catch (\Throwable $e) {
                    // ignore
                }
            }
        }
    }
}
