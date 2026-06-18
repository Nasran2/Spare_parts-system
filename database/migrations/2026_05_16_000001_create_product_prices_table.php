<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->decimal('cost_price', 15, 2)->default(0);
            $table->decimal('selling_price', 15, 2)->default(0);
            $table->decimal('stock_qty', 15, 3)->default(0);
            $table->boolean('is_default')->default(false);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['product_id', 'cost_price', 'selling_price', 'status'], 'product_prices_unique_active_price');
            $table->index(['product_id', 'status', 'is_default']);
        });

        Product::query()
            ->select(['id', 'cost_price', 'selling_price', 'stock_quantity', 'created_at', 'updated_at'])
            ->orderBy('id')
            ->chunkById(200, function ($products) {
                $now = now();
                foreach ($products as $product) {
                    DB::table('product_prices')->insertOrIgnore([
                        'product_id' => $product->id,
                        'cost_price' => (float) ($product->cost_price ?? 0),
                        'selling_price' => (float) ($product->selling_price ?? 0),
                        'stock_qty' => (float) ($product->stock_quantity ?? 0),
                        'is_default' => true,
                        'status' => 'active',
                        'created_at' => $product->created_at ?? $now,
                        'updated_at' => $product->updated_at ?? $now,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_prices');
    }
};
