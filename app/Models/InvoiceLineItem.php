<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $invoice_id
 * @property string $item_type
 * @property string $description
 * @property string|float|int $quantity
 * @property string|float|int $unit_price_iqd
 * @property string|float|int $line_total_iqd
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class InvoiceLineItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'item_type',
        'description',
        'quantity',
        'unit_price_iqd',
        'line_total_iqd',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price_iqd' => 'decimal:2',
            'line_total_iqd' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
