<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoices')) {
            return;
        }

        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->string('customer_name')->nullable();
            $table->decimal('subtotal_iqd', 14, 2)->default(0);
            $table->decimal('discount_iqd', 14, 2)->default(0);
            $table->decimal('tax_iqd', 14, 2)->default(0);
            $table->decimal('total_iqd', 14, 2)->default(0);
            $table->decimal('paid_iqd', 14, 2)->default(0);
            $table->decimal('outstanding_iqd', 14, 2)->default(0);
            $table->string('status', 20)->default('UNPAID');
            $table->string('recorded_by')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
