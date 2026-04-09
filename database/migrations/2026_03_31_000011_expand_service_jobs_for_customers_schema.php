<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('service_jobs')) {
            return;
        }

        Schema::table('service_jobs', function (Blueprint $table): void {
            if (! Schema::hasColumn('service_jobs', 'customer_record_id')) {
                $table->foreignId('customer_record_id')->nullable()->constrained('customers')->nullOnDelete();
            }

            if (! Schema::hasColumn('service_jobs', 'device_model')) {
                $table->string('device_model')->nullable();
            }

            if (! Schema::hasColumn('service_jobs', 'device_type')) {
                $table->string('device_type')->nullable();
            }

            if (! Schema::hasColumn('service_jobs', 'problem')) {
                $table->text('problem')->nullable();
            }

            if (! Schema::hasColumn('service_jobs', 'notes')) {
                $table->text('notes')->nullable();
            }

            if (! Schema::hasColumn('service_jobs', 'estimated_price')) {
                $table->decimal('estimated_price', 12, 2)->nullable();
            }

            if (! Schema::hasColumn('service_jobs', 'final_price')) {
                $table->decimal('final_price', 12, 2)->nullable();
            }

            if (! Schema::hasColumn('service_jobs', 'received_at')) {
                $table->timestamp('received_at')->nullable();
            }
        });

        DB::table('service_jobs')->update([
            'device_model' => DB::raw('tv_model'),
            'device_type' => DB::raw('category'),
            'problem' => DB::raw('issue'),
            'notes' => DB::raw('technician_notes'),
            'estimated_price' => DB::raw('estimated_price_iqd'),
            'final_price' => DB::raw('final_price_iqd'),
            'received_at' => DB::raw('created_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('service_jobs', function (Blueprint $table): void {
            if (Schema::hasColumn('service_jobs', 'customer_record_id')) {
                $table->dropConstrainedForeignId('customer_record_id');
            }

            $columns = [
                'device_model',
                'device_type',
                'problem',
                'notes',
                'estimated_price',
                'final_price',
                'received_at',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('service_jobs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
