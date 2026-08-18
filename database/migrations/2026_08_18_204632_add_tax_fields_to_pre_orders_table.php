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
        Schema::table('pre_orders', function (Blueprint $table) {
            $table->decimal('custom_tax_rate', 5, 2)->nullable()->after('notes');
            $table->string('pdf_tax_display', 50)->default('separate')->after('custom_tax_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pre_orders', function (Blueprint $table) {
            $table->dropColumn(['custom_tax_rate', 'pdf_tax_display']);
        });
    }
};
