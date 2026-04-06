<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\JobImage;
use App\Models\ServiceJob;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class JobController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ServiceJob::query()
            ->with(['assignedStaff:id,name', 'createdBy:id,name,role', 'jobImages'])
            ->latest();

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('job_code', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%")
                    ->orWhere('tv_model', 'like', "%{$search}%")
                    ->orWhere('assigned_technician', 'like', "%{$search}%")
                    ->orWhereHas('createdBy', function ($userQuery) use ($search): void {
                        $userQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($status = strtoupper($request->string('status')->toString())) {
            if (in_array($status, ['OUT', 'CHECKED_OUT'], true)) {
                $query->whereIn('status', ['OUT', 'CHECKED_OUT']);
            } else {
                $query->where('status', $status);
            }
        }

        if ($technician = $request->string('technician')->toString()) {
            $query->where('assigned_staff_uid', $technician);
        }

        $jobs = $query->paginate($request->integer('per_page', 20));

        return response()->json($jobs->through(fn (ServiceJob $job): array => $this->transformJob($job)));
    }

    public function overdueSummary(): JsonResponse
    {
        $threshold = Carbon::now()->subDays(7);

        $jobs = ServiceJob::query()
            ->select(['id', 'job_code', 'customer_name', 'category', 'status', 'created_at'])
            ->whereNotIn('status', ['OUT', 'CHECKED_OUT'])
            ->where('created_at', '<=', $threshold)
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'count' => $jobs->count(),
            'jobs' => $jobs->take(5)->map(function (ServiceJob $job): array {
                $createdAt = $job->created_at ? Carbon::parse($job->created_at) : Carbon::now();

                return [
                    'id' => (string) $job->id,
                    'job_code' => (string) $job->job_code,
                    'customer_name' => (string) $job->customer_name,
                    'category' => (string) $job->category,
                    'status' => (string) $job->status,
                    'created_at' => $createdAt->toISOString(),
                    'overdue_days' => max(8, $createdAt->diffInDays(Carbon::now())),
                ];
            })->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $payload = $request->validate([
            'customer_id' => ['sometimes', 'nullable', 'integer', Rule::exists('customers', 'id')],
            'customer_name' => ['required_without:customer_id', 'nullable', 'string', 'max:255'],
            'customer_phone' => ['required_without:customer_id', 'nullable', 'string', 'max:50'],
            'tv_model' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'priority' => ['nullable', 'string', 'max:50'],
            'issue' => ['required', 'string'],
            'estimated_price_iqd' => ['nullable', 'numeric', 'min:0'],
            'estimated_price' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'assigned_staff_uid' => ['nullable', 'integer', Rule::exists('users', 'id')],
        ]);

        $customer = null;

        if (! empty($payload['customer_id'])) {
            $customer = Customer::find($payload['customer_id']);
        } elseif (! empty($payload['customer_phone'])) {
            $customer = Customer::firstOrCreate(
                ['phone' => $payload['customer_phone']],
                ['name' => $payload['customer_name'] ?: 'Unknown customer'],
            );
        }

        $assignedStaff = isset($payload['assigned_staff_uid'])
            ? User::find($payload['assigned_staff_uid'])
            : null;

        $job = ServiceJob::create([
            'customer_id' => $customer ? (string) $customer->id : null,
            'customer_record_id' => $customer?->id,
            'customer_name' => $customer?->name ?: ($payload['customer_name'] ?: 'Unknown customer'),
            'customer_phone' => $customer?->phone ?: ($payload['customer_phone'] ?: 'Unknown phone'),
            'tv_model' => $payload['tv_model'],
            'device_model' => $payload['tv_model'],
            'category' => strtoupper((string) ($payload['category'] ?? 'OTHER')),
            'device_type' => strtoupper((string) ($payload['category'] ?? 'OTHER')),
            'priority' => strtolower((string) ($payload['priority'] ?? 'normal')),
            'issue' => $payload['issue'],
            'problem' => $payload['issue'],
            'estimated_price_iqd' => $payload['estimated_price_iqd'] ?? $payload['estimated_price'] ?? 0,
            'estimated_price' => $payload['estimated_price_iqd'] ?? $payload['estimated_price'] ?? 0,
            'status' => 'PENDING',
            'assigned_staff_uid' => $assignedStaff?->id,
            'notes' => $payload['notes'] ?? null,
            'created_by_user_id' => $user->id,
        ]);

        // Send WhatsApp notification for new job
        $whatsappService = new WhatsAppService();
        if ($whatsappService->sendJobCreatedMessage($job)) {
            $job->update(['whatsapp_created_sent' => true]);
        }

        return response()->json([
            'message' => 'Job created successfully.',
            'job' => $this->transformJob($job->fresh(['assignedStaff:id,name', 'createdBy:id,name,role'])),
        ], 201);
    }

    public function show(ServiceJob $job): JsonResponse
    {
        $job->loadMissing(['assignedStaff:id,name', 'createdBy:id,name,role', 'jobImages']);

        return response()->json(['job' => $this->transformJob($job)]);
    }

    public function update(Request $request, ServiceJob $job): JsonResponse
    {
        $payload = $request->validate([
            'tv_model' => ['sometimes', 'string', 'max:255'],
            'category' => ['sometimes', 'string', 'max:255'],
            'priority' => ['sometimes', 'string', 'max:50'],
            'issue' => ['sometimes', 'string'],
            'estimated_price_iqd' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'final_price_iqd' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ]);

        if (array_key_exists('tv_model', $payload)) {
            $job->tv_model = $payload['tv_model'];
            $job->device_model = $payload['tv_model'];
        }

        if (array_key_exists('category', $payload)) {
            $job->category = strtoupper((string) $payload['category']);
            $job->device_type = strtoupper((string) $payload['category']);
        }

        if (array_key_exists('priority', $payload)) {
            $job->priority = strtolower((string) $payload['priority']);
        }

        if (array_key_exists('issue', $payload)) {
            $job->issue = $payload['issue'];
            $job->problem = $payload['issue'];
        }

        if (array_key_exists('estimated_price_iqd', $payload)) {
            $job->estimated_price_iqd = $payload['estimated_price_iqd'];
            $job->estimated_price = $payload['estimated_price_iqd'];
        }

        if (array_key_exists('final_price_iqd', $payload)) {
            $job->final_price_iqd = $payload['final_price_iqd'];
            $job->final_price = $payload['final_price_iqd'];
        }

        $job->save();

        return response()->json([
            'message' => 'Job details updated successfully.',
            'job' => $this->transformJob($job->fresh(['assignedStaff:id,name', 'createdBy:id,name,role', 'jobImages'])),
        ]);
    }

    public function uploadImage(Request $request, ServiceJob $job): JsonResponse
    {
        $payload = $request->validate([
            'image' => ['required', 'image', 'max:8192'],
            'label' => ['nullable', 'string', 'max:255'],
        ]);

        $path = $request->file('image')->store("job-images/{$job->job_code}", 'public');

        $job->jobImages()->create([
            'image_path' => $path,
            'label' => $payload['label'] ?? null,
        ]);

        return response()->json([
            'message' => 'Job image uploaded successfully.',
            'job' => $this->transformJob($job->fresh(['assignedStaff:id,name', 'createdBy:id,name,role', 'jobImages'])),
        ], 201);
    }

    public function deleteImage(ServiceJob $job, JobImage $image): JsonResponse
    {
        abort_unless((int) $image->job_id === (int) $job->id, 404);

        if ($image->image_path && Storage::disk('public')->exists($image->image_path)) {
            Storage::disk('public')->delete($image->image_path);
        }

        $image->delete();

        return response()->json([
            'message' => 'Job image deleted successfully.',
            'job' => $this->transformJob($job->fresh(['assignedStaff:id,name', 'createdBy:id,name,role', 'jobImages'])),
        ]);
    }

    public function updateStatus(Request $request, ServiceJob $job): JsonResponse
    {
        $payload = $request->validate([
            'status' => ['nullable', Rule::in(['PENDING', 'REPAIR', 'FINISHED', 'OUT', 'CHECKED_OUT'])],
            'final_price_iqd' => ['nullable', 'numeric', 'min:0'],
            'assigned_staff_uid' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'notes' => ['nullable', 'string'],
            'technician_notes' => ['nullable', 'string'],
            'resolution' => ['nullable', Rule::in(['FIXED', 'NOT_FIXED'])],
            'not_fixed_reason' => ['nullable', Rule::in(['MATERIAL_UNAVAILABLE', 'NOT_REPAIRABLE', 'OWNER_CANCELLED'])],
        ]);

        $nextStatus = $payload['status'] ?? $this->nextStatus($job->status);
        $job->status = $this->normalizeStatus((string) $nextStatus);

        if (array_key_exists('final_price_iqd', $payload)) {
            $job->final_price_iqd = $payload['final_price_iqd'];
            $job->final_price = $payload['final_price_iqd'];
        }

        if (array_key_exists('notes', $payload)) {
            $job->notes = $payload['notes'];
        }

        if (array_key_exists('technician_notes', $payload)) {
            $job->technician_notes = $payload['technician_notes'];

            if (! array_key_exists('notes', $payload)) {
                $job->notes = $payload['technician_notes'];
            }
        }

        if (array_key_exists('resolution', $payload)) {
            $job->resolution = $payload['resolution'];
        }

        if (array_key_exists('not_fixed_reason', $payload)) {
            $job->not_fixed_reason = $payload['not_fixed_reason'];
        }

        if ($job->resolution === 'FIXED') {
            $job->not_fixed_reason = null;
        }

        if (array_key_exists('assigned_staff_uid', $payload)) {
            $assignedStaff = $payload['assigned_staff_uid'] ? User::find($payload['assigned_staff_uid']) : null;
            $job->assigned_staff_uid = $assignedStaff?->id;
            $job->assigned_technician = $assignedStaff?->name;
        }

        if ($job->status === 'REPAIR' && blank($job->repair_started_at)) {
            $job->repair_started_at = Carbon::now();
        }

        if ($job->status === 'FINISHED' && blank($job->finished_at)) {
            $job->finished_at = Carbon::now();
        }

        if (in_array($job->status, ['OUT', 'CHECKED_OUT'], true) && blank($job->out_at)) {
            $job->out_at = Carbon::now();
        }

        $job->save();

        // Send WhatsApp notifications based on status change
        $whatsappService = new WhatsAppService();
        if ($job->status === 'REPAIR' && !$job->whatsapp_repair_started_sent && $whatsappService->sendRepairStartedMessage($job)) {
            $job->update(['whatsapp_repair_started_sent' => true]);
        }

        if ($job->status === 'FINISHED' && !$job->whatsapp_finished_sent && $whatsappService->sendJobFinishedMessage($job)) {
            $job->update(['whatsapp_finished_sent' => true]);
        }

        if (in_array($job->status, ['OUT', 'CHECKED_OUT'], true) && !$job->whatsapp_pickup_sent && $whatsappService->sendReadyForPickupMessage($job)) {
            $job->update(['whatsapp_pickup_sent' => true]);
        }

        return response()->json([
            'message' => 'Job updated successfully.',
            'job' => $this->transformJob($job->fresh(['assignedStaff:id,name', 'createdBy:id,name,role'])),
        ]);
    }

    public function assign(Request $request, ServiceJob $job): JsonResponse
    {
        $payload = $request->validate([
            'assigned_staff_uid' => ['nullable', 'integer', Rule::exists('users', 'id')],
        ]);

        $assignedStaff = ! empty($payload['assigned_staff_uid'])
            ? User::find($payload['assigned_staff_uid'])
            : null;

        $job->assigned_staff_uid = $assignedStaff?->id;
        $job->assigned_technician = $assignedStaff?->name;
        $job->save();

        return response()->json([
            'message' => 'Technician assignment updated successfully.',
            'job' => $this->transformJob($job->fresh(['assignedStaff:id,name', 'createdBy:id,name,role'])),
        ]);
    }

    public function updateNotes(Request $request, ServiceJob $job): JsonResponse
    {
        $payload = $request->validate([
            'technician_notes' => ['nullable', 'string'],
        ]);

        $job->technician_notes = $payload['technician_notes'] ?? null;
        $job->notes = $payload['technician_notes'] ?? $job->notes;
        $job->save();

        return response()->json([
            'message' => 'Technician notes updated successfully.',
            'job' => $this->transformJob($job->fresh(['assignedStaff:id,name', 'createdBy:id,name,role'])),
        ]);
    }

    public function markWhatsappSent(ServiceJob $job): JsonResponse
    {
        $job->whatsapp_sent = true;
        $job->save();

        return response()->json([
            'message' => 'WhatsApp status updated successfully.',
            'job' => $this->transformJob($job->fresh(['assignedStaff:id,name'])),
        ]);
    }

    public function staffOptions(): JsonResponse
    {
        $staff = User::query()
            ->whereIn('role', [
                UserRole::Admin->value,
                UserRole::Accountant->value,
                UserRole::Staff->value,
                UserRole::Technician->value,
            ])
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'role', 'can_record_payment'])
            ->map(fn (User $user): array => [
                'id' => (string) $user->id,
                'name' => $user->name,
                'role' => $user->roleEnum()->value,
                'can_record_payment' => $user->canRecordPaymentPermission(),
            ])
            ->values();

        return response()->json(['staff' => $staff]);
    }

    private function nextStatus(string $currentStatus): string
    {
        return match ($this->normalizeStatus($currentStatus)) {
            'PENDING' => 'REPAIR',
            'REPAIR' => 'FINISHED',
            'FINISHED' => 'CHECKED_OUT',
            'CHECKED_OUT' => 'CHECKED_OUT',
            default => 'PENDING',
        };
    }

    private function normalizeStatus(string $status): string
    {
        return match (strtoupper($status)) {
            'OUT' => 'CHECKED_OUT',
            default => strtoupper($status),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function transformJob(ServiceJob $job): array
    {
        return [
            'id' => (string) $job->id,
            'job_code' => $job->job_code,
            'customer_id' => $job->customer_id,
            'customer_name' => $job->customer_name,
            'customer_phone' => $job->customer_phone,
            'tv_model' => $job->tv_model,
            'category' => strtoupper((string) $job->category),
            'priority' => strtolower((string) $job->priority),
            'issue' => $job->issue,
            'technician_notes' => $job->technician_notes,
            'status' => $this->normalizeStatus((string) $job->status),
            'estimated_price_iqd' => (float) ($job->estimated_price_iqd ?: 0),
            'final_price_iqd' => (float) ($job->final_price_iqd ?: 0),
            'repair_cost_iqd' => (float) ($job->repair_cost_iqd ?: 0),
            'whatsapp_sent' => (bool) $job->whatsapp_sent,
            'whatsapp_created_sent' => (bool) $job->whatsapp_created_sent,
            'whatsapp_repair_started_sent' => (bool) $job->whatsapp_repair_started_sent,
            'whatsapp_finished_sent' => (bool) $job->whatsapp_finished_sent,
            'whatsapp_pickup_sent' => (bool) $job->whatsapp_pickup_sent,
            'payment_received_iqd' => (float) ($job->payment_received_iqd ?: $job->total_paid),
            'total_paid_iqd' => (float) $job->total_paid,
            'remaining_balance_iqd' => (float) $job->remaining_balance,
            'assigned_staff_uid' => $job->assigned_staff_uid ? (string) $job->assigned_staff_uid : null,
            'assigned_staff_name' => $job->assignedStaff?->name,
            'assigned_technician' => $job->assigned_technician,
            'created_by' => $job->created_by_user_id ? (string) $job->created_by_user_id : null,
            'created_by_name' => $job->createdBy?->name,
            'created_by_role' => $job->createdBy?->roleEnum()->value,
            'notes' => $job->notes,
            'resolution' => $job->resolution,
            'not_fixed_reason' => $job->not_fixed_reason,
            'repair_started_at' => $job->repair_started_at?->toIso8601String(),
            'received_at' => $job->received_at?->toIso8601String(),
            'finished_at' => $job->finished_at?->toIso8601String(),
            'out_at' => $job->out_at?->toIso8601String(),
            'created_at' => $job->created_at?->toIso8601String(),
            'updated_at' => $job->updated_at?->toIso8601String(),
            'images' => $job->jobImages
                ->map(fn (JobImage $image): array => [
                    'id' => (string) $image->id,
                    'path' => $image->image_path,
                    'url' => asset('storage/' . ltrim((string) $image->image_path, '/')),
                    'label' => $image->label,
                    'created_at' => $image->created_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
            'parts_used' => $job->stockMovements()
                ->with('inventoryItem:id,name,sku')
                ->where('type', 'out')
                ->latest()
                ->get()
                ->map(fn (StockMovement $movement): array => [
                    'id' => (string) $movement->id,
                    'inventory_item_id' => (string) $movement->inventory_item_id,
                    'part_name' => $movement->inventoryItem?->name,
                    'part_sku' => $movement->inventoryItem?->sku,
                    'quantity' => (int) $movement->quantity,
                    'reason' => $movement->reason,
                    'created_at' => $movement->created_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
        ];
    }
}
