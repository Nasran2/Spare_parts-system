<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

class UpdateBarcodePermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissionsToAdd = ['barcode.print', 'barcode.settings'];

        $roles = Role::whereIn('name', ['Admin', 'Manager', 'Cashier'])->get();
        foreach ($roles as $role) {
            $current = $role->permissions ?? [];
            $updated = array_values(array_unique(array_merge($current, $permissionsToAdd)));
            $role->update(['permissions' => $updated]);
        }

        $this->command?->info('✓ Barcode permissions added to roles');
    }
}
