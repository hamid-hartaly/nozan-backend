<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('phone', 50);
            $table->string('tv_model', 255);
            $table->text('description');
            $table->text('address');
            $table->string('image_path')->nullable();
            $table->enum('status', ['pending', 'converted', 'rejected'])->default('pending');
            $table->dateTime('converted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
