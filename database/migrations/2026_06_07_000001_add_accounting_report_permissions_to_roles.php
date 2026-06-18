<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = ['accounting.t-accounts', 'accounting.trial-balance'];

        Role::whereIn('name', ['Admin', 'Super Admin', 'Manager'])->get()->each(function (Role $role) use ($permissions) {
            $role->permissions = array_values(array_unique(array_merge($role->permissions ?? [], $permissions)));
            $role->save();
        });
    }

    public function down(): void
    {
        $permissions = ['accounting.t-accounts', 'accounting.trial-balance'];

        Role::whereIn('name', ['Admin', 'Super Admin', 'Manager'])->get()->each(function (Role $role) use ($permissions) {
            $role->permissions = array_values(array_diff($role->permissions ?? [], $permissions));
            $role->save();
        });
    }
};
