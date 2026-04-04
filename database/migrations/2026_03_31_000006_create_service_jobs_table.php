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

        Schema::create('service_jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('job_code')->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->string('tv_model');
            $table->string('category')->default('TV');
            $table->string('priority')->default('Normal');
            $table->text('issue');
            $table->decimal('estimated_price_iqd', 12, 2)->default(0);
            $table->decimal('final_price_iqd', 12, 2)->nullable();
            $table->string('status')->default('pending')->index();
            $table->string('assigned_technician')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_jobs');
    }
};
