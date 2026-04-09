<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('jobs') || ! Schema::hasColumn('jobs', 'job_code')) {
            return;
        }

        Schema::table('jobs', function (Blueprint $table) {
            if (! Schema::hasColumn('jobs', 'customer_name')) {
                $table->string('customer_name')->nullable();
            }

            if (! Schema::hasColumn('jobs', 'customer_phone')) {
                $table->string('customer_phone')->nullable();
            }

            if (! Schema::hasColumn('jobs', 'repair_outcome')) {
                $table->string('repair_outcome')->nullable();
            }

            if (! Schema::hasColumn('jobs', 'repair_notes')) {
                $table->text('repair_notes')->nullable();
            }

            if (! Schema::hasColumn('jobs', 'cannot_repair_reason')) {
                $table->string('cannot_repair_reason')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('jobs', function (Blueprint $table) {
            $columns = [
                'customer_name',
                'customer_phone',
                'repair_outcome',
                'repair_notes',
                'cannot_repair_reason',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('jobs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
