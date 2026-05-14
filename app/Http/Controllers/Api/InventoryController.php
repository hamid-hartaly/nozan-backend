<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryItemImage;
use App\Models\ServiceJob;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $this->ensureInventoryAccess($request);

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(['COF', 'PCB', 'T.con', 'panel'])],
            'model' => ['nullable', 'string', 'max:255'],
            'part_number' => ['required', 'string', 'max:255'],
            'similar_products' => ['nullable', 'array'],
            'similar_products.*' => ['string', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
            'unit_cost_iqd' => ['required', 'numeric', 'min:0'],
            'photo' => ['nullable', 'image', 'max:3072'],
            'photos' => ['nullable', 'array', 'max:8'],
            'photos.*' => ['image', 'max:3072'],
        ]);

        $similarProducts = collect($payload['similar_products'] ?? [])
            ->map(static fn ($value): string => trim((string) $value))
            ->filter(static fn (string $value): bool => $value !== '')
            ->values()
            ->all();

        $sku = $this->generateUniqueSku((string) $payload['part_number']);

        $item = InventoryItem::create([
            'name' => trim((string) $payload['name']),
            'model' => trim((string) ($payload['model'] ?? '')) ?: null,
            'part_number' => trim((string) $payload['part_number']),
            'similar_products' => $similarProducts,
            'sku' => $sku,
            'category' => (string) $payload['category'],
            'on_hand' => 0,
            'reserved' => 0,
            'reorder_level' => 0,
            'quantity' => 0,
            'unit_cost_iqd' => $payload['unit_cost_iqd'],
            'buy_price' => $payload['unit_cost_iqd'],
            'location' => trim((string) $payload['location']),
            'image_path' => null,
            'supplier' => null,
        ]);

        $this->storeUploadedImages($request, $item);
        $item = $item->fresh(['inventoryImages']);

        return response()->json([
            'message' => 'Inventory item created successfully.',
            'item' => $this->transformItem($item),
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->roleEnum()->canAccessInventory(), 403);

        $items = InventoryItem::query()
            ->with('inventoryImages')
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
        $item->loadMissing('inventoryImages');

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

    public function recordMovement(Request $request, InventoryItem $item): JsonResponse
    {
        return $this->storeMovement($request, $item);
    }

    public function update(Request $request, InventoryItem $item): JsonResponse
    {
        $this->ensureInventoryAccess($request);

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(['COF', 'PCB', 'T.con', 'panel'])],
            'model' => ['nullable', 'string', 'max:255'],
            'part_number' => ['required', 'string', 'max:255'],
            'similar_products' => ['nullable', 'array'],
            'similar_products.*' => ['string', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
            'unit_cost_iqd' => ['required', 'numeric', 'min:0'],
            'photo' => ['nullable', 'image', 'max:3072'],
            'photos' => ['nullable', 'array', 'max:8'],
            'photos.*' => ['image', 'max:3072'],
            'remove_photo' => ['nullable', 'boolean'],
            'remove_image_ids' => ['nullable', 'array'],
            'remove_image_ids.*' => ['integer', Rule::exists('inventory_item_images', 'id')],
        ]);

        $similarProducts = collect($payload['similar_products'] ?? [])
            ->map(static fn ($value): string => trim((string) $value))
            ->filter(static fn (string $value): bool => $value !== '')
            ->values()
            ->all();

        $item->loadMissing('inventoryImages');
        $this->migrateLegacyImageToRelation($item);

        if ($request->boolean('remove_photo')) {
            foreach ($item->inventoryImages as $image) {
                Storage::disk('public')->delete($image->image_path);
                $image->delete();
            }
            $item->setRelation('inventoryImages', collect());
        }

        $removeImageIds = collect($payload['remove_image_ids'] ?? [])->map(static fn ($value): int => (int) $value)->all();
        if ($removeImageIds !== []) {
            $imagesToRemove = $item->inventoryImages->whereIn('id', $removeImageIds);
            foreach ($imagesToRemove as $image) {
                Storage::disk('public')->delete($image->image_path);
                $image->delete();
            }
        }

        $item->fill([
            'name' => trim((string) $payload['name']),
            'model' => trim((string) ($payload['model'] ?? '')) ?: null,
            'part_number' => trim((string) $payload['part_number']),
            'similar_products' => $similarProducts,
            'category' => (string) $payload['category'],
            'location' => trim((string) $payload['location']),
            'unit_cost_iqd' => $payload['unit_cost_iqd'],
            'buy_price' => $payload['unit_cost_iqd'],
        ]);

        if ($item->sku === null || trim((string) $item->sku) === '') {
            $item->sku = $this->generateUniqueSku((string) $payload['part_number']);
        }

        $item->save();
        $this->storeUploadedImages($request, $item);
        $this->syncPrimaryImagePath($item->fresh(['inventoryImages']));

        return response()->json([
            'message' => 'Inventory item updated successfully.',
            'item' => $this->transformItem($item->fresh(['inventoryImages'])),
        ]);
    }

    public function destroy(Request $request, InventoryItem $item): JsonResponse
    {
        $this->ensureInventoryAccess($request);

        DB::transaction(function () use ($item): void {
            StockMovement::query()->where('inventory_item_id', $item->id)->delete();

            foreach ($item->inventoryImages()->get() as $image) {
                Storage::disk('public')->delete($image->image_path);
            }

            if ($item->image_path) {
                Storage::disk('public')->delete($item->image_path);
            }

            $item->inventoryImages()->delete();

            $item->delete();
        });

        return response()->json([
            'message' => 'Inventory item deleted successfully.',
        ]);
    }

    private function ensureInventoryAccess(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->roleEnum()->canAccessInventory(), 403);

        return $user;
    }

    private function generateUniqueSku(string $partNumber): string
    {
        $normalized = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '-', trim($partNumber)) ?: 'PART');
        $base = trim($normalized, '-');
        $candidate = $base;
        $suffix = 1;

        while (InventoryItem::query()->where('sku', $candidate)->exists()) {
            $suffix++;
            $candidate = sprintf('%s-%d', $base, $suffix);
        }

        return $candidate;
    }

    /**
     * @return array<string, int|string>
     */
    private function transformItem(InventoryItem $item): array
    {
        /** @var FilesystemAdapter $publicDisk */
        $publicDisk = Storage::disk('public');
        $item->loadMissing('inventoryImages');
        $images = $item->inventoryImages
            ->map(fn (InventoryItemImage $image): array => [
                'id' => (string) $image->id,
                'path' => $image->image_path,
                'url' => $publicDisk->url($image->image_path),
                'created_at' => $image->created_at?->toIso8601String(),
            ])
            ->values();

        if ($images->isEmpty() && $item->image_path) {
            $images = collect([[
                'id' => 'legacy',
                'path' => $item->image_path,
                'url' => $publicDisk->url($item->image_path),
                'created_at' => $item->updated_at?->toIso8601String(),
            ]]);
        }

        $primaryImageUrl = $images->first()['url'] ?? null;

        return [
            'id' => (string) $item->id,
            'sku' => $item->sku,
            'name' => $item->name,
            'model' => $item->model,
            'part_number' => $item->part_number,
            'similar_products' => $item->similar_products ?? [],
            'category' => $item->category,
            'on_hand' => $item->on_hand,
            'reserved' => $item->reserved,
            'reorder_level' => $item->reorder_level,
            'unit_cost_iqd' => $item->unit_cost_iqd,
            'supplier' => $item->supplier ?? '',
            'location' => $item->location ?? '',
            'image_url' => $primaryImageUrl,
            'images' => $images->all(),
        ];
    }

    private function storeUploadedImages(Request $request, InventoryItem $item): void
    {
        $uploads = $this->gatherUploadedImages($request);
        if ($uploads === []) {
            $this->syncPrimaryImagePath($item->fresh(['inventoryImages']));
            return;
        }

        $item->loadMissing('inventoryImages');
        $sortOrder = (int) $item->inventoryImages->count();

        foreach ($uploads as $upload) {
            $path = $upload->store('inventory-items', 'public');
            $item->inventoryImages()->create([
                'image_path' => $path,
                'sort_order' => $sortOrder,
            ]);
            $sortOrder++;
        }

        $this->syncPrimaryImagePath($item->fresh(['inventoryImages']));
    }

    /**
     * @return array<int, UploadedFile>
     */
    private function gatherUploadedImages(Request $request): array
    {
        $uploads = [];

        if ($request->hasFile('photo')) {
            $single = $request->file('photo');
            if ($single instanceof UploadedFile) {
                $uploads[] = $single;
            }
        }

        foreach ($request->file('photos', []) as $file) {
            if ($file instanceof UploadedFile) {
                $uploads[] = $file;
            }
        }

        return $uploads;
    }

    private function migrateLegacyImageToRelation(InventoryItem $item): void
    {
        $item->loadMissing('inventoryImages');

        if (! $item->image_path || $item->inventoryImages->isNotEmpty()) {
            return;
        }

        $item->inventoryImages()->create([
            'image_path' => $item->image_path,
            'sort_order' => 0,
        ]);

        $item->load('inventoryImages');
    }

    private function syncPrimaryImagePath(InventoryItem $item): void
    {
        $item->loadMissing('inventoryImages');
        $primaryPath = $item->inventoryImages->first()?->image_path;

        if ($item->image_path !== $primaryPath) {
            $item->image_path = $primaryPath;
            $item->save();
        }
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
