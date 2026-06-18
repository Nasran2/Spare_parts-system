<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (! Schema::hasColumn('sales', 'held_cheque_amount')) {
                $table->decimal('held_cheque_amount', 15, 2)->default(0)->after('paid_amount');
            }
        });

        Schema::create('cheque_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('cheque_date');
            $table->string('cheque_number', 100);
            $table->string('bank_name')->nullable();
            $table->string('account_name')->nullable();
            $table->decimal('amount', 15, 2);
            $table->enum('status', ['pending', 'passed', 'returned'])->default('pending');
            $table->timestamp('processed_at')->nullable();
            $table->boolean('auto_passed')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'cheque_date']);
            $table->index('cheque_number');
        });

        $this->upsertSetting('pos_cheque_reminders_enabled', '1', 'boolean', 'pos');
        $this->upsertSetting('pos_cheque_reminder_days_before', '3', 'number', 'pos');
        $this->upsertSetting('pos_cheque_auto_pass_enabled', '1', 'boolean', 'pos');
        $this->upsertSetting('pos_cheque_auto_pass_days_after', '15', 'number', 'pos');

        $permissions = [
            'cheque_payments.view',
            'cheque_payments.create',
            'cheque_payments.manage',
            'cheque_payments.settings',
        ];

        foreach (DB::table('roles')->get(['id', 'name', 'permissions']) as $role) {
            $roleName = strtolower(trim((string) $role->name));
            $existing = json_decode($role->permissions ?: '[]', true) ?: [];

            if (in_array($roleName, ['super admin', 'superadmin', 'super_admin', 'admin'], true)) {
                $merged = array_values(array_unique(array_merge($existing, $permissions)));
            } elseif ($roleName === 'manager') {
                $merged = array_values(array_unique(array_merge($existing, [
                    'cheque_payments.view',
                    'cheque_payments.create',
                    'cheque_payments.manage',
                ])));
            } elseif ($roleName === 'cashier') {
                $merged = array_values(array_unique(array_merge($existing, [
                    'cheque_payments.create',
                ])));
            } else {
                $merged = $existing;
            }

            DB::table('roles')->where('id', $role->id)->update(['permissions' => json_encode($merged)]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cheque_payments');

        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'held_cheque_amount')) {
                $table->dropColumn('held_cheque_amount');
            }
        });
    }

    private function upsertSetting(string $key, string $value, string $type, string $group): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => $key],
            ['value' => $value, 'type' => $type, 'group' => $group, 'updated_at' => now(), 'created_at' => now()]
        );
    }
};
