<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products') || DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE products MODIFY sku VARCHAR(191) NOT NULL');
    }

    public function down(): void
    {
        // Keep the shorter indexed string length for older MySQL compatibility.
    }
};
