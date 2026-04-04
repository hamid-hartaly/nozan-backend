<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_jobs', function (Blueprint $table): void {
            if (! Schema::hasColumn('service_jobs', 'invoice_discount_iqd')) {
                $table->decimal('invoice_discount_iqd', 12, 2)->default(0)->after('invoice_items');
            }

            if (! Schema::hasColumn('service_jobs', 'invoice_tax_iqd')) {
                $table->decimal('invoice_tax_iqd', 12, 2)->default(0)->after('invoice_discount_iqd');
            }
        });
    }

    public function down(): void
    {
        Schema::table('service_jobs', function (Blueprint $table): void {
            if (Schema::hasColumn('service_jobs', 'invoice_tax_iqd')) {
                $table->dropColumn('invoice_tax_iqd');
            }

            if (Schema::hasColumn('service_jobs', 'invoice_discount_iqd')) {
                $table->dropColumn('invoice_discount_iqd');
            }
        });
    }
};
