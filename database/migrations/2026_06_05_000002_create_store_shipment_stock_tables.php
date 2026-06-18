<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('store_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_price_id')->nullable()->constrained('product_prices')->nullOnDelete();
            $table->decimal('quantity', 15, 3)->default(0);
            $table->unique(['store_id', 'product_id', 'product_price_id'], 'store_product_price_unique');
            $table->timestamps();
        });

        Schema::create('stock_shipments', function (Blueprint $table) {
            $table->id();
            $table->string('shipment_no')->unique();
            $table->string('grn_no')->unique();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->date('shipment_date');
            $table->date('received_date')->nullable();
            $table->enum('status', ['draft', 'received', 'allocated', 'closed'])->default('draft');
            $table->decimal('freight_cost', 15, 2)->default(0);
            $table->decimal('duty_cost', 15, 2)->default(0);
            $table->decimal('other_cost', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_shipment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_shipment_id')->constrained('stock_shipments')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('landed_unit_cost', 15, 2)->default(0);
            $table->decimal('selling_price', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('stock_shipment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_shipment_item_id')->constrained('stock_shipment_items')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->timestamps();
        });

        Schema::create('store_stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('to_store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->date('transfer_date');
            $table->string('reference_no')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_stock_transfers');
        Schema::dropIfExists('stock_shipment_allocations');
        Schema::dropIfExists('stock_shipment_items');
        Schema::dropIfExists('stock_shipments');
        Schema::dropIfExists('store_stocks');
        Schema::dropIfExists('stores');
    }
};
