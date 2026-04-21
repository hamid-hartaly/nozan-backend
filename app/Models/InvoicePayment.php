<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class InvoicePayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
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
        static::creating(function (InvoicePayment $payment): void {
            $payment->recorded_by ??= Auth::user()?->name;
            $payment->recorded_at ??= now();
        });

        static::created(function (InvoicePayment $payment): void {
            if ($payment->receipt_number) {
                return;
            }

            $payment->receipt_number = sprintf('INV-RCP-%s-%06d', now()->format('ymd'), $payment->id);
            $payment->saveQuietly();
        });
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function allocatedPayments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
