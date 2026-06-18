<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
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
            } elseif (in_array($roleName, ['admin', 'manager', 'cashier'], true)) {
                $toAdd = ['privacy_mode.view', 'privacy_mode.toggle'];
            }

            if (! empty($toAdd)) {
                $role->permissions = array_values(array_unique(array_merge($current, $toAdd)));
                $role->save();
            }
        });
    }

    public function down(): void
    {
        // Keep permissions on rollback so existing role behavior is not made less secure accidentally.
    }
};
