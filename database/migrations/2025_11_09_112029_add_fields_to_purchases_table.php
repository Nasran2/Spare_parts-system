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
            $table->string('reference_no')->nullable()->after('purchase_no');
            $table->string('status')->default('pending')->after('reference_no'); // pending, ordered, received
            $table->string('discount_type')->default('none')->after('status'); // none, fixed, percentage
            $table->decimal('discount_amount', 15, 2)->default(0)->after('discount_type');
            $table->unsignedBigInteger('tax_id')->nullable()->after('discount_amount');
            $table->decimal('tax_amount', 15, 2)->default(0)->after('tax_id');
            $table->decimal('shipping_cost', 15, 2)->default(0)->after('tax_amount');
            $table->string('shipping_type')->default('divided')->after('shipping_cost'); // divided, expense
            $table->string('payment_method')->nullable()->after('shipping_type'); // cash, card, bank_transfer, cheque, credit
            $table->string('document_path')->nullable()->after('payment_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn([
                'reference_no',
                'status',
                'discount_type',
                'discount_amount',
                'tax_id',
                'tax_amount',
                'shipping_cost',
                'shipping_type',
                'payment_method',
                'document_path',
            ]);
        });
    }
};
