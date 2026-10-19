<?php

use App\Support\Migrations\Schema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['payments', 'cheque_payments', 'expenses', 'tax_payments'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['payments', 'cheque_payments', 'expenses', 'tax_payments'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropConstrainedForeignId('bank_account_id'));
        }
    }
};
