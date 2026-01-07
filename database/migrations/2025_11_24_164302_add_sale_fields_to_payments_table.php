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
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('sale_id')->nullable()->after('purchase_id')->constrained('sales')->onDelete('cascade');
            $table->foreignId('customer_id')->nullable()->after('supplier_id')->constrained('customers')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['sale_id']);
            $table->dropColumn('sale_id');
            $table->dropForeign(['customer_id']);
            $table->dropColumn('customer_id');
        });
    }
};
