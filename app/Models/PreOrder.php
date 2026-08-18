<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PreOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'pre_order_number', 'customer_id', 'store_id', 'sale_id', 'pre_order_date',
        'document_type', 'vehicle_name', 'registration_number', 'chassis_number',
        'vehicle_description', 'vehicle_image', 'instructions', 'notes',
        'expected_delivery_date', 'bill_discount_type', 'bill_discount_value',
        'custom_tax_rate', 'pdf_tax_display',
        'subtotal', 'discount_amount', 'tax_amount', 'rounding_adjustment',
        'grand_total', 'paid_amount', 'held_cheque_amount', 'due_amount', 'status',
        'payment_status', 'completed_at', 'completed_by', 'cancelled_at',
        'cancelled_by', 'cancellation_reason', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'pre_order_date' => 'date',
        'expected_delivery_date' => 'date',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'bill_discount_value' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'rounding_adjustment' => 'decimal:4',
        'grand_total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'held_cheque_amount' => 'decimal:2',
        'due_amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (PreOrder $preOrder) {
            $preOrder->pre_order_number ??= static::generateNumber();
        });
    }

    public static function generateNumber(): string
    {
        $driver = DB::connection()->getDriverName();
        $locked = false;
        if ($driver === 'mysql') {
            $locked = (int) (DB::selectOne("SELECT GET_LOCK('pre_order_number', 5) AS acquired")->acquired ?? 0) === 1;
        }

        try {
            $max = static::query()->pluck('pre_order_number')->reduce(function (int $carry, string $number) {
                return preg_match('/^PRE-(\d+)$/', $number, $match) ? max($carry, (int) $match[1]) : $carry;
            }, 0);

            return 'PRE-'.str_pad((string) ($max + 1), 6, '0', STR_PAD_LEFT);
        } finally {
            if ($driver === 'mysql' && $locked) {
                DB::selectOne("SELECT RELEASE_LOCK('pre_order_number')");
            }
        }
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function items()
    {
        return $this->hasMany(PreOrderItem::class);
    }

    public function activities()
    {
        return $this->hasMany(PreOrderActivity::class)->latest();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function completer()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function canceller()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function getVehicleImageUrlAttribute(): ?string
    {
        return $this->vehicle_image ? asset($this->vehicle_image) : null;
    }

    public function getVehicleImageAbsolutePathAttribute(): ?string
    {
        if (! $this->vehicle_image || ! str_starts_with($this->vehicle_image, 'uploads/preorders/')) {
            return null;
        }
        $path = public_path($this->vehicle_image);

        return is_file($path) ? $path : null;
    }
}
