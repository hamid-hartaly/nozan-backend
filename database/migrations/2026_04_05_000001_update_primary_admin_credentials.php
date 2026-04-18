<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $primaryAdmin = DB::table('users')
            ->where('role', 'admin')
            ->orderBy('id')
            ->first(['id']);

        if (! $primaryAdmin) {
            return;
        }

        $targetEmail = 'hamid.hartaly@gmail.com';
        $emailTakenByAnotherUser = DB::table('users')
            ->where('email', $targetEmail)
            ->where('id', '!=', $primaryAdmin->id)
            ->exists();

        $updates = [
            'name' => 'HamidHartaly',
            'password' => Hash::make('H@mid1990'),
        ];

        // Avoid breaking deploys when the desired email already belongs to another account.
        if (! $emailTakenByAnotherUser) {
            $updates['email'] = $targetEmail;
        }

        DB::table('users')
            ->where('id', $primaryAdmin->id)
            ->update($updates);
    }

    public function down(): void
    {
        // intentionally left blank – credentials reset is one-way
    }
};
