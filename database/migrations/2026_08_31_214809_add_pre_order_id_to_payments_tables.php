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
            $table->foreignId('pre_order_id')->nullable()->after('sale_id')->constrained()->nullOnDelete();
        });

        Schema::table('cheque_payments', function (Blueprint $table) {
            $table->foreignId('pre_order_id')->nullable()->after('sale_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['pre_order_id']);
            $table->dropColumn('pre_order_id');
        });

        Schema::table('cheque_payments', function (Blueprint $table) {
            $table->dropForeign(['pre_order_id']);
            $table->dropColumn('pre_order_id');
        });
    }
};
