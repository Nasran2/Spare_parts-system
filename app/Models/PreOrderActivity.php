<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PreOrderActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'pre_order_id', 'user_id', 'action', 'description', 'old_values',
        'new_values', 'ip_address',
    ];

    protected $casts = ['old_values' => 'array', 'new_values' => 'array'];

    public function preOrder()
    {
        return $this->belongsTo(PreOrder::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
