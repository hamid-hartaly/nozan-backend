<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('service_jobs')) {
            return;
        }

        Schema::table('service_jobs', function (Blueprint $table): void {
            if (! Schema::hasColumn('service_jobs', 'promised_completion_at')) {
                $table->timestamp('promised_completion_at')->nullable()->after('received_at')->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('service_jobs') || ! Schema::hasColumn('service_jobs', 'promised_completion_at')) {
            return;
        }

        Schema::table('service_jobs', function (Blueprint $table): void {
            $table->dropColumn('promised_completion_at');
        });
    }
};
