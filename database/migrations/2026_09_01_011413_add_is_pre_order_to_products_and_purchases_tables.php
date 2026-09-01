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
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_pre_order')->default(false)->after('is_active');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->boolean('is_pre_order')->default(false)->after('notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_pre_order');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn('is_pre_order');
        });
    }
};
