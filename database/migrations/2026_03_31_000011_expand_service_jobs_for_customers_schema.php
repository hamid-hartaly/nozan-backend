<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_jobs', function (Blueprint $table): void {
            $table->foreignId('customer_record_id')->nullable()->after('job_code')->constrained('customers')->nullOnDelete();
            $table->string('device_model')->nullable()->after('tv_model');
            $table->string('device_type')->nullable()->after('device_model');
            $table->text('problem')->nullable()->after('issue');
            $table->text('notes')->nullable()->after('technician_notes');
            $table->decimal('estimated_price', 12, 2)->nullable()->after('estimated_price_iqd');
            $table->decimal('final_price', 12, 2)->nullable()->after('final_price_iqd');
            $table->timestamp('received_at')->nullable()->after('out_at');
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
            $table->dropConstrainedForeignId('customer_record_id');
            $table->dropColumn([
                'device_model',
                'device_type',
                'problem',
                'notes',
                'estimated_price',
                'final_price',
                'received_at',
            ]);
        });
    }
};
