<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->foreignId('product_price_id')
                ->nullable()
                ->after('product_id')
                ->constrained('product_prices')
                ->nullOnDelete();
            $table->decimal('selling_price', 15, 2)->default(0)->after('unit_cost');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->foreignId('product_price_id')
                ->nullable()
                ->after('product_id')
                ->constrained('product_prices')
                ->nullOnDelete();
        });

        Schema::table('sale_return_items', function (Blueprint $table) {
            $table->foreignId('product_price_id')
                ->nullable()
                ->after('product_id')
                ->constrained('product_prices')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sale_return_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_price_id');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_price_id');
        });

        Schema::table('purchase_items', function (Blueprint $table) {
            $table->dropColumn('selling_price');
            $table->dropConstrainedForeignId('product_price_id');
        });
    }
};
