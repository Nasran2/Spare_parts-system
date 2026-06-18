<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (! Schema::hasColumn('sales', 'store_id')) {
                $table->foreignId('store_id')->nullable()->after('user_id')->constrained('stores')->nullOnDelete();
            }
            if (! Schema::hasColumn('sales', 'cheque_number')) {
                $table->string('cheque_number')->nullable()->after('payment_method');
            }
            if (! Schema::hasColumn('sales', 'bank_reference')) {
                $table->string('bank_reference')->nullable()->after('cheque_number');
            }
        });

        Schema::table('purchases', function (Blueprint $table) {
            if (! Schema::hasColumn('purchases', 'stock_shipment_id')) {
                $table->foreignId('stock_shipment_id')->nullable()->after('supplier_id')->constrained('stock_shipments')->nullOnDelete();
            }
            if (! Schema::hasColumn('purchases', 'cheque_number')) {
                $table->string('cheque_number')->nullable()->after('payment_method');
            }
            if (! Schema::hasColumn('purchases', 'bank_reference')) {
                $table->string('bank_reference')->nullable()->after('cheque_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            if (Schema::hasColumn('purchases', 'stock_shipment_id')) {
                $table->dropConstrainedForeignId('stock_shipment_id');
            }
            if (Schema::hasColumn('purchases', 'cheque_number')) {
                $table->dropColumn('cheque_number');
            }
            if (Schema::hasColumn('purchases', 'bank_reference')) {
                $table->dropColumn('bank_reference');
            }
        });

        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'store_id')) {
                $table->dropConstrainedForeignId('store_id');
            }
            if (Schema::hasColumn('sales', 'cheque_number')) {
                $table->dropColumn('cheque_number');
            }
            if (Schema::hasColumn('sales', 'bank_reference')) {
                $table->dropColumn('bank_reference');
            }
        });
    }
};
