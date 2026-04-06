<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('expenses')) {
            return;
        }

        Schema::create('expenses', function (Blueprint $table): void {
            $table->id();
            $table->date('expense_date');
            $table->string('title');
            $table->string('category', 40);
            $table->decimal('amount_iqd', 12, 2);
            $table->text('note')->nullable();
            $table->string('recorded_by')->nullable();
            $table->timestamps();

            $table->index('expense_date');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
