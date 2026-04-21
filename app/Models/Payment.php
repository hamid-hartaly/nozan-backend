<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * @property int $id
 * @property int $service_job_id
 * @property string|float|int $amount_iqd
 * @property string|null $method
 * @property string|null $reference
 * @property string|null $receipt_number
 * @property string|null $recorded_by
 * @property Carbon|null $recorded_at
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_job_id',
        'invoice_payment_id',
        'amount_iqd',
        'method',
        'reference',
        'receipt_number',
        'recorded_by',
        'recorded_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount_iqd' => 'decimal:2',
            'recorded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Payment $payment): void {
            $payment->recorded_by ??= Auth::user()?->name;
            $payment->recorded_at ??= now();
        });

        static::created(function (Payment $payment): void {
            if ($payment->receipt_number) {
                return;
            }

            $payment->receipt_number = sprintf('RCP-%s-%06d', now()->format('ymd'), $payment->id);
            $payment->saveQuietly();
        });
    }

    public function serviceJob(): BelongsTo
    {
        return $this->belongsTo(ServiceJob::class, 'service_job_id');
    }

    public function invoicePayment(): BelongsTo
    {
        return $this->belongsTo(InvoicePayment::class);
    }
}
