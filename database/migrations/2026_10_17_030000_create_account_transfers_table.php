<?php

use App\Support\Migrations\Schema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_transfers', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_token')->unique();
            $table->foreignId('from_bank_id')->nullable()->constrained('bank_accounts')->restrictOnDelete();
            $table->foreignId('to_bank_id')->nullable()->constrained('bank_accounts')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('transfer_date');
            $table->decimal('amount', 15, 2);
            $table->string('note');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_transfers');
    }
};
