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
        'store_id',
        'sale_date',
        'subtotal',
        'tax',
        'discount',
        'total_amount',
        'paid_amount',
        'held_cheque_amount',
        'tendered_amount',
        'due_amount',
        'payment_status',
        'payment_method',
        'cheque_number',
        'bank_reference',
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
        'held_cheque_amount' => 'decimal:2',
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

    public function chequePayments()
    {
        return $this->hasMany(ChequePayment::class);
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
                if (! empty($sale->sale_date)) {
                    try {
                        $forDate = Carbon::parse($sale->sale_date);
                    } catch (\Throwable $e) {
                        $forDate = null;
                    }
                }

                $sale->sale_no = static::generateNumber($sale->sale_type ?? 'sale', $forDate);
            }
        });

        static::addGlobalScope('hiddenStores', function (\Illuminate\Database\Eloquent\Builder $builder) {
            if (auth()->hasUser()) {
                $hiddenStoreIds = \App\Services\DashboardVisibilityService::hiddenStoreIdsForUser(auth()->user());
                if (!empty($hiddenStoreIds)) {
                    $builder->where(function ($q) use ($hiddenStoreIds) {
                        $q->whereNotIn('sales.store_id', $hiddenStoreIds)
                          ->orWhereNull('sales.store_id');
                    });
                }
            }
        });
    }

    /**
     * Generate next number with separate daily sequence per sale_type.
     * Format: PREFIX-SECRET(DDMMYY + sequence) + two random digits.
     *
     * IMPORTANT: Do not use daily COUNT() to determine next sequence.
     * If any sale is deleted, COUNT() can shrink and cause duplicate numbers.
     */
    public static function generateNumber(string $type, ?Carbon $forDate = null): string
    {
        $prefix = $type === 'quotation' ? 'QUO' : 'INV';
        $date = ($forDate ?? now())->copy();
        $dateKey = $date->format('Ymd');

        $driver = DB::connection()->getDriverName();
        $lockName = "sale_no_{$type}_{$dateKey}";
        $locked = false;

        if ($driver === 'mysql') {
            try {
                $row = DB::selectOne('SELECT GET_LOCK(?, 5) AS l', [$lockName]);
                $locked = (int) ($row->l ?? 0) === 1;
            } catch (\Throwable $e) {
                $locked = false;
            }
        }

        try {
            $next = static::nextSequenceForDate($type, $prefix, $date);

            for ($attempt = 0; $attempt < 20; $attempt++) {
                $sequence = str_pad((string) $next, max(3, strlen((string) $next)), '0', STR_PAD_LEFT);
                $encoded = static::encodeSecretDigits($date->format('dmy').$sequence);
                $candidate = $prefix.'-'.$encoded.str_pad((string) random_int(0, 99), 2, '0', STR_PAD_LEFT);

                if (! static::query()->where('sale_no', $candidate)->exists()) {
                    return $candidate;
                }
            }

            throw new \RuntimeException('Unable to generate a unique invoice number.');
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

    private static function nextSequenceForDate(string $type, string $prefix, Carbon $date): int
    {
        $max = 0;
        $numbers = static::query()
            ->where('sale_type', $type)
            ->whereDate('sale_date', $date->toDateString())
            ->pluck('sale_no');

        foreach ($numbers as $number) {
            $sequence = static::sequenceFromNumber((string) $number, $prefix, $date);
            if ($sequence !== null) {
                $max = max($max, $sequence);
            }
        }

        return $max + 1;
    }

    private static function sequenceFromNumber(string $number, string $prefix, Carbon $date): ?int
    {
        $legacyBase = $prefix.'-'.$date->format('Ymd').'-';
        if (str_starts_with($number, $legacyBase)) {
            $suffix = substr($number, strlen($legacyBase));

            return ctype_digit($suffix) ? (int) $suffix : null;
        }

        $newBase = $prefix.'-';
        if (! str_starts_with($number, $newBase)) {
            return null;
        }

        $encoded = substr($number, strlen($newBase));
        if (strlen($encoded) < 11 || ! ctype_digit(substr($encoded, -2))) {
            return null;
        }

        $decoded = static::decodeSecretDigits(substr($encoded, 0, -2));
        $dateDigits = $date->format('dmy');
        if (! str_starts_with($decoded, $dateDigits)) {
            return null;
        }

        $sequence = substr($decoded, strlen($dateDigits));

        return ctype_digit($sequence) ? (int) $sequence : null;
    }

    private static function encodeSecretDigits(string $digits): string
    {
        $map = static::secretCodeMap();
        $zeroPool = static::zeroCodePool($map);
        $encoded = '';

        foreach (str_split($digits) as $digit) {
            if ($digit === '0') {
                $encoded .= $zeroPool[random_int(0, count($zeroPool) - 1)];

                continue;
            }

            $encoded .= $map[$digit] ?? $digit;
        }

        return $encoded;
    }

    private static function decodeSecretDigits(string $encoded): string
    {
        $reverse = [];
        foreach (static::secretCodeMap() as $digit => $letter) {
            $reverse[$letter] = (string) $digit;
        }

        $digits = '';
        foreach (str_split(strtoupper($encoded)) as $letter) {
            if (isset($reverse[$letter])) {
                $digits .= $reverse[$letter];

                continue;
            }

            $digits .= ctype_alpha($letter) ? '0' : $letter;
        }

        return $digits;
    }

    private static function secretCodeMap(): array
    {
        $default = [
            '0' => 'E',
            '1' => 'M',
            '2' => 'O',
            '3' => 'D',
            '4' => 'T',
            '5' => 'W',
            '6' => 'I',
            '7' => 'N',
            '8' => 'K',
            '9' => 'L',
        ];

        $stored = Setting::get('barcode_cost_code_map', []);
        if (! is_array($stored)) {
            return $default;
        }

        foreach ($default as $digit => $fallback) {
            $letter = strtoupper(substr((string) ($stored[$digit] ?? $fallback), 0, 1));
            $default[$digit] = ctype_alpha($letter) ? $letter : $fallback;
        }

        return $default;
    }

    private static function zeroCodePool(array $map): array
    {
        $usedLetters = array_filter(array_map('strtoupper', array_values($map)));
        $pool = [$map['0'] ?? 'E'];

        foreach (range('A', 'Z') as $letter) {
            if (! in_array($letter, $usedLetters, true)) {
                $pool[] = $letter;
            }
        }

        return array_values(array_unique($pool));
    }
}
