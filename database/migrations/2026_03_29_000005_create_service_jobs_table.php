<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('service_jobs')) {
            return;
        }

        Schema::create('service_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('job_code')->unique();
            $table->string('customer_id')->nullable();
            $table->string('customer_name');
            $table->string('customer_phone', 50);
            $table->string('tv_model');
            $table->string('category', 20);
            $table->text('issue');
            $table->string('priority', 20)->default('normal');
            $table->string('status', 20)->default('PENDING');
            $table->unsignedBigInteger('estimated_price_iqd')->nullable();
            $table->unsignedBigInteger('final_price_iqd')->nullable();
            $table->unsignedBigInteger('repair_cost_iqd')->nullable();
            $table->boolean('whatsapp_sent')->default(false);
            $table->unsignedBigInteger('payment_received_iqd')->default(0);
            $table->string('assigned_staff_uid')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('out_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_jobs');
    }
};
