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
            if (! Schema::hasColumn('service_jobs', 'is_in_warranty')) {
                $table->boolean('is_in_warranty')->default(false);
            }

            if (! Schema::hasColumn('service_jobs', 'warranty_company')) {
                $table->string('warranty_company')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('service_jobs')) {
            return;
        }

        Schema::table('service_jobs', function (Blueprint $table): void {
            if (Schema::hasColumn('service_jobs', 'warranty_company')) {
                $table->dropColumn('warranty_company');
            }

            if (Schema::hasColumn('service_jobs', 'is_in_warranty')) {
                $table->dropColumn('is_in_warranty');
            }
        });
    }
};
