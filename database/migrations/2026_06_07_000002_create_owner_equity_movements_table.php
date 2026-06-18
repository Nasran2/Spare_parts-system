<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owner_equity_movements', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['investment', 'withdrawal']);
            $table->string('owner_name')->nullable();
            $table->date('movement_date');
            $table->enum('payment_account', ['cash', 'bank']);
            $table->decimal('amount', 15, 2);
            $table->string('reference_no')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('asset_account_id')->constrained('chart_accounts');
            $table->foreignId('equity_account_id')->constrained('chart_accounts');
            $table->foreignId('asset_transaction_id')->nullable()->constrained('account_transactions')->nullOnDelete();
            $table->foreignId('equity_transaction_id')->nullable()->constrained('account_transactions')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::table('chart_accounts')->updateOrInsert(
            ['code' => '3100'],
            [
                'name' => 'Owner Capital',
                'type' => 'equity',
                'subtype' => 'owner_capital',
                'opening_balance' => 0,
                'current_balance' => DB::table('chart_accounts')->where('code', '3100')->value('current_balance') ?? 0,
                'is_system' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('chart_accounts')->updateOrInsert(
            ['code' => '3200'],
            [
                'name' => 'Owner Drawings',
                'type' => 'equity',
                'subtype' => 'owner_drawings',
                'opening_balance' => 0,
                'current_balance' => DB::table('chart_accounts')->where('code', '3200')->value('current_balance') ?? 0,
                'is_system' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $permissions = [
            'accounting.owner-equity.view',
            'accounting.owner-equity.create',
            'accounting.owner-equity.edit',
            'accounting.owner-equity.delete',
        ];

        Role::whereIn('name', ['Admin', 'Super Admin', 'Manager'])->get()->each(function (Role $role) use ($permissions) {
            $role->permissions = array_values(array_unique(array_merge($role->permissions ?? [], $permissions)));
            $role->save();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_equity_movements');

        $permissions = [
            'accounting.owner-equity.view',
            'accounting.owner-equity.create',
            'accounting.owner-equity.edit',
            'accounting.owner-equity.delete',
        ];

        Role::all()->each(function (Role $role) use ($permissions) {
            $role->permissions = array_values(array_diff($role->permissions ?? [], $permissions));
            $role->save();
        });
    }
};
