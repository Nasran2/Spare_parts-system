<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'parent_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'category_product');
    }

    public static function productCountsMap(?array $onlyCategoryIds = null)
    {
        $pivot = DB::table('category_product')
            ->select(['category_id', 'product_id']);

        $legacy = DB::table('products')
            ->whereNotNull('category_id')
            ->selectRaw('category_id, id as product_id');

        if (! empty($onlyCategoryIds)) {
            $pivot->whereIn('category_id', $onlyCategoryIds);
            $legacy->whereIn('category_id', $onlyCategoryIds);
        }

        $union = $pivot->union($legacy);

        return DB::query()
            ->fromSub($union, 'cp')
            ->selectRaw('category_id, COUNT(DISTINCT product_id) as products_count')
            ->groupBy('category_id')
            ->pluck('products_count', 'category_id');
    }

    public static function productMetricsMap(?array $onlyCategoryIds = null)
    {
        $pivot = DB::table('category_product')
            ->select(['category_id', 'product_id']);

        $legacy = DB::table('products')
            ->whereNotNull('category_id')
            ->selectRaw('category_id, id as product_id');

        if (! empty($onlyCategoryIds)) {
            $pivot->whereIn('category_id', $onlyCategoryIds);
            $legacy->whereIn('category_id', $onlyCategoryIds);
        }

        $union = $pivot->union($legacy);

        return DB::query()
            ->fromSub($union, 'cp')
            ->join('products', 'products.id', '=', 'cp.product_id')
            ->selectRaw('cp.category_id, COUNT(DISTINCT cp.product_id) as products_count, COALESCE(SUM(COALESCE(products.cost_price, 0)), 0) as total_cost_price, COALESCE(SUM(COALESCE(products.selling_price, 0)), 0) as total_selling_price')
            ->groupBy('cp.category_id')
            ->get()
            ->keyBy('category_id');
    }
}
