<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\Payment;
use App\Models\ServiceJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! Schema::hasTable('service_jobs')) {
            return response()->json($this->emptyDashboardResponse());
        }

        $hasPaymentsTable = Schema::hasTable('payments');
        $hasInventoryTable = Schema::hasTable('inventory_items');
        $jobsHasStatus = Schema::hasColumn('service_jobs', 'status');
        $jobsHasCreatedAt = Schema::hasColumn('service_jobs', 'created_at');
        $jobsHasFinishedAt = Schema::hasColumn('service_jobs', 'finished_at');
        $jobsHasCategory = Schema::hasColumn('service_jobs', 'category');
        $jobsHasPriority = Schema::hasColumn('service_jobs', 'priority');
        $jobsHasAssignedStaffUid = Schema::hasColumn('service_jobs', 'assigned_staff_uid');
        $jobsHasEstimatedPrice = Schema::hasColumn('service_jobs', 'estimated_price_iqd');
        $jobsHasFinalPrice = Schema::hasColumn('service_jobs', 'final_price_iqd');
        $jobsHasPaymentReceived = Schema::hasColumn('service_jobs', 'payment_received_iqd');
        $paymentsHasCreatedAt = $hasPaymentsTable && Schema::hasColumn('payments', 'created_at');
        $paymentsHasAmount = $hasPaymentsTable && Schema::hasColumn('payments', 'amount_iqd');
        $paymentsHasServiceJobId = $hasPaymentsTable && Schema::hasColumn('payments', 'service_job_id');

        $dateFrom = $request->query('date_from')
            ? Carbon::parse($request->query('date_from'))->startOfDay()
            : Carbon::today();

        $dateTo = $request->query('date_to')
            ? Carbon::parse($request->query('date_to'))->endOfDay()
            : Carbon::now()->endOfDay();

        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();

        $allJobsQuery = ServiceJob::query();
        $totalJobs = (int) $allJobsQuery->count();
        $urgentCount = ($jobsHasStatus && $jobsHasPriority)
            ? (int) ServiceJob::query()
                ->whereIn('status', ['PENDING', 'REPAIR'])
                ->where(function ($query): void {
                    $query->where('priority', 'urgent')
                        ->orWhere('priority', 'URGENT');
                })
                ->count()
            : 0;
        $assignedCount = $jobsHasAssignedStaffUid
            ? (int) ServiceJob::query()->whereNotNull('assigned_staff_uid')->count()
            : 0;
        $unassignedCount = $jobsHasAssignedStaffUid
            ? (int) ServiceJob::query()->whereNull('assigned_staff_uid')->count()
            : 0;

        // Today's jobs count
        $todayJobsCount = $jobsHasCreatedAt
            ? (int) ServiceJob::query()
                ->whereDate('created_at', Carbon::today())
                ->count()
            : 0;

        // Open jobs count (PENDING or REPAIR)
        $openJobsCount = $jobsHasStatus
            ? (int) ServiceJob::query()
                ->whereIn('status', ['PENDING', 'REPAIR'])
                ->count()
            : 0;

        // Completed jobs count (FINISHED or OUT)
        $completedJobsCount = ($jobsHasStatus && $jobsHasFinishedAt)
            ? (int) ServiceJob::query()
                ->whereIn('status', ['FINISHED', 'OUT', 'CHECKED_OUT'])
                ->whereDate('finished_at', '>=', $dateFrom)
                ->whereDate('finished_at', '<=', $dateTo)
                ->count()
            : 0;

        // Today's revenue (payments recorded today)
        $todayRevenueIqd = ($paymentsHasCreatedAt && $paymentsHasAmount)
            ? (float) Payment::query()
                ->whereDate('created_at', Carbon::today())
                ->sum('amount_iqd')
            : 0;

        // Date range revenue
        $rangeRevenueIqd = ($paymentsHasCreatedAt && $paymentsHasAmount)
            ? (float) Payment::query()
                ->whereDate('created_at', '>=', $dateFrom)
                ->whereDate('created_at', '<=', $dateTo)
                ->sum('amount_iqd')
            : 0;

        // Monthly revenue
        $monthlyRevenueIqd = ($paymentsHasCreatedAt && $paymentsHasAmount)
            ? (float) Payment::query()
                ->whereDate('created_at', '>=', $monthStart)
                ->whereDate('created_at', '<=', $monthEnd)
                ->sum('amount_iqd')
            : 0;

        // Outstanding debt (unpaid balance on all open jobs)
        $outstandingDebtIqd = ($jobsHasStatus && $jobsHasEstimatedPrice && $jobsHasFinalPrice && $jobsHasPaymentReceived)
            ? (float) ServiceJob::query()
                ->whereIn('status', ['PENDING', 'REPAIR', 'FINISHED'])
                ->get()
                ->sum(function (ServiceJob $job): float {
                    $totalPrice = (float) ($job->final_price_iqd ?: $job->estimated_price_iqd ?: 0);
                    $paid = (float) ($job->payment_received_iqd ?: 0);

                    return max(0, $totalPrice - $paid);
                })
            : 0;

        // Low stock items (below minimum threshold, default 5 units)
        $stockColumn = null;
        if ($hasInventoryTable && Schema::hasColumn('inventory_items', 'current_stock')) {
            $stockColumn = 'current_stock';
        } elseif ($hasInventoryTable && Schema::hasColumn('inventory_items', 'quantity')) {
            $stockColumn = 'quantity';
        }

        $lowStockItems = $stockColumn
            ? InventoryItem::query()
                ->where($stockColumn, '<', 5)
                ->orderBy($stockColumn)
                ->take(5)
                ->get()
                ->map(fn (InventoryItem $item) => [
                    'id' => (string) $item->id,
                    'name' => $item->name,
                    'sku' => $item->sku,
                    'current_stock' => (int) ($item->{$stockColumn} ?? 0),
                    'low_stock_threshold' => 5,
                ])
            : collect();

        // Technician performance (jobs completed this month)
        $technicianPerformance = ($jobsHasStatus && $jobsHasFinishedAt && $jobsHasAssignedStaffUid)
            ? ServiceJob::query()
                ->with('assignedStaff:id,name')
                ->whereIn('status', ['FINISHED', 'OUT', 'CHECKED_OUT'])
                ->whereDate('finished_at', '>=', $monthStart)
                ->whereDate('finished_at', '<=', $monthEnd)
                ->get()
                ->groupBy('assigned_staff_uid')
                ->map(function ($jobs) use ($paymentsHasAmount, $paymentsHasServiceJobId) {
                    $staffMember = $jobs->first()?->assignedStaff;

                    return [
                        'technician_uid' => $jobs->first()?->assigned_staff_uid,
                        'technician_name' => $staffMember?->name ?? 'Unassigned',
                        'jobs_completed' => $jobs->count(),
                        'revenue_generated_iqd' => ($paymentsHasAmount && $paymentsHasServiceJobId)
                            ? (float) $jobs->sum(fn (ServiceJob $job) => Payment::where('service_job_id', $job->id)->sum('amount_iqd'))
                            : 0,
                    ];
                })
                ->values()
                ->sortByDesc('jobs_completed')
                ->take(5)
            : collect();

        // Job status breakdown
        $statusBreakdown = $jobsHasStatus
            ? ServiceJob::query()
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->get()
                ->map(fn ($row) => [
                    'status' => strtoupper((string) $row->status),
                    'count' => (int) $row->count,
                ])
                ->keyBy('status')
                ->all()
            : [];

        // Category popularity (most common repair types this month)
        $categoryPopularity = ($jobsHasCategory && $jobsHasCreatedAt)
            ? ServiceJob::query()
                ->selectRaw('category, COUNT(*) as count')
                ->whereDate('created_at', '>=', $monthStart)
                ->whereDate('created_at', '<=', $monthEnd)
                ->groupBy('category')
                ->orderByRaw('COUNT(*) DESC')
                ->take(5)
                ->get()
                ->map(fn ($row) => [
                    'category' => strtoupper((string) $row->category),
                    'count' => (int) $row->count,
                ])
            : collect();

        // Average job value (estimated vs final price)
        $avgJobMetrics = ($jobsHasStatus && $jobsHasFinishedAt)
            ? ServiceJob::query()
                ->whereIn('status', ['FINISHED', 'OUT', 'CHECKED_OUT'])
                ->whereDate('finished_at', '>=', $monthStart)
                ->whereDate('finished_at', '<=', $monthEnd)
                ->get()
            : collect();

        $avgEstimatedPrice = ($avgJobMetrics->count() > 0 && $jobsHasEstimatedPrice)
            ? (float) $avgJobMetrics->average('estimated_price_iqd')
            : 0;

        $avgFinalPrice = ($avgJobMetrics->count() > 0 && $jobsHasFinalPrice)
            ? (float) $avgJobMetrics->average('final_price_iqd')
            : 0;

        return response()->json([
            'total_jobs' => $totalJobs,
            'urgent_count' => $urgentCount,
            'assigned_count' => $assignedCount,
            'unassigned_count' => $unassignedCount,
            'summary' => [
                'today_jobs_count' => $todayJobsCount,
                'open_jobs_count' => $openJobsCount,
                'completed_jobs_count' => $completedJobsCount,
                'today_revenue_iqd' => (float) $todayRevenueIqd,
                'range_revenue_iqd' => (float) $rangeRevenueIqd,
                'monthly_revenue_iqd' => (float) $monthlyRevenueIqd,
                'outstanding_debt_iqd' => (float) $outstandingDebtIqd,
            ],
            'low_stock_items' => $lowStockItems,
            'technician_performance' => array_values($technicianPerformance->toArray()),
            'status_breakdown' => $statusBreakdown,
            'category_popularity' => $categoryPopularity,
            'average_job_metrics' => [
                'estimated_price_iqd' => $avgEstimatedPrice,
                'final_price_iqd' => $avgFinalPrice,
                'jobs_analyzed_count' => $avgJobMetrics->count(),
            ],
            'date_range' => [
                'from' => $dateFrom->toIso8601String(),
                'to' => $dateTo->toIso8601String(),
                'month_start' => $monthStart->toIso8601String(),
                'month_end' => $monthEnd->toIso8601String(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyDashboardResponse(): array
    {
        return [
            'total_jobs' => 0,
            'urgent_count' => 0,
            'assigned_count' => 0,
            'unassigned_count' => 0,
            'summary' => [
                'today_jobs_count' => 0,
                'open_jobs_count' => 0,
                'completed_jobs_count' => 0,
                'today_revenue_iqd' => 0,
                'range_revenue_iqd' => 0,
                'monthly_revenue_iqd' => 0,
                'outstanding_debt_iqd' => 0,
            ],
            'low_stock_items' => [],
            'technician_performance' => [],
            'status_breakdown' => [],
            'category_popularity' => [],
            'average_job_metrics' => [
                'estimated_price_iqd' => 0,
                'final_price_iqd' => 0,
                'jobs_analyzed_count' => 0,
            ],
            'date_range' => [
                'from' => Carbon::today()->toIso8601String(),
                'to' => Carbon::now()->endOfDay()->toIso8601String(),
                'month_start' => Carbon::now()->startOfMonth()->toIso8601String(),
                'month_end' => Carbon::now()->endOfMonth()->toIso8601String(),
            ],
        ];
    }
}
