<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('category_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });

        Schema::create('brand_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('brand_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });

        // Migrate existing data
        $products = DB::table('products')->get();
        foreach ($products as $product) {
            if ($product->category_id) {
                // Check if category exists to avoid foreign key error
                $exists = DB::table('categories')->where('id', $product->category_id)->exists();
                if ($exists) {
                    DB::table('category_product')->insert([
                        'product_id' => $product->id,
                        'category_id' => $product->category_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
            if ($product->brand_id) {
                // Check if brand exists
                $exists = DB::table('brands')->where('id', $product->brand_id)->exists();
                if ($exists) {
                    DB::table('brand_product')->insert([
                        'product_id' => $product->id,
                        'brand_id' => $product->brand_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_product');
        Schema::dropIfExists('brand_product');
    }
};
