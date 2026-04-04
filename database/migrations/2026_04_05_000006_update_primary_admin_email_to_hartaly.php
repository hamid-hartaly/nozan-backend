<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->updateOrInsert(
            ['role' => 'admin'],
            [
                'name'       => 'HamidHartaly',
                'email'      => 'hamid.hartaly@gmail.com',
                'password'   => Hash::make('H@mid1990'),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        // intentionally blank
    }
};
