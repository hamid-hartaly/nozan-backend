<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * @property int $id
 * @property string|null $invoice_number
 * @property string|null $customer_name
 * @property string|null $customer_phone
 * @property string|null $customer_address
 * @property string|float|int $subtotal_iqd
 * @property string|float|int $discount_iqd
 * @property string|float|int $tax_iqd
 * @property string|float|int $total_iqd
 * @property string|float|int $paid_iqd
 * @property string|float|int $outstanding_iqd
 * @property string $status
 * @property string|null $recorded_by
 * @property Carbon|null $issued_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'customer_name',
        'customer_phone',
        'customer_address',
        'subtotal_iqd',
        'discount_iqd',
        'tax_iqd',
        'total_iqd',
        'paid_iqd',
        'outstanding_iqd',
        'status',
        'recorded_by',
        'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal_iqd' => 'decimal:2',
            'discount_iqd' => 'decimal:2',
            'tax_iqd' => 'decimal:2',
            'total_iqd' => 'decimal:2',
            'paid_iqd' => 'decimal:2',
            'outstanding_iqd' => 'decimal:2',
            'issued_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Invoice $invoice): void {
            if (blank($invoice->invoice_number)) {
                $invoice->invoice_number = sprintf('FIN-%s-%04d', now()->format('ymd'), random_int(1, 9999));
            }

            $invoice->recorded_by ??= Auth::user()?->name;
            $invoice->issued_at ??= now();
        });
    }

    public function serviceJobs(): BelongsToMany
    {
        return $this->belongsToMany(ServiceJob::class, 'invoice_jobs', 'invoice_id', 'service_job_id');
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(InvoiceLineItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }
}
