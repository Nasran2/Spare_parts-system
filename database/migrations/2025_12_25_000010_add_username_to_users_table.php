<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'username')) {
                $table->string('username')->nullable()->unique()->after('name');
            }
        });

        // Backfill usernames from existing names if missing
        $users = DB::table('users')->select('id', 'name', 'username')->get();
        foreach ($users as $user) {
            if (empty($user->username)) {
                $base = Str::slug($user->name ?: 'user'.$user->id, '_');
                $candidate = $base;
                $suffix = 1;
                while (DB::table('users')->where('username', $candidate)->exists()) {
                    $candidate = $base.'_'.$suffix;
                    $suffix++;
                }
                DB::table('users')->where('id', $user->id)->update(['username' => $candidate]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'username')) {
                $table->dropUnique(['username']);
                $table->dropColumn('username');
            }
        });
    }
};
