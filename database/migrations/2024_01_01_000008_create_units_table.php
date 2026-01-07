<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Piece, Set of 2, Set of 4, Dozen
            $table->string('short_name'); // pc, set2, set4, dz
            $table->integer('base_unit_multiplier')->default(1); // 1 for piece, 2 for set of 2, 4 for set of 4, 12 for dozen
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
