<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $sku
 * @property string $category
 * @property int $on_hand
 * @property int $reserved
 * @property int $reorder_level
 * @property int $quantity
 * @property string|float|int|null $unit_cost_iqd
 * @property string|float|int|null $buy_price
 * @property string|float|int|null $sell_price
 * @property string|float|int|null $sell_price_iqd
 * @property string|null $supplier
 * @property int|null $low_stock_threshold
 * @property string|null $location
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int $current_stock
 */
class InventoryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'sku',
        'category',
        'on_hand',
        'reserved',
        'reorder_level',
        'quantity',
        'unit_cost_iqd',
        'buy_price',
        'sell_price',
        'sell_price_iqd',
        'supplier',
        'low_stock_threshold',
        'location',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'unit_cost_iqd' => 'decimal:2',
            'buy_price' => 'decimal:2',
            'sell_price' => 'decimal:2',
            'sell_price_iqd' => 'decimal:2',
        ];
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'inventory_item_id');
    }
}
