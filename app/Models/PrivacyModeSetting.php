<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrivacyModeSetting extends Model
{
    use HasFactory;

    protected $table = 'privacy_mode_settings';

    protected $fillable = [
        'is_enabled',
        'shortcut_key',
        'shortcut_key_mac',
        'visible_invoice_limit',
        'masking_type',
        'apply_to_pos',
        'apply_to_sales_list',
        'apply_to_reports',
        'apply_to_dashboard',
        'apply_to_customer_history',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'apply_to_pos' => 'boolean',
        'apply_to_sales_list' => 'boolean',
        'apply_to_reports' => 'boolean',
        'apply_to_dashboard' => 'boolean',
        'apply_to_customer_history' => 'boolean',
        'visible_invoice_limit' => 'integer',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
