<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_jobs', function (Blueprint $table) {
            // Track WhatsApp notifications sent for different events
            if (!Schema::hasColumn('service_jobs', 'whatsapp_created_sent')) {
                $table->boolean('whatsapp_created_sent')->default(false)->after('whatsapp_sent');
            }
            if (!Schema::hasColumn('service_jobs', 'whatsapp_repair_started_sent')) {
                $table->boolean('whatsapp_repair_started_sent')->default(false)->after('whatsapp_created_sent');
            }
            if (!Schema::hasColumn('service_jobs', 'whatsapp_finished_sent')) {
                $table->boolean('whatsapp_finished_sent')->default(false)->after('whatsapp_repair_started_sent');
            }
            if (!Schema::hasColumn('service_jobs', 'whatsapp_pickup_sent')) {
                $table->boolean('whatsapp_pickup_sent')->default(false)->after('whatsapp_finished_sent');
            }
        });
    }

    public function down(): void
    {
        Schema::table('service_jobs', function (Blueprint $table) {
            $table->dropColumn([
                'whatsapp_created_sent',
                'whatsapp_repair_started_sent',
                'whatsapp_finished_sent',
                'whatsapp_pickup_sent',
            ]);
        });
    }
};
