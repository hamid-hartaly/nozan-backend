<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Job extends Model
{
    protected $fillable = [
        'job_code',
        'customer_id',
        'customer_name',
        'customer_phone',
        'tv_model',
        'category',
        'priority',
        'issue',
        'status',
        'repair_outcome',
        'repair_notes',
        'cannot_repair_reason',
        'estimated_price_iqd',
        'final_price_iqd',
    ];

    protected static function booted(): void
    {
        static::creating(function (Job $job) {
            if (! filled($job->job_code)) {
                $today = now()->format('ymd');

                $lastTodayJob = static::query()
                    ->whereDate('created_at', today())
                    ->where('job_code', 'like', "NGS-{$today}-%")
                    ->latest('id')
                    ->first();

                $nextNumber = 1;

                if ($lastTodayJob?->job_code) {
                    $parts = explode('-', $lastTodayJob->job_code);
                    $lastSequence = (int) end($parts);
                    $nextNumber = $lastSequence + 1;
                }

                $job->job_code = sprintf('NGS-%s-%04d', $today, $nextNumber);
            }

            if (! filled($job->status)) {
                $job->status = 'pending';
            }
        });
    }
}
