<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoices')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table): void {
            if (! Schema::hasColumn('invoices', 'customer_phone')) {
                $table->string('customer_phone')->nullable()->after('customer_name');
            }

            if (! Schema::hasColumn('invoices', 'customer_address')) {
                $table->string('customer_address')->nullable()->after('customer_phone');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('invoices')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table): void {
            if (Schema::hasColumn('invoices', 'customer_address')) {
                $table->dropColumn('customer_address');
            }

            if (Schema::hasColumn('invoices', 'customer_phone')) {
                $table->dropColumn('customer_phone');
            }
        });
    }
};
