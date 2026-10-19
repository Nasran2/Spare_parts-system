<?php

use App\Support\Migrations\Schema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        // Imported databases can have an inaccurate migration history. Ensure
        // parent tables exist before MySQL attempts the document foreign keys.
        if (! Schema::hasTable('stores') || ! Schema::hasTable('stock_shipments')) {
            (require __DIR__.'/2026_06_05_000002_create_store_shipment_stock_tables.php')->up();
        }

        Schema::table('sales', function (Blueprint $table) {
            if (! Schema::hasColumn('sales', 'store_id')) {
                $table->unsignedBigInteger('store_id')->nullable()->after('user_id');
            }
            $table->foreign('store_id')->references('id')->on('stores')->nullOnDelete();
            if (! Schema::hasColumn('sales', 'cheque_number')) {
                $table->string('cheque_number')->nullable()->after('payment_method');
            }
            if (! Schema::hasColumn('sales', 'bank_reference')) {
                $table->string('bank_reference')->nullable()->after('cheque_number');
            }
        });

        Schema::table('purchases', function (Blueprint $table) {
            if (! Schema::hasColumn('purchases', 'stock_shipment_id')) {
                $table->unsignedBigInteger('stock_shipment_id')->nullable()->after('supplier_id');
            }
            $table->foreign('stock_shipment_id')->references('id')->on('stock_shipments')->nullOnDelete();
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
