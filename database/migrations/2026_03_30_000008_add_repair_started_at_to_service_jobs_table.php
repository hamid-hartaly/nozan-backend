<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_jobs', function (Blueprint $table): void {
            $table->timestamp('repair_started_at')->nullable()->after('assigned_staff_uid');
        });
    }

    public function down(): void
    {
        Schema::table('service_jobs', function (Blueprint $table): void {
            $table->dropColumn('repair_started_at');
        });
    }
};
