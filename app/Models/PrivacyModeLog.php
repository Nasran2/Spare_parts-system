<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrivacyModeLog extends Model
{
    use HasFactory;

    protected $table = 'privacy_mode_logs';

    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'action', // activated, deactivated
        'page',
        'ip_address',
        'created_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
