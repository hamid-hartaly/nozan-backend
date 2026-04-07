<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        DB::table('users')->updateOrInsert(
            ['email' => 'hamid.hartaly@gmail.com'],
            [
                'name' => 'HamidHartaly',
                'password' => Hash::make('H@mid1990'),
                'role' => 'admin',
                'is_active' => true,
                'email_verified_at' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        // Intentionally non-destructive.
    }
};
