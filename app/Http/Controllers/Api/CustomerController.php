<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\ServiceJob;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->ensureInternalUser($request);

        $query = Customer::query()->latest();

        if ($search = trim($request->string('search')->toString())) {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $customers = $query->limit(30)->get();

        return response()->json([
            'customers' => $customers->map(fn (Customer $customer): array => $this->transformCustomer($customer))->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureInternalUser($request);

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $customer = Customer::query()->updateOrCreate(
            ['phone' => $payload['phone']],
            [
                'name' => $payload['name'],
                'email' => $payload['email'] ?? null,
                'address' => $payload['address'] ?? null,
                'notes' => $payload['notes'] ?? null,
            ],
        );

        return response()->json([
            'message' => 'Customer saved successfully.',
            'customer' => $this->transformCustomer($customer),
        ], 201);
    }

    public function show(Request $request, Customer $customer): JsonResponse
    {
        $this->ensureInternalUser($request);

        return response()->json([
            'customer' => $this->transformCustomer($customer, includeJobs: true),
        ]);
    }

    private function ensureInternalUser(Request $request): void
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->roleEnum()->canAccessJobs(), 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function transformCustomer(Customer $customer, bool $includeJobs = false): array
    {
        $jobs = ServiceJob::query()
            ->where('customer_record_id', $customer->id)
            ->withSum('payments', 'amount_iqd')
            ->latest()
            ->get();

        $jobRows = $jobs->map(function (ServiceJob $job): array {
            $targetAmount = (float) ($job->final_price_iqd ?: $job->estimated_price_iqd ?: 0);
            $paidAmount = (float) ($job->payments_sum_amount_iqd ?? $job->payment_received_iqd ?: 0);

            return [
                'id' => (string) $job->id,
                'job_code' => $job->job_code,
                'tv_model' => $job->tv_model,
                'status' => strtoupper((string) $job->status),
                'target_iqd' => $targetAmount,
                'paid_iqd' => $paidAmount,
                'remaining_iqd' => max($targetAmount - $paidAmount, 0),
                'created_at' => $job->created_at?->toIso8601String(),
                'finished_at' => $job->finished_at?->toIso8601String(),
            ];
        })->all();

        $totalPaid = (float) $jobs->sum(fn (ServiceJob $job): float => (float) ($job->payments_sum_amount_iqd ?? $job->payment_received_iqd ?: 0));
        $outstandingBalance = (float) $jobs->sum(function (ServiceJob $job): float {
            $target = (float) ($job->final_price_iqd ?: $job->estimated_price_iqd ?: 0);
            $paid = (float) ($job->payments_sum_amount_iqd ?? $job->payment_received_iqd ?: 0);

            return max($target - $paid, 0);
        });

        return [
            'id' => (string) $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'address' => $customer->address,
            'notes' => $customer->notes,
            'job_count' => $jobs->count(),
            'total_paid_iqd' => $totalPaid,
            'outstanding_balance_iqd' => $outstandingBalance,
            'last_job_at' => $jobs->first()?->created_at?->toIso8601String(),
            'jobs' => $includeJobs ? $jobRows : [],
        ];
    }
}
