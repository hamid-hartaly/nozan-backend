<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payments') || Schema::hasColumn('payments', 'invoice_payment_id')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignId('invoice_payment_id')
                ->nullable()
                ->after('service_job_id')
                ->constrained('invoice_payments')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('payments') || ! Schema::hasColumn('payments', 'invoice_payment_id')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('invoice_payment_id');
        });
    }
};
