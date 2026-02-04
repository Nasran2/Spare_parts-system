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
        Schema::table('expense_categories', function (Blueprint $table) {
            $table->decimal('limit', 15, 2)->default(0)->after('is_active');
            $table->string('reset_frequency')->default('lifetime')->after('limit'); // 'lifetime', 'monthly'
            $table->integer('reset_date')->nullable()->after('reset_frequency'); // 1-31
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expense_categories', function (Blueprint $table) {
            $table->dropColumn(['limit', 'reset_frequency', 'reset_date']);
        });
    }
};
