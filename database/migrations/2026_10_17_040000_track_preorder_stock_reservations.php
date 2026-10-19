<?php

use App\Support\Migrations\Schema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('pre_order_items', 'stock_deducted')) {
            return;
        }

        Schema::table('pre_order_items', function (Blueprint $table) {
            $table->boolean('stock_deducted')->default(false);
        });
        // Existing linked items in pending or completed orders were deducted
        // by the previous workflow. Preserve their inventory history.
        DB::table('pre_order_items')->whereNotNull('product_id')
            ->whereIn('pre_order_id', DB::table('pre_orders')->whereIn('status', ['pending', 'completed'])->select('id'))
            ->update(['stock_deducted' => true]);
    }

    public function down(): void
    {
        Schema::table('pre_order_items', fn (Blueprint $table) => $table->dropColumn('stock_deducted'));
    }
};
