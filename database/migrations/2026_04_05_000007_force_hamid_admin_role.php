<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        DB::table('users')
            ->where('email', 'hamid.hartaly@gmail.com')
            ->update([
                'role' => 'admin',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // intentionally blank
    }
};
