<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('brands', 'subcategory_id')) {
            Schema::table('brands', function (Blueprint $table) {
                $table->dropConstrainedForeignId('subcategory_id');
            });
        }

        if (Schema::hasColumn('brands', 'category_id')) {
            Schema::table('brands', function (Blueprint $table) {
                $table->dropConstrainedForeignId('category_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('brands', 'category_id')) {
            Schema::table('brands', function (Blueprint $table) {
                $table->foreignId('category_id')
                    ->nullable()
                    ->constrained('categories')
                    ->nullOnDelete()
                    ->after('description');
            });
        }

        if (! Schema::hasColumn('brands', 'subcategory_id')) {
            Schema::table('brands', function (Blueprint $table) {
                $table->foreignId('subcategory_id')
                    ->nullable()
                    ->constrained('categories')
                    ->nullOnDelete()
                    ->after('category_id');
            });
        }
    }
};
