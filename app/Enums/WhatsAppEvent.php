<?php

namespace App\Enums;

enum WhatsAppEvent: string
{
    case JOB_CREATED = 'job.created';
    case JOB_REPAIR_STARTED = 'job.repair_started';
    case JOB_FINISHED = 'job.finished';
    case JOB_READY_FOR_PICKUP = 'job.ready_for_pickup';
    case PAYMENT_RECORDED = 'payment.recorded';

    public function templateName(): string
    {
        return match ($this) {
            self::JOB_CREATED => 'new_job_created',
            self::JOB_REPAIR_STARTED => 'repair_started',
            self::JOB_FINISHED => 'job_finished',
            self::JOB_READY_FOR_PICKUP => 'ready_for_pickup',
            self::PAYMENT_RECORDED => 'payment_recorded',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::JOB_CREATED => 'تێدەر بنەر لێ کریا',
            self::JOB_REPAIR_STARTED => 'درووستکاری دەست پێکرد',
            self::JOB_FINISHED => 'درووستکاری تێچوو',
            self::JOB_READY_FOR_PICKUP => 'تێدەر ئامادەیە',
            self::PAYMENT_RECORDED => 'پارە وەردەگرتن',
        };
    }

    public static function canAutoSend(self $event): bool
    {
        return in_array($event, [
            self::JOB_CREATED,
            self::JOB_REPAIR_STARTED,
            self::JOB_FINISHED,
            self::JOB_READY_FOR_PICKUP,
        ], true);
    }
}
