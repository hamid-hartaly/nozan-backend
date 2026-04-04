<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_jobs', function (Blueprint $table): void {
            if (! Schema::hasColumn('service_jobs', 'invoice_items')) {
                $table->json('invoice_items')->nullable()->after('notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('service_jobs', function (Blueprint $table): void {
            if (Schema::hasColumn('service_jobs', 'invoice_items')) {
                $table->dropColumn('invoice_items');
            }
        });
    }
};
