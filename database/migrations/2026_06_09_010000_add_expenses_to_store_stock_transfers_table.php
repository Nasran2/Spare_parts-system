<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_stock_transfers', function (Blueprint $table) {
            if (! Schema::hasColumn('store_stock_transfers', 'shipping_cost')) {
                $table->decimal('shipping_cost', 15, 2)->default(0)->after('notes');
            }

            if (! Schema::hasColumn('store_stock_transfers', 'additional_expense')) {
                $table->decimal('additional_expense', 15, 2)->default(0)->after('shipping_cost');
            }
        });
    }

    public function down(): void
    {
        Schema::table('store_stock_transfers', function (Blueprint $table) {
            foreach (['additional_expense', 'shipping_cost'] as $column) {
                if (Schema::hasColumn('store_stock_transfers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
