<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('secret_settings')) {
            return;
        }

        Schema::create('secret_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->longText('value')->nullable();
            $table->string('type')->default('text');
            $table->string('group')->default('secret');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secret_settings');
    }
};
