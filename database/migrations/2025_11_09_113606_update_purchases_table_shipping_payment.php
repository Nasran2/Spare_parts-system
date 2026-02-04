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
        Schema::table('purchases', function (Blueprint $table) {
            // Remove old fields only when they exist to keep migrate:fresh idempotent
            foreach (['business_location', 'pay_term_number', 'pay_term_type'] as $col) {
                if (Schema::hasColumn('purchases', $col)) {
                    $table->dropColumn($col);
                }
            }
            
            // Add new fields
            if (!Schema::hasColumn('purchases', 'shipping_cost')) {
                $table->decimal('shipping_cost', 15, 2)->default(0)->after('tax_amount');
            }
            if (!Schema::hasColumn('purchases', 'shipping_type')) {
                $table->string('shipping_type')->default('divided')->after('shipping_cost');
            }
            if (!Schema::hasColumn('purchases', 'payment_method')) {
                $table->string('payment_method')->nullable()->after('shipping_type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            // Remove new fields only when they exist
            foreach (['shipping_cost', 'shipping_type', 'payment_method'] as $col) {
                if (Schema::hasColumn('purchases', $col)) {
                    $table->dropColumn($col);
                }
            }
            
            // Restore old fields
            $table->string('business_location')->nullable();
            $table->integer('pay_term_number')->nullable();
            $table->string('pay_term_type')->nullable();
        });
    }
};
