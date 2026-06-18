<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Brand extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'logo',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'brand_product');
    }

    public static function productMetricsMap(?array $onlyBrandIds = null)
    {
        $pivot = DB::table('brand_product')
            ->select(['brand_id', 'product_id']);

        $legacy = DB::table('products')
            ->whereNotNull('brand_id')
            ->selectRaw('brand_id, id as product_id');

        if (! empty($onlyBrandIds)) {
            $pivot->whereIn('brand_id', $onlyBrandIds);
            $legacy->whereIn('brand_id', $onlyBrandIds);
        }

        $union = $pivot->union($legacy);

        return DB::query()
            ->fromSub($union, 'bp')
            ->join('products', 'products.id', '=', 'bp.product_id')
            ->selectRaw('bp.brand_id, COUNT(DISTINCT bp.product_id) as products_count, COALESCE(SUM(COALESCE(products.cost_price, 0)), 0) as total_cost_price, COALESCE(SUM(COALESCE(products.selling_price, 0)), 0) as total_selling_price')
            ->groupBy('bp.brand_id')
            ->get()
            ->keyBy('brand_id');
    }
}
