<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('jobs') || ! Schema::hasTable('service_jobs')) {
            return;
        }

        // If the legacy business columns do not exist, this is likely Laravel queue jobs table.
        if (! Schema::hasColumn('jobs', 'job_code')) {
            return;
        }

        $defaultCreatorId = DB::table('users')->orderBy('id')->value('id');

        if (! $defaultCreatorId) {
            return;
        }

        DB::table('jobs')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($defaultCreatorId): void {
                $payload = [];

                foreach ($rows as $row) {
                    $jobCode = trim((string) ($row->job_code ?? ''));
                    if ($jobCode === '') {
                        continue;
                    }

                    $customerName = trim((string) ($row->customer_name ?? ''));
                    $customerPhone = trim((string) ($row->customer_phone ?? ''));
                    $tvModel = trim((string) ($row->tv_model ?? ''));
                    $category = trim((string) ($row->category ?? ''));
                    $issue = trim((string) ($row->issue ?? ''));
                    $status = strtoupper(trim((string) ($row->status ?? 'PENDING')));

                    if (! in_array($status, ['PENDING', 'REPAIR', 'FINISHED', 'OUT', 'CHECKED_OUT'], true)) {
                        $status = 'PENDING';
                    }

                    $payload[] = [
                        'job_code' => $jobCode,
                        'customer_id' => $row->customer_id ? (string) $row->customer_id : null,
                        'customer_record_id' => null,
                        'customer_name' => $customerName !== '' ? $customerName : 'Unknown customer',
                        'customer_phone' => $customerPhone !== '' ? $customerPhone : 'Unknown phone',
                        'tv_model' => $tvModel !== '' ? $tvModel : 'Unknown model',
                        'device_model' => $tvModel !== '' ? $tvModel : 'Unknown model',
                        'device_type' => $category !== '' ? strtoupper($category) : 'OTHER',
                        'category' => $category !== '' ? strtoupper($category) : 'OTHER',
                        'priority' => strtolower(trim((string) ($row->priority ?? 'normal'))),
                        'issue' => $issue !== '' ? $issue : 'No issue specified',
                        'problem' => $issue !== '' ? $issue : 'No issue specified',
                        'status' => $status,
                        'estimated_price_iqd' => $row->estimated_price_iqd ?? null,
                        'estimated_price' => $row->estimated_price_iqd ?? null,
                        'final_price_iqd' => $row->final_price_iqd ?? null,
                        'final_price' => $row->final_price_iqd ?? null,
                        'created_by_user_id' => $row->created_by_user_id ?? $defaultCreatorId,
                        'notes' => $row->repair_notes ?? null,
                        'resolution' => null,
                        'not_fixed_reason' => null,
                        'received_at' => $row->created_at ?? now(),
                        'finished_at' => $row->finished_at ?? null,
                        'out_at' => $row->out_at ?? null,
                        'created_at' => $row->created_at ?? now(),
                        'updated_at' => $row->updated_at ?? now(),
                    ];
                }

                if ($payload !== []) {
                    DB::table('service_jobs')->insertOrIgnore($payload);
                }
            }, 'id');
    }

    public function down(): void
    {
        // Data backfill migration; intentionally non-destructive on rollback.
    }
};
