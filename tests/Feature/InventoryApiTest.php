<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InventoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_internal_roles_can_list_inventory_with_summary(): void
    {
        InventoryItem::query()->create([
            'sku' => 'TEST-001',
            'name' => 'Test main board',
            'category' => 'Board',
            'on_hand' => 3,
            'reserved' => 1,
            'reorder_level' => 4,
            'unit_cost_iqd' => 50000,
            'supplier' => 'Test Supplier',
            'location' => 'Shelf T1',
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => 'staff']));

        $response = $this->getJson('/api/inventory');

        $expectedItems = InventoryItem::query()->get();

        $response
            ->assertOk()
            ->assertJsonFragment(['sku' => 'TEST-001'])
            ->assertJsonPath('summary.total_units', (int) $expectedItems->sum('on_hand'))
            ->assertJsonPath('summary.reserved_units', (int) $expectedItems->sum('reserved'))
            ->assertJsonPath('summary.low_stock_count', (int) $expectedItems->filter(fn (InventoryItem $item) => $item->on_hand <= $item->reorder_level)->count())
            ->assertJsonPath('summary.stock_value_iqd', (int) $expectedItems->sum(fn (InventoryItem $item) => $item->on_hand * $item->unit_cost_iqd));
    }

    public function test_customers_cannot_access_inventory(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->getJson('/api/inventory')
            ->assertForbidden();
    }
}
