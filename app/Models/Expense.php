<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * @property int $id
 * @property Carbon|null $expense_date
 * @property string $title
 * @property string $category
 * @property string|float|int $amount_iqd
 * @property string|null $note
 * @property string|null $recorded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'expense_date',
        'title',
        'category',
        'amount_iqd',
        'note',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'amount_iqd' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Expense $expense): void {
            $expense->recorded_by ??= Auth::user()?->name;
        });
    }
}
