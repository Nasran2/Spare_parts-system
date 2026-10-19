<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;

class AccountTransfer extends Model
{
    protected $guarded = [];

    protected $casts = ['transfer_date' => 'date', 'amount' => 'decimal:2'];
}
