<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('privacy_mode_settings')) {
            Schema::table('privacy_mode_settings', function (Blueprint $table) {
                if (! Schema::hasColumn('privacy_mode_settings', 'shortcut_key_mac')) {
                    $table->string('shortcut_key_mac')->default('Cmd+X')->after('shortcut_key');
                }
            });

            // Update the existing settings row to default to Cmd+X
            DB::table('privacy_mode_settings')->update(['shortcut_key_mac' => 'Cmd+X']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('privacy_mode_settings')) {
            Schema::table('privacy_mode_settings', function (Blueprint $table) {
                if (Schema::hasColumn('privacy_mode_settings', 'shortcut_key_mac')) {
                    $table->dropColumn('shortcut_key_mac');
                }
            });
        }
    }
};
