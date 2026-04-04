<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $year
 * @property int $month
 * @property string|float|int $total_revenue_iqd
 * @property string|float|int $total_expenses_iqd
 * @property string|float|int $total_net_iqd
 * @property int $total_jobs
 * @property int $total_finished_jobs
 * @property string|float|int $total_open_debt_iqd
 * @property bool $is_closed
 * @property Carbon|null $generated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MonthlyFinanceSummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'year',
        'month',
        'total_revenue_iqd',
        'total_expenses_iqd',
        'total_net_iqd',
        'total_jobs',
        'total_finished_jobs',
        'total_open_debt_iqd',
        'is_closed',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'total_revenue_iqd' => 'decimal:2',
            'total_expenses_iqd' => 'decimal:2',
            'total_net_iqd' => 'decimal:2',
            'total_open_debt_iqd' => 'decimal:2',
            'is_closed' => 'boolean',
            'generated_at' => 'datetime',
        ];
    }
}
