<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cheque_payments', function (Blueprint $table) {
            $table->enum('type', ['customer', 'own'])->default('customer')->after('id');
            // supplier_id and purchase_id
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete()->after('customer_id');
            $table->foreignId('purchase_id')->nullable()->constrained('purchases')->cascadeOnDelete()->after('sale_id');
        });

        // Modifying sale_id constrained to be nullable
        Schema::table('cheque_payments', function (Blueprint $table) {
            $table->dropForeign(['sale_id']);
        });
        Schema::table('cheque_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('sale_id')->nullable()->change();
            $table->foreign('sale_id')->references('id')->on('sales')->onDelete('cascade');
        });

        Schema::table('purchases', function (Blueprint $table) {
            if (! Schema::hasColumn('purchases', 'held_own_cheque_amount')) {
                $table->decimal('held_own_cheque_amount', 15, 2)->default(0)->after('paid_amount');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn('held_own_cheque_amount');
        });

        Schema::table('cheque_payments', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropForeign(['purchase_id']);
            $table->dropColumn(['type', 'supplier_id', 'purchase_id']);
        });

        Schema::table('cheque_payments', function (Blueprint $table) {
            $table->dropForeign(['sale_id']);
        });
        Schema::table('cheque_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('sale_id')->nullable(false)->change();
            $table->foreign('sale_id')->references('id')->on('sales')->onDelete('cascade');
        });
    }
};
