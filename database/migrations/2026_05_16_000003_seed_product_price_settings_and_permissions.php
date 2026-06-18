<?php

use App\Models\Role;
use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Setting::set('use_price_wise_stock', true, 'boolean', 'pos');
        Setting::set('show_cost_price_in_pos_popup', false, 'boolean', 'pos');

        $permissions = [
            'view_product_prices',
            'create_product_prices',
            'edit_product_prices',
            'delete_product_prices',
            'view_cost_price_in_pos',
        ];

        Role::query()->each(function (Role $role) use ($permissions) {
            $current = $role->permissions ?? [];
            $roleName = strtolower(trim((string) $role->name));

            if (in_array($roleName, ['super admin', 'superadmin', 'super_admin', 'admin', 'manager'], true)) {
                $role->permissions = array_values(array_unique(array_merge($current, $permissions)));
                $role->save();
            }
        });
    }

    public function down(): void
    {
        $permissions = [
            'view_product_prices',
            'create_product_prices',
            'edit_product_prices',
            'delete_product_prices',
            'view_cost_price_in_pos',
        ];

        Role::query()->each(function (Role $role) use ($permissions) {
            $role->permissions = array_values(array_diff($role->permissions ?? [], $permissions));
            $role->save();
        });
    }
};
