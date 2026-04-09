<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $job_code
 * @property string|null $customer_id
 * @property int|null $customer_record_id
 * @property string|null $customer_name
 * @property string|null $customer_phone
 * @property string|null $tv_model
 * @property string|null $device_model
 * @property string|null $device_type
 * @property string|null $category
 * @property string|null $priority
 * @property string|null $issue
 * @property string|null $problem
 * @property string|null $technician_notes
 * @property string|float|int|null $estimated_price_iqd
 * @property string|float|int|null $estimated_price
 * @property string|float|int|null $final_price_iqd
 * @property string|float|int|null $final_price
 * @property string|float|int|null $repair_cost_iqd
 * @property string|float|int|null $payment_received_iqd
 * @property bool $whatsapp_sent
 * @property bool $whatsapp_created_sent
 * @property bool $whatsapp_repair_started_sent
 * @property bool $whatsapp_finished_sent
 * @property bool $whatsapp_pickup_sent
 * @property string|null $status
 * @property int|null $assigned_staff_uid
 * @property string|null $assigned_technician
 * @property int|null $created_by_user_id
 * @property string|null $notes
 * @property string|null $resolution
 * @property string|null $not_fixed_reason
 * @property Carbon|null $received_at
 * @property Carbon|null $repair_started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $out_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read float $total_paid
 * @property-read float $remaining_balance
 */
class ServiceJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_code',
        'customer_id',
        'customer_record_id',
        'customer_name',
        'customer_phone',
        'tv_model',
        'device_model',
        'device_type',
        'category',
        'priority',
        'issue',
        'problem',
        'technician_notes',
        'estimated_price_iqd',
        'estimated_price',
        'final_price_iqd',
        'final_price',
        'repair_cost_iqd',
        'payment_received_iqd',
        'whatsapp_sent',
        'whatsapp_created_sent',
        'whatsapp_repair_started_sent',
        'whatsapp_finished_sent',
        'whatsapp_pickup_sent',
        'status',
        'assigned_staff_uid',
        'assigned_technician',
        'created_by_user_id',
        'notes',
        'received_at',
        'repair_started_at',
        'finished_at',
        'out_at',
        'invoice_items',
        'invoice_discount_iqd',
        'invoice_tax_iqd',
        'resolution',
        'not_fixed_reason',
    ];

    protected function casts(): array
    {
        return [
            'estimated_price_iqd' => 'decimal:2',
            'final_price_iqd' => 'decimal:2',
            'repair_cost_iqd' => 'decimal:2',
            'payment_received_iqd' => 'decimal:2',
            'estimated_price' => 'decimal:2',
            'final_price' => 'decimal:2',
            'whatsapp_sent' => 'boolean',
            'whatsapp_created_sent' => 'boolean',
            'whatsapp_repair_started_sent' => 'boolean',
            'whatsapp_finished_sent' => 'boolean',
            'whatsapp_pickup_sent' => 'boolean',
            'received_at' => 'datetime',
            'repair_started_at' => 'datetime',
            'finished_at' => 'datetime',
            'out_at' => 'datetime',
            'invoice_items' => 'array',
            'invoice_discount_iqd' => 'decimal:2',
            'invoice_tax_iqd' => 'decimal:2',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'job_code';
    }

    protected static function booted(): void
    {
        static::creating(function (ServiceJob $job): void {
            if (blank($job->job_code)) {
                $job->job_code = 'NGS-' . now()->format('ymd') . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            }

            $job->customer_name ??= $job->customer?->name;
            $job->customer_phone ??= $job->customer?->phone;
            $job->received_at ??= now();
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_record_id');
    }

    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_staff_uid');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'service_job_id');
    }

    public function invoices(): BelongsToMany
    {
        return $this->belongsToMany(Invoice::class, 'invoice_jobs', 'service_job_id', 'invoice_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'service_job_id');
    }

    public function jobImages(): HasMany
    {
        return $this->hasMany(JobImage::class, 'service_job_id');
    }

    public function getTotalPaidAttribute(): float
    {
        return (float) $this->payments()->sum('amount_iqd');
    }

    public function getRemainingBalanceAttribute(): float
    {
        return max(0, (float) ($this->final_price_iqd ?: $this->estimated_price_iqd) - $this->total_paid);
    }

    public function markWhatsAppEventSent(string $event): self
    {
        return match ($event) {
            'created' => $this->forceFill(['whatsapp_created_sent' => true]),
            'repair_started' => $this->forceFill(['whatsapp_repair_started_sent' => true]),
            'finished' => $this->forceFill(['whatsapp_finished_sent' => true]),
            'pickup' => $this->forceFill(['whatsapp_pickup_sent' => true]),
            default => $this,
        };
    }

    public function wasWhatsAppEventSent(string $event): bool
    {
        return match ($event) {
            'created' => (bool) $this->whatsapp_created_sent,
            'repair_started' => (bool) $this->whatsapp_repair_started_sent,
            'finished' => (bool) $this->whatsapp_finished_sent,
            'pickup' => (bool) $this->whatsapp_pickup_sent,
            default => false,
        };
    }
}

