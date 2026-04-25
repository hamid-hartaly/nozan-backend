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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class JobController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        abort_unless($user instanceof User && $user->roleEnum()->canAccessJobs(), 403);

        $query = ServiceJob::query()
            ->with(['assignedStaff:id,name', 'createdBy:id,name,role', 'jobImages', 'returnedFromJob:id,job_code']);

        $filters = $request->validate([
            'promise' => ['nullable', Rule::in(['today', 'tomorrow', 'overdue', 'all', 'none'])],
            'promised_day' => ['nullable', 'date'],
            'overdue_promises' => ['nullable', 'boolean'],
            'promise_sort' => ['nullable', Rule::in(['none', 'closest_first', 'overdue_first'])],
        ]);

        $promisePreset = strtolower((string) ($filters['promise'] ?? ''));
        $explicitPromisedDay = ! empty($filters['promised_day']);
        $explicitOverdueFlag = $request->query('overdue_promises') !== null;

        $promisedDayFilter = $explicitPromisedDay
            ? Carbon::parse((string) $filters['promised_day'])->toDateString()
            : null;

        $overdueOnlyFilter = $explicitOverdueFlag
            ? $request->boolean('overdue_promises')
            : false;

        if (! $explicitPromisedDay && ! $explicitOverdueFlag) {
            if ($promisePreset === 'today') {
                $promisedDayFilter = Carbon::today()->toDateString();
            } elseif ($promisePreset === 'tomorrow') {
                $promisedDayFilter = Carbon::tomorrow()->toDateString();
            } elseif ($promisePreset === 'overdue') {
                $overdueOnlyFilter = true;
            }
        }

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

        if ($promisedDayFilter !== null) {
            $query->whereDate('promised_completion_at', $promisedDayFilter);
        }

        if ($overdueOnlyFilter) {
            $query
                ->whereNotNull('promised_completion_at')
                ->where('promised_completion_at', '<', Carbon::now())
                ->whereIn('status', ['PENDING', 'REPAIR']);
        }

        $promiseSort = $filters['promise_sort'] ?? 'none';

        if ($promiseSort === 'closest_first') {
            $query
                ->orderByRaw('CASE WHEN promised_completion_at IS NULL THEN 1 ELSE 0 END ASC')
                ->orderBy('promised_completion_at')
                ->latest();
        } elseif ($promiseSort === 'overdue_first') {
            $query
                ->orderByRaw(
                    "CASE WHEN promised_completion_at IS NOT NULL AND promised_completion_at < ? AND status IN ('PENDING','REPAIR') THEN 0 ELSE 1 END ASC",
                    [Carbon::now()->toDateTimeString()]
                )
                ->orderByRaw('CASE WHEN promised_completion_at IS NULL THEN 1 ELSE 0 END ASC')
                ->orderBy('promised_completion_at')
                ->latest();
        } else {
            $query->latest();
        }

        $jobs = $query->paginate($request->integer('per_page', 20));
        $transformed = $jobs->through(fn (ServiceJob $job): array => $this->transformJob($job));

        return response()->json([
            'jobs' => $transformed->items(),
            'data' => $transformed->items(),
            'current_page' => $transformed->currentPage(),
            'last_page' => $transformed->lastPage(),
            'per_page' => $transformed->perPage(),
            'total' => $transformed->total(),
        ]);
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

    public function publicTracking(Request $request, ServiceJob $job): JsonResponse
    {
        $token = trim((string) $request->query('token', ''));

        if (! $job->hasValidTrackingToken($token)) {
            return response()->json([
                'message' => 'Invalid tracking token.',
            ], 403);
        }

        return response()->json([
            'job' => [
                'job_code' => (string) $job->job_code,
                'customer_name' => (string) ($job->customer_name ?? ''),
                'tv_model' => (string) ($job->tv_model ?? ''),
                'category' => strtoupper((string) ($job->category ?? 'OTHER')),
                'status' => $this->normalizeStatus((string) $job->status),
                'created_at' => $job->created_at?->toIso8601String(),
                'repair_started_at' => $job->repair_started_at?->toIso8601String(),
                'finished_at' => $job->finished_at?->toIso8601String(),
                'out_at' => $job->out_at?->toIso8601String(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user instanceof User && $user->roleEnum()->canCreateJob(), 403);

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
            'is_in_warranty' => ['sometimes', 'boolean'],
            'warranty_company' => ['nullable', 'string', 'max:255', 'required_if:is_in_warranty,true'],
            'notes' => ['nullable', 'string'],
            'assigned_staff_uid' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'promised_completion_at' => ['nullable', 'date'],
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
            'is_in_warranty' => (bool) ($payload['is_in_warranty'] ?? false),
            'warranty_company' => ! empty($payload['is_in_warranty']) ? ($payload['warranty_company'] ?? null) : null,
            'estimated_price_iqd' => $payload['estimated_price_iqd'] ?? $payload['estimated_price'] ?? 0,
            'estimated_price' => $payload['estimated_price_iqd'] ?? $payload['estimated_price'] ?? 0,
            'status' => 'PENDING',
            // Keep legacy field for older clients; value becomes true only after a successful send.
            'whatsapp_sent' => false,
            'assigned_staff_uid' => $assignedStaff?->id,
            'notes' => $payload['notes'] ?? null,
            'created_by_user_id' => $user->id,
            'promised_completion_at' => isset($payload['promised_completion_at'])
                ? Carbon::parse((string) $payload['promised_completion_at'])
                : null,
        ]);

        // Send WhatsApp notification for new job
        $whatsappService = new WhatsAppService;
        if ($whatsappService->sendJobCreatedMessage($job)) {
            $job->update([
                'whatsapp_sent' => true,
                'whatsapp_created_sent' => true,
            ]);
        }

        return response()->json([
            'message' => 'Job created successfully.',
            'job' => $this->transformJob($job->fresh(['assignedStaff:id,name', 'createdBy:id,name,role'])),
        ], 201);
    }

    public function show(ServiceJob $job): JsonResponse
    {
        $job->loadMissing(['assignedStaff:id,name', 'createdBy:id,name,role', 'jobImages', 'returnedFromJob:id,job_code']);

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
            'is_in_warranty' => ['sometimes', 'boolean'],
            'warranty_company' => ['nullable', 'string', 'max:255', 'required_if:is_in_warranty,true'],
            'promised_completion_at' => ['sometimes', 'nullable', 'date'],
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

        if (array_key_exists('is_in_warranty', $payload)) {
            $job->is_in_warranty = (bool) $payload['is_in_warranty'];

            if (! $job->is_in_warranty) {
                $job->warranty_company = null;
            }
        }

        if (array_key_exists('warranty_company', $payload)) {
            $job->warranty_company = ($payload['is_in_warranty'] ?? $job->is_in_warranty)
                ? ($payload['warranty_company'] ?? null)
                : null;
        }

        if (array_key_exists('promised_completion_at', $payload)) {
            $job->promised_completion_at = $payload['promised_completion_at']
                ? Carbon::parse((string) $payload['promised_completion_at'])
                : null;
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
        /** @var User|null $user */
        $user = $request->user();
        abort_unless($user instanceof User && $user->roleEnum()->canOperateJobs(), 403);

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
            if (Schema::hasColumn($job->getTable(), 'assigned_technician')) {
                $job->assigned_technician = $assignedStaff?->name;
            }
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
        $whatsappService = new WhatsAppService;
        if ($job->status === 'REPAIR' && ! $job->whatsapp_repair_started_sent && $whatsappService->sendRepairStartedMessage($job)) {
            $job->update([
                'whatsapp_sent' => true,
                'whatsapp_repair_started_sent' => true,
            ]);
        }

        if ($job->status === 'FINISHED' && ! $job->whatsapp_finished_sent && $whatsappService->sendJobFinishedMessage($job)) {
            $job->update([
                'whatsapp_sent' => true,
                'whatsapp_finished_sent' => true,
            ]);
        }

        if (in_array($job->status, ['OUT', 'CHECKED_OUT'], true) && ! $job->whatsapp_pickup_sent && $whatsappService->sendReadyForPickupMessage($job)) {
            $job->update([
                'whatsapp_sent' => true,
                'whatsapp_pickup_sent' => true,
            ]);
        }

        return response()->json([
            'message' => 'Job updated successfully.',
            'job' => $this->transformJob($job->fresh(['assignedStaff:id,name', 'createdBy:id,name,role'])),
        ]);
    }

    public function assign(Request $request, ServiceJob $job): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        abort_unless($user instanceof User && $user->roleEnum()->canOperateJobs(), 403);

        $payload = $request->validate([
            'assigned_staff_uid' => ['nullable', 'integer', Rule::exists('users', 'id')],
        ]);

        $assignedStaff = ! empty($payload['assigned_staff_uid'])
            ? User::find($payload['assigned_staff_uid'])
            : null;

        $job->assigned_staff_uid = $assignedStaff?->id;
        if (Schema::hasColumn($job->getTable(), 'assigned_technician')) {
            $job->assigned_technician = $assignedStaff?->name;
        }
        $job->save();

        return response()->json([
            'message' => 'Technician assignment updated successfully.',
            'job' => $this->transformJob($job->fresh(['assignedStaff:id,name', 'createdBy:id,name,role'])),
        ]);
    }

    public function updateNotes(Request $request, ServiceJob $job): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        abort_unless($user instanceof User && $user->roleEnum()->canOperateJobs(), 403);

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
        /** @var User|null $user */
        $user = request()->user();
        abort_unless($user instanceof User && $user->roleEnum()->canOperateJobs(), 403);

        $job->whatsapp_sent = true;
        $job->save();

        return response()->json([
            'message' => 'WhatsApp status updated successfully.',
            'job' => $this->transformJob($job->fresh(['assignedStaff:id,name'])),
        ]);
    }

    public function createReturnJob(Request $request, ServiceJob $originalJob): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user instanceof User && $user->roleEnum()->canCreateJob(), 403);

        $payload = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        // Warranty mapping by category
        $warrantyMap = [
            'PANEL' => 2,
            'Screen broken' => 2,
            'LED' => 6,
            'M.B' => 2,
        ];

        $warrantyMonths = $warrantyMap[$originalJob->category] ?? 0;

        $returnJob = ServiceJob::create([
            'customer_id' => $originalJob->customer_id,
            'customer_record_id' => $originalJob->customer_record_id,
            'customer_name' => $originalJob->customer_name,
            'customer_phone' => $originalJob->customer_phone,
            'tv_model' => $originalJob->tv_model,
            'device_model' => $originalJob->device_model,
            'device_type' => $originalJob->device_type,
            'category' => $originalJob->category,
            'priority' => $originalJob->priority ?? 'normal',
            'issue' => "Return from: {$originalJob->job_code}",
            'problem' => "Return from: {$originalJob->job_code}",
            'status' => 'PENDING',
            'is_in_warranty' => true,
            'warranty_company' => null,
            'warranty_months' => $warrantyMonths,
            'returned_from_job_id' => $originalJob->id,
            'created_by_user_id' => $user->id,
            'notes' => $payload['notes'] ?? null,
            'received_at' => now(),
        ]);

        $whatsappService = new WhatsAppService;
        if ($whatsappService->sendJobCreatedMessage($returnJob)) {
            $returnJob->update([
                'whatsapp_sent' => true,
                'whatsapp_created_sent' => true,
            ]);
        }

        return response()->json([
            'message' => 'Return job created successfully.',
            'job' => $this->transformJob($returnJob->fresh(['assignedStaff:id,name', 'createdBy:id,name,role', 'jobImages', 'returnedFromJob:id,job_code'])),
        ], 201);
    }

    public function destroy(Request $request, ServiceJob $job): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        abort_unless($user instanceof User && in_array($user->roleEnum(), [UserRole::Admin, UserRole::Accountant], true), 403);

        DB::transaction(function () use ($job): void {
            foreach ($job->jobImages()->get() as $image) {
                if ($image->image_path) {
                    try {
                        if (Storage::disk('public')->exists($image->image_path)) {
                            Storage::disk('public')->delete($image->image_path);
                        }
                    } catch (\Throwable) {
                        // Storage errors should not block job deletion
                    }
                }
            }

            if (Schema::hasColumn('service_jobs', 'returned_from_job_id')) {
                $job->returnedJobs()->update(['returned_from_job_id' => null]);
            }

            $job->jobImages()->delete();
            $job->delete();
        });

        return response()->json([
            'message' => 'Job deleted successfully.',
        ]);
    }

    public function staffOptions(): JsonResponse
    {
        $staff = User::query()
            ->whereIn('role', [
                UserRole::Admin->value,
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
            'is_in_warranty' => (bool) $job->is_in_warranty,
            'warranty_company' => $job->warranty_company,
            'warranty_months' => (int) $job->warranty_months,
            'returned_from_job_id' => $job->returned_from_job_id ? (string) $job->returned_from_job_id : null,
            'returned_from_job_code' => $job->returnedFromJob?->job_code,
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
            'promised_completion_at' => $job->promised_completion_at?->toIso8601String(),
            'finished_at' => $job->finished_at?->toIso8601String(),
            'out_at' => $job->out_at?->toIso8601String(),
            'created_at' => $job->created_at?->toIso8601String(),
            'updated_at' => $job->updated_at?->toIso8601String(),
            'images' => $job->jobImages
                ->map(fn (JobImage $image): array => [
                    'id' => (string) $image->id,
                    'path' => $image->image_path,
                    'url' => asset('storage/'.ltrim((string) $image->image_path, '/')),
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
