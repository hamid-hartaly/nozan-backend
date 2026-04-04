<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('role', 'admin')
            ->orderBy('id')
            ->limit(1)
            ->update([
                'name'     => 'HamidHartaly',
                'email'    => 'hamid.harrtaly@gmail.com',
                'password' => Hash::make('H@mid1990'),
            ]);
    }

    public function down(): void
    {
        // intentionally left blank – credentials reset is one-way
    }
};
