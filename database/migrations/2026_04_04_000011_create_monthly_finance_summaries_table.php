<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('monthly_finance_summaries')) {
            return;
        }

        Schema::create('monthly_finance_summaries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('total_revenue_iqd', 14, 2)->default(0);
            $table->decimal('total_expenses_iqd', 14, 2)->default(0);
            $table->decimal('total_net_iqd', 14, 2)->default(0);
            $table->unsignedInteger('total_jobs')->default(0);
            $table->unsignedInteger('total_finished_jobs')->default(0);
            $table->decimal('total_open_debt_iqd', 14, 2)->default(0);
            $table->boolean('is_closed')->default(false);
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->unique(['year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_finance_summaries');
    }
};
