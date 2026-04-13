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
            if (! Schema::hasColumn('service_jobs', 'returned_from_job_id')) {
                $table->unsignedBigInteger('returned_from_job_id')->nullable()->index();
            }

            if (! Schema::hasColumn('service_jobs', 'warranty_months')) {
                $table->integer('warranty_months')->default(0);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('service_jobs')) {
            return;
        }

        Schema::table('service_jobs', function (Blueprint $table): void {
            if (Schema::hasColumn('service_jobs', 'warranty_months')) {
                $table->dropColumn('warranty_months');
            }

            if (Schema::hasColumn('service_jobs', 'returned_from_job_id')) {
                $table->dropColumn('returned_from_job_id');
            }
        });
    }
};
