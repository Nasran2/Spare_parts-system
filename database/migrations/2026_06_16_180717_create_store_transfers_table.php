<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_no')->unique();
            $table->foreignId('from_store_id')->constrained('stores');
            $table->foreignId('to_store_id')->constrained('stores');
            $table->date('transfer_date');
            $table->string('reference_no')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('shipping_cost', 15, 2)->default(0);
            $table->decimal('additional_expense', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::table('store_stock_transfers', function (Blueprint $table) {
            $table->foreignId('store_transfer_id')->nullable()->constrained('store_transfers')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('store_stock_transfers', function (Blueprint $table) {
            $table->dropForeign(['store_transfer_id']);
            $table->dropColumn('store_transfer_id');
        });
        Schema::dropIfExists('store_transfers');
    }
};
