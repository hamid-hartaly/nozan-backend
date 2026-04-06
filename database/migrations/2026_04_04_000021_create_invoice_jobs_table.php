<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoice_jobs')) {
            return;
        }

        Schema::create('invoice_jobs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_job_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['invoice_id', 'service_job_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_jobs');
    }
};
