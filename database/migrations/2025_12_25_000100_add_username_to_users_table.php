<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add column if it doesn't exist
        if (! Schema::hasColumn('users', 'username')) {
            Schema::table('users', function (Blueprint $table) {
                // Add column first without unique index to allow backfill
                $table->string('username')->nullable()->after('name');
            });
        }

        // Backfill usernames from existing data
        $users = DB::table('users')->select('id', 'name', 'email', 'username')->get();
        foreach ($users as $user) {
            if (empty($user->username)) {
                // Prefer a slug from name; fallback to email local part
                $base = $user->name ? Str::slug($user->name) : Str::before($user->email, '@');
                if ($base === '') {
                    $base = 'user';
                }

                $candidate = $base;
                $suffix = 0;
                // Ensure uniqueness
                while (DB::table('users')->where('username', $candidate)->exists()) {
                    $suffix++;
                    $candidate = $base.'-'.$suffix;
                }

                DB::table('users')->where('id', $user->id)->update(['username' => $candidate]);
            }
        }

        // Now enforce uniqueness
        $hasUsernameUniqueIndex = collect(DB::select("SHOW INDEX FROM users WHERE Key_name = 'users_username_unique'"))->isNotEmpty();
        if (! $hasUsernameUniqueIndex) {
            Schema::table('users', function (Blueprint $table) {
                $table->unique('username');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('users', 'username')) {
            Schema::table('users', function (Blueprint $table) {
                // Drop unique index if exists
                try {
                    $table->dropUnique(['username']);
                } catch (\Throwable $e) {
                    // index might have a generated name; ignore
                }
                $table->dropColumn('username');
            });
        }
    }
};
