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
        Schema::table('privacy_mode_settings', function (Blueprint $table) {
            $table->string('sales_list_percentage_mode')->default('each_day')->after('visible_invoice_limit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('privacy_mode_settings', function (Blueprint $table) {
            $table->dropColumn('sales_list_percentage_mode');
        });
    }
};
