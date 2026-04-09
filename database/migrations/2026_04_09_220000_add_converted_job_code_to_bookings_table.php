<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            if (! Schema::hasColumn('bookings', 'converted_job_code')) {
                $table->string('converted_job_code')->nullable()->after('converted_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            if (Schema::hasColumn('bookings', 'converted_job_code')) {
                $table->dropColumn('converted_job_code');
            }
        });
    }
};
