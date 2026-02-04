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
        Schema::table('payments', function (Blueprint $table) {
            // Drop existing foreign keys
            $table->dropForeign(['purchase_id']);
            $table->dropForeign(['supplier_id']);
            
            // Make columns nullable and re-add foreign keys
            $table->foreignId('purchase_id')->nullable()->change()->constrained('purchases')->onDelete('cascade');
            $table->foreignId('supplier_id')->nullable()->change()->constrained('suppliers')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['purchase_id']);
            $table->dropForeign(['supplier_id']);
            
            $table->foreignId('purchase_id')->nullable(false)->change()->constrained('purchases')->onDelete('cascade');
            $table->foreignId('supplier_id')->nullable(false)->change()->constrained('suppliers')->onDelete('cascade');
        });
    }
};
