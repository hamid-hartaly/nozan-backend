<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ServiceJob;
use App\Models\Payment;
use App\Models\InventoryItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $dateFrom = $request->query('date_from')
            ? Carbon::parse($request->query('date_from'))->startOfDay()
            : Carbon::today();

        $dateTo = $request->query('date_to')
            ? Carbon::parse($request->query('date_to'))->endOfDay()
            : Carbon::now()->endOfDay();

        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();

        // Today's jobs count
        $todayJobsCount = ServiceJob::query()
            ->whereDate('created_at', Carbon::today())
            ->count();

        // Open jobs count (PENDING or REPAIR)
        $openJobsCount = ServiceJob::query()
            ->whereIn('status', ['PENDING', 'REPAIR'])
            ->count();

        // Completed jobs count (FINISHED or OUT)
        $completedJobsCount = ServiceJob::query()
            ->whereIn('status', ['FINISHED', 'OUT', 'CHECKED_OUT'])
            ->whereDate('finished_at', '>=', $dateFrom)
            ->whereDate('finished_at', '<=', $dateTo)
            ->count();

        // Today's revenue (payments recorded today)
        $todayRevenueIqd = Payment::query()
            ->whereDate('created_at', Carbon::today())
            ->sum('amount_iqd');

        // Date range revenue
        $rangeRevenueIqd = Payment::query()
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->sum('amount_iqd');

        // Monthly revenue
        $monthlyRevenueIqd = Payment::query()
            ->whereDate('created_at', '>=', $monthStart)
            ->whereDate('created_at', '<=', $monthEnd)
            ->sum('amount_iqd');

        // Outstanding debt (unpaid balance on all open jobs)
        $outstandingDebtIqd = ServiceJob::query()
            ->whereIn('status', ['PENDING', 'REPAIR', 'FINISHED'])
            ->get()
            ->sum(function (ServiceJob $job): float {
                $totalPrice = (float) ($job->final_price_iqd ?: $job->estimated_price_iqd ?: 0);
                $paid = (float) ($job->payment_received_iqd ?: 0);
                return max(0, $totalPrice - $paid);
            });

        // Low stock items (below minimum threshold, default 5 units)
        $lowStockItems = InventoryItem::query()
            ->where('current_stock', '<', 5)
            ->orderBy('current_stock')
            ->take(5)
            ->get()
            ->map(fn (InventoryItem $item) => [
                'id' => (string) $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'current_stock' => (int) $item->current_stock,
                'low_stock_threshold' => 5,
            ]);

        // Technician performance (jobs completed this month)
        $technicianPerformance = ServiceJob::query()
            ->with('assignedStaff:id,name')
            ->whereIn('status', ['FINISHED', 'OUT', 'CHECKED_OUT'])
            ->whereDate('finished_at', '>=', $monthStart)
            ->whereDate('finished_at', '<=', $monthEnd)
            ->get()
            ->groupBy('assigned_staff_uid')
            ->map(function ($jobs) {
                $staffMember = $jobs->first()?->assignedStaff;
                return [
                    'technician_uid' => $jobs->first()?->assigned_staff_uid,
                    'technician_name' => $staffMember?->name ?? 'Unassigned',
                    'jobs_completed' => $jobs->count(),
                    'revenue_generated_iqd' => (float) $jobs->sum(fn (ServiceJob $job) =>
                        Payment::where('service_job_id', $job->id)->sum('amount_iqd')
                    ),
                ];
            })
            ->values()
            ->sortByDesc('jobs_completed')
            ->take(5);

        // Job status breakdown
        $statusBreakdown = ServiceJob::query()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get()
            ->map(fn ($row) => [
                'status' => strtoupper((string) $row->status),
                'count' => (int) $row->count,
            ])
            ->keyBy('status')
            ->all();

        // Category popularity (most common repair types this month)
        $categoryPopularity = ServiceJob::query()
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
            ]);

        // Average job value (estimated vs final price)
        $avgJobMetrics = ServiceJob::query()
            ->whereIn('status', ['FINISHED', 'OUT', 'CHECKED_OUT'])
            ->whereDate('finished_at', '>=', $monthStart)
            ->whereDate('finished_at', '<=', $monthEnd)
            ->get();

        $avgEstimatedPrice = $avgJobMetrics->count() > 0
            ? (float) $avgJobMetrics->average('estimated_price_iqd')
            : 0;

        $avgFinalPrice = $avgJobMetrics->count() > 0
            ? (float) $avgJobMetrics->average('final_price_iqd')
            : 0;

        return response()->json([
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
}
