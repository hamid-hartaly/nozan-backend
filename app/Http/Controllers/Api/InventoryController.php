<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\ServiceJob;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->roleEnum()->canAccessInventory(), 403);

        $items = InventoryItem::query()
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        $summary = [
            'total_units' => (int) $items->sum('on_hand'),
            'reserved_units' => (int) $items->sum('reserved'),
            'low_stock_count' => (int) $items->filter(fn (InventoryItem $item) => $item->on_hand <= $item->reorder_level)->count(),
            'stock_value_iqd' => (int) $items->sum(fn (InventoryItem $item) => $item->on_hand * $item->unit_cost_iqd),
        ];

        return response()->json([
            'items' => $items->map(fn (InventoryItem $item) => $this->transformItem($item))->all(),
            'summary' => $summary,
        ]);
    }

    public function show(Request $request, InventoryItem $item): JsonResponse
    {
        $this->ensureInventoryAccess($request);

        return response()->json([
            'item' => $this->transformItem($item),
        ]);
    }

    public function movements(Request $request, InventoryItem $item): JsonResponse
    {
        $this->ensureInventoryAccess($request);

        $movements = StockMovement::query()
            ->with(['job:id,job_code', 'createdBy:id,name'])
            ->where('inventory_item_id', $item->id)
            ->latest()
            ->limit(20)
            ->get();

        return response()->json([
            'movements' => $movements->map(fn (StockMovement $movement): array => $this->transformMovement($movement))->all(),
        ]);
    }

    public function storeMovement(Request $request, InventoryItem $item): JsonResponse
    {
        /** @var User $user */
        $user = $this->ensureInventoryAccess($request);

        $payload = $request->validate([
            'type' => ['required', Rule::in(['in', 'out'])],
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:500'],
            'job_code' => ['nullable', 'string', Rule::exists('service_jobs', 'job_code')],
            'unit_cost_iqd' => ['nullable', 'numeric', 'min:0'],
        ]);

        $job = null;

        if (! empty($payload['job_code'])) {
            $job = ServiceJob::query()->where('job_code', $payload['job_code'])->first();
        }

        $movement = DB::transaction(function () use ($payload, $item, $job, $user): StockMovement {
            $quantity = (int) $payload['quantity'];
            $type = (string) $payload['type'];
            $delta = $type === 'in' ? $quantity : -$quantity;

            $item->refresh();
            $nextOnHand = (int) $item->on_hand + $delta;

            if ($nextOnHand < 0) {
                abort(422, 'Not enough stock to complete this movement.');
            }

            $item->on_hand = $nextOnHand;
            $item->quantity = $nextOnHand;

            if (array_key_exists('unit_cost_iqd', $payload) && $payload['unit_cost_iqd'] !== null && $type === 'in') {
                $item->setAttribute('unit_cost_iqd', $payload['unit_cost_iqd']);
            }

            $item->save();

            return StockMovement::create([
                'inventory_item_id' => $item->id,
                'service_job_id' => $job?->id,
                'created_by_user_id' => $user->id,
                'quantity' => $quantity,
                'type' => $type,
                'reason' => $payload['reason'] ?? null,
            ]);
        });

        $movement->loadMissing(['job:id,job_code', 'createdBy:id,name']);

        return response()->json([
            'message' => 'Stock movement recorded successfully.',
            'item' => $this->transformItem($item->fresh()),
            'movement' => $this->transformMovement($movement),
        ], 201);
    }

    private function ensureInventoryAccess(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->roleEnum()->canAccessInventory(), 403);

        return $user;
    }

    /**
     * @return array<string, int|string>
     */
    private function transformItem(InventoryItem $item): array
    {
        return [
            'id' => (string) $item->id,
            'sku' => $item->sku,
            'name' => $item->name,
            'category' => $item->category,
            'on_hand' => $item->on_hand,
            'reserved' => $item->reserved,
            'reorder_level' => $item->reorder_level,
            'unit_cost_iqd' => $item->unit_cost_iqd,
            'supplier' => $item->supplier,
            'location' => $item->location,
        ];
    }

    /**
     * @return array<string, int|string|null>
     */
    private function transformMovement(StockMovement $movement): array
    {
        return [
            'id' => (string) $movement->id,
            'inventory_item_id' => (string) $movement->inventory_item_id,
            'type' => $movement->type,
            'quantity' => (int) $movement->quantity,
            'reason' => $movement->reason,
            'job_code' => $movement->job?->job_code,
            'created_by_name' => $movement->createdBy?->name,
            'created_at' => $movement->created_at?->toIso8601String(),
        ];
    }
}
