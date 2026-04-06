<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_jobs', function (Blueprint $table): void {
            $table->text('technician_notes')->nullable()->after('issue');
        });
    }

    public function down(): void
    {
        Schema::table('service_jobs', function (Blueprint $table): void {
            $table->dropColumn('technician_notes');
        });
    }
};
