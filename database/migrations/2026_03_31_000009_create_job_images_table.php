<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_job_id')->constrained()->cascadeOnDelete();
            $table->string('image_path');
            $table->string('label')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_images');
    }
};
