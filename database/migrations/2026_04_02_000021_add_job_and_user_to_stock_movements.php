<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stock_movements')) {
            return;
        }

        Schema::table('stock_movements', function (Blueprint $table): void {
            if (! Schema::hasColumn('stock_movements', 'service_job_id')) {
                $table->foreignId('service_job_id')->nullable()->after('inventory_item_id')->constrained('service_jobs')->nullOnDelete();
            }

            if (! Schema::hasColumn('stock_movements', 'created_by_user_id')) {
                $table->foreignId('created_by_user_id')->nullable()->after('service_job_id')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('stock_movements')) {
            return;
        }

        Schema::table('stock_movements', function (Blueprint $table): void {
            if (Schema::hasColumn('stock_movements', 'created_by_user_id')) {
                $table->dropConstrainedForeignId('created_by_user_id');
            }

            if (Schema::hasColumn('stock_movements', 'service_job_id')) {
                $table->dropConstrainedForeignId('service_job_id');
            }
        });
    }
};
