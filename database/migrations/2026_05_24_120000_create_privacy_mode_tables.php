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
        // 1. Create settings table
        if (! Schema::hasTable('privacy_mode_settings')) {
            Schema::create('privacy_mode_settings', function (Blueprint $table) {
                $table->id();
                $table->boolean('is_enabled')->default(false);
                $table->string('shortcut_key')->default('Cmd+X');
                $table->integer('visible_invoice_limit')->default(10);
                $table->string('masking_type')->default('hide'); // hide, low_amount, blur, hidden
                $table->boolean('apply_to_pos')->default(true);
                $table->boolean('apply_to_sales_list')->default(true);
                $table->boolean('apply_to_reports')->default(true);
                $table->boolean('apply_to_dashboard')->default(true);
                $table->boolean('apply_to_customer_history')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });

            // Seed default settings row
            DB::table('privacy_mode_settings')->insert([
                'is_enabled' => false,
                'shortcut_key' => 'Cmd+X',
                'visible_invoice_limit' => 10,
                'masking_type' => 'hide',
                'apply_to_pos' => true,
                'apply_to_sales_list' => true,
                'apply_to_reports' => true,
                'apply_to_dashboard' => true,
                'apply_to_customer_history' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 2. Create logs table
        if (! Schema::hasTable('privacy_mode_logs')) {
            Schema::create('privacy_mode_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('action'); // activated, deactivated
                $table->string('page')->nullable();
                $table->string('ip_address')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        // 3. Assign new permissions to roles
        $this->seedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_mode_settings');
        Schema::dropIfExists('privacy_mode_logs');
        $this->removePermissions();
    }

    private function seedPermissions(): void
    {
        $allPermissions = [
            'privacy_mode.view',
            'privacy_mode.toggle',
            'privacy_mode.settings',
            'privacy_mode.bypass',
        ];

        Role::query()->each(function (Role $role) use ($allPermissions) {
            $current = $role->permissions ?? [];
            $roleName = strtolower(trim((string) $role->name));

            $toAdd = [];
            if (in_array($roleName, ['super admin', 'superadmin', 'super_admin'], true)) {
                $toAdd = $allPermissions;
            } elseif (in_array($roleName, ['admin', 'manager'], true)) {
                $toAdd = ['privacy_mode.view', 'privacy_mode.toggle'];
            } elseif (in_array($roleName, ['cashier'], true)) {
                $toAdd = ['privacy_mode.view', 'privacy_mode.toggle'];
            }

            if (! empty($toAdd)) {
                $role->permissions = array_values(array_unique(array_merge($current, $toAdd)));
                $role->save();
            }
        });
    }

    private function removePermissions(): void
    {
        $allPermissions = [
            'privacy_mode.view',
            'privacy_mode.toggle',
            'privacy_mode.settings',
            'privacy_mode.bypass',
        ];

        Role::query()->each(function (Role $role) use ($allPermissions) {
            $role->permissions = array_values(array_diff($role->permissions ?? [], $allPermissions));
            $role->save();
        });
    }
};
