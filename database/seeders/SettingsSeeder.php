<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Setting;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            ['key' => 'shop_name', 'value' => 'Vehicle POS System', 'type' => 'text', 'group' => 'business'],
            ['key' => 'shop_address', 'value' => '123 Main Street, City, State 12345', 'type' => 'text', 'group' => 'business'],
            ['key' => 'shop_phone', 'value' => '+1 (555) 123-4567', 'type' => 'text', 'group' => 'business'],
            ['key' => 'shop_email', 'value' => 'info@vehiclepos.com', 'type' => 'text', 'group' => 'business'],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
