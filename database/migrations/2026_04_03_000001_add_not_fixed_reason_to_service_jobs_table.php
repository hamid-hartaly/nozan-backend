<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_jobs', function (Blueprint $table) {
            $table->string('not_fixed_reason', 40)->nullable()->after('resolution');
        });
    }

    public function down(): void
    {
        Schema::table('service_jobs', function (Blueprint $table) {
            $table->dropColumn('not_fixed_reason');
        });
    }
};
