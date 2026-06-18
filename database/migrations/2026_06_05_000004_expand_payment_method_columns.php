<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        if (Schema::hasColumn('sales', 'payment_method')) {
            DB::statement("ALTER TABLE sales MODIFY payment_method VARCHAR(50) NOT NULL DEFAULT 'cash'");
        }

        if (Schema::hasColumn('purchases', 'payment_method')) {
            DB::statement('ALTER TABLE purchases MODIFY payment_method VARCHAR(50) NULL');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        if (Schema::hasColumn('sales', 'payment_method')) {
            DB::statement("ALTER TABLE sales MODIFY payment_method ENUM('cash','card','bank_transfer','mobile_payment') NOT NULL DEFAULT 'cash'");
        }

        if (Schema::hasColumn('purchases', 'payment_method')) {
            DB::statement('ALTER TABLE purchases MODIFY payment_method VARCHAR(255) NULL');
        }
    }
};
