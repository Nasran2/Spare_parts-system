<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductTaxSetting extends Model
{
    protected $fillable = [
        'product_id',
        'tax_type_id',
        'tax_status',
        'vat_rate',
        'sale_price_mode',
        'purchase_price_mode',
        'output_vat_allowed',
        'input_vat_allowed',
    ];

    protected $casts = [
        'vat_rate' => 'decimal:4',
        'output_vat_allowed' => 'boolean',
        'input_vat_allowed' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function taxType()
    {
        return $this->belongsTo(TaxType::class);
    }
}
