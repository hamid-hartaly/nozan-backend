<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\MonthlyFinanceSummary;
use App\Models\Payment;
use App\Models\ServiceJob;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FinanceController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $this->ensureFinanceAccess($request);

        $today = Carbon::now();
        $todayStart = $today->copy()->startOfDay();
        $todayEnd = $today->copy()->endOfDay();
        $monthStart = $today->copy()->startOfMonth();
        $monthEnd = $today->copy()->endOfMonth();
        $lastMonthStart = $today->copy()->subMonthNoOverflow()->startOfMonth();

        $revenueToday = (int) round((float) Payment::query()
            ->whereBetween('recorded_at', [$todayStart, $todayEnd])
            ->sum('amount_iqd'));

        $revenueThisMonth = (int) round((float) Payment::query()
            ->whereBetween('recorded_at', [$monthStart, $monthEnd])
            ->sum('amount_iqd'));

        $expensesThisMonth = (int) round((float) Expense::query()
            ->whereBetween('expense_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->sum('amount_iqd'));

        $openDebt = (int) ServiceJob::query()->get()->sum(function (ServiceJob $job): int {
            return $this->openDebtForJob($job);
        });

        $pendingWithBalance = (int) ServiceJob::query()
            ->whereNotIn('status', ['OUT', 'CHECKED_OUT'])
            ->get()
            ->filter(fn (ServiceJob $job): bool => $this->openDebtForJob($job) > 0)
            ->count();

        $todayPaymentsCount = Payment::query()
            ->whereBetween('recorded_at', [$todayStart, $todayEnd])
            ->count();

        $todayExpensesCount = Expense::query()
            ->whereDate('expense_date', $today->toDateString())
            ->count();

        $lastMonthSummary = MonthlyFinanceSummary::query()
            ->where('year', (int) $lastMonthStart->year)
            ->where('month', (int) $lastMonthStart->month)
            ->first();

        if (! $lastMonthSummary) {
            $computed = $this->computeMonthlySummary($lastMonthStart);
            $lastMonthSummary = MonthlyFinanceSummary::create([
                ...$computed,
                'generated_at' => Carbon::now(),
            ]);
        }

        return response()->json([
            'dashboard' => [
                'total_revenue_today_iqd' => $revenueToday,
                'total_revenue_this_month_iqd' => $revenueThisMonth,
                'open_debt_iqd' => $openDebt,
                'expenses_this_month_iqd' => $expensesThisMonth,
                'net_profit_this_month_iqd' => $revenueThisMonth - $expensesThisMonth,
                'pending_jobs_with_balance' => $pendingWithBalance,
                'daily_close_status' => [
                    'is_closed' => $todayPaymentsCount === 0 && $todayExpensesCount === 0,
                    'payments_count' => $todayPaymentsCount,
                    'expenses_count' => $todayExpensesCount,
                    'date' => $today->toDateString(),
                ],
                'last_month_summary' => [
                    'year' => (int) $lastMonthSummary->year,
                    'month' => (int) $lastMonthSummary->month,
                    'total_revenue_iqd' => (int) round((float) $lastMonthSummary->total_revenue_iqd),
                    'total_expenses_iqd' => (int) round((float) $lastMonthSummary->total_expenses_iqd),
                    'total_net_iqd' => (int) round((float) $lastMonthSummary->total_net_iqd),
                    'total_jobs' => (int) $lastMonthSummary->total_jobs,
                    'total_finished_jobs' => (int) $lastMonthSummary->total_finished_jobs,
                    'total_open_debt_iqd' => (int) round((float) $lastMonthSummary->total_open_debt_iqd),
                    'is_closed' => (bool) $lastMonthSummary->is_closed,
                    'generated_at' => $lastMonthSummary->generated_at?->toIso8601String(),
                ],
            ],
        ]);
    }

    public function invoices(Request $request): JsonResponse
    {
        $this->ensureFinanceAccess($request);

        $invoices = Invoice::query()
            ->with(['serviceJobs:id,job_code,customer_name,tv_model,status', 'lineItems:id,invoice_id,item_type,description,quantity,unit_price_iqd,line_total_iqd'])
            ->latest('issued_at')
            ->get();

        if ($invoices->isEmpty()) {
            $jobs = ServiceJob::query()
                ->with('assignedStaff:id,name')
                ->latest('updated_at')
                ->get();

            $legacyInvoices = $jobs->map(fn (ServiceJob $job) => $this->transformInvoice($job));

            return response()->json([
                'invoices' => $legacyInvoices->all(),
                'summary' => [
                    'total_invoiced_iqd' => (int) $legacyInvoices->sum('amount_iqd'),
                    'total_paid_iqd' => (int) $legacyInvoices->sum('paid_iqd'),
                    'outstanding_iqd' => (int) $legacyInvoices->sum('outstanding_iqd'),
                    'unpaid_count' => (int) $legacyInvoices->filter(fn (array $invoice) => $invoice['outstanding_iqd'] > 0)->count(),
                ],
            ]);
        }

        $rows = $invoices->map(function (Invoice $invoice): array {
            $jobCodes = $invoice->serviceJobs->pluck('job_code')->filter()->values();
            $customers = $invoice->serviceJobs->pluck('customer_name')->filter()->unique()->values();
            $models = $invoice->serviceJobs->pluck('tv_model')->filter()->unique()->values();
            $staffNames = $invoice->serviceJobs
                ->map(fn (ServiceJob $job): ?string => $job->assignedStaff?->name)
                ->filter()
                ->unique()
                ->values();

            return [
                'id' => (string) $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'job_code' => $jobCodes->join(', '),
                'job_codes' => $jobCodes->all(),
                'customer_name' => $customers->join(', '),
                'tv_model' => $models->join(' | '),
                'assigned_staff_name' => $staffNames->join(', '),
                'job_status' => 'FINISHED',
                'amount_iqd' => (int) round((float) $invoice->total_iqd),
                'paid_iqd' => (int) round((float) $invoice->paid_iqd),
                'outstanding_iqd' => (int) round((float) $invoice->outstanding_iqd),
                'status' => $invoice->status,
                'issued_at' => $invoice->issued_at?->toIso8601String(),
                'line_items' => $invoice->lineItems->map(fn (InvoiceLineItem $item): array => [
                    'id' => (string) $item->id,
                    'item_type' => $item->item_type,
                    'description' => $item->description,
                    'quantity' => (float) $item->quantity,
                    'unit_price_iqd' => (int) round((float) $item->unit_price_iqd),
                    'line_total_iqd' => (int) round((float) $item->line_total_iqd),
                ])->values()->all(),
            ];
        });

        return response()->json([
            'invoices' => $rows->all(),
            'summary' => [
                'total_invoiced_iqd' => (int) $rows->sum('amount_iqd'),
                'total_paid_iqd' => (int) $rows->sum('paid_iqd'),
                'outstanding_iqd' => (int) $rows->sum('outstanding_iqd'),
                'unpaid_count' => (int) $rows->filter(fn (array $invoice) => $invoice['outstanding_iqd'] > 0)->count(),
            ],
        ]);
    }

    public function invoiceCandidates(Request $request): JsonResponse
    {
        $this->ensureFinanceAccess($request);

        $jobs = ServiceJob::query()
            ->with('assignedStaff:id,name')
            ->whereIn('status', ['FINISHED', 'CHECKED_OUT', 'OUT'])
            ->latest('updated_at')
            ->limit(120)
            ->get();

        return response()->json([
            'jobs' => $jobs->map(function (ServiceJob $job): array {
                $target = $this->amountForJob($job);
                $paid = (int) round((float) ($job->payment_received_iqd ?? 0));

                return [
                    'id' => (string) $job->id,
                    'job_code' => $job->job_code,
                    'customer_name' => $job->customer_name,
                    'tv_model' => $job->tv_model,
                    'assigned_staff_name' => $job->assignedStaff?->name,
                    'target_iqd' => $target,
                    'paid_iqd' => $paid,
                    'remaining_iqd' => max($target - $paid, 0),
                ];
            })->values()->all(),
        ]);
    }

    public function storeInvoice(Request $request): JsonResponse
    {
        $this->ensureFinanceAccess($request);

        $payload = $request->validate([
            'service_job_ids' => ['required', 'array', 'min:1'],
            'service_job_ids.*' => ['integer', 'exists:service_jobs,id'],
            'extra_items' => ['nullable', 'array'],
            'extra_items.*.description' => ['required_with:extra_items', 'string', 'max:255'],
            'extra_items.*.quantity' => ['nullable', 'numeric', 'min:0.01'],
            'extra_items.*.unit_price_iqd' => ['nullable', 'numeric', 'min:0'],
            'discount_iqd' => ['nullable', 'numeric', 'min:0'],
            'tax_iqd' => ['nullable', 'numeric', 'min:0'],
        ]);

        $invoice = DB::transaction(function () use ($payload, $request): Invoice {
            /** @var EloquentCollection<int, ServiceJob> $jobs */
            $jobs = ServiceJob::query()
                ->whereIn('id', $payload['service_job_ids'])
                ->get();

            $jobSubtotal = (float) $jobs->sum(fn (ServiceJob $job): int => $this->amountForJob($job));

            $extraItems = collect($payload['extra_items'] ?? [])->map(function (array $item): array {
                $qty = (float) ($item['quantity'] ?? 1);
                $unit = (float) ($item['unit_price_iqd'] ?? 0);

                return [
                    'description' => $item['description'],
                    'quantity' => $qty,
                    'unit_price_iqd' => $unit,
                    'line_total_iqd' => $qty * $unit,
                ];
            });

            $extrasSubtotal = (float) $extraItems->sum('line_total_iqd');
            $subtotal = $jobSubtotal + $extrasSubtotal;
            $discount = (float) ($payload['discount_iqd'] ?? 0);
            $tax = (float) ($payload['tax_iqd'] ?? 0);
            $total = max($subtotal - $discount + $tax, 0);

            $invoice = Invoice::create([
                'customer_name' => $jobs->pluck('customer_name')->filter()->unique()->join(', '),
                'subtotal_iqd' => $subtotal,
                'discount_iqd' => $discount,
                'tax_iqd' => $tax,
                'total_iqd' => $total,
                'paid_iqd' => 0,
                'outstanding_iqd' => $total,
                'status' => 'UNPAID',
                'recorded_by' => $request->user()?->name,
                'issued_at' => Carbon::now(),
            ]);

            $invoice->serviceJobs()->sync($jobs->pluck('id')->all());

            foreach ($jobs as $job) {
                $jobAmount = (float) $this->amountForJob($job);

                if ($jobAmount <= 0) {
                    continue;
                }

                $invoice->lineItems()->create([
                    'item_type' => 'job',
                    'description' => sprintf('%s - %s', $job->job_code, $job->tv_model),
                    'quantity' => 1,
                    'unit_price_iqd' => $jobAmount,
                    'line_total_iqd' => $jobAmount,
                ]);
            }

            foreach ($extraItems as $item) {
                $invoice->lineItems()->create([
                    'item_type' => 'extra',
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price_iqd' => $item['unit_price_iqd'],
                    'line_total_iqd' => $item['line_total_iqd'],
                ]);
            }

            return $invoice->fresh(['serviceJobs:id,job_code', 'lineItems:id,invoice_id,item_type,description,quantity,unit_price_iqd,line_total_iqd']);
        });

        return response()->json([
            'message' => 'Invoice created successfully.',
            'invoice' => [
                'id' => (string) $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'job_codes' => $invoice->serviceJobs->pluck('job_code')->values()->all(),
                'amount_iqd' => (int) round((float) $invoice->total_iqd),
                'status' => $invoice->status,
                'issued_at' => $invoice->issued_at?->toIso8601String(),
            ],
        ], 201);
    }

    public function payments(Request $request): JsonResponse
    {
        $this->ensureFinanceAccess($request);

        $payments = Payment::query()
            ->with('serviceJob:id,job_code,customer_name,tv_model,status')
            ->latest('recorded_at')
            ->get()
            ->map(fn (Payment $payment) => [
                'id' => (string) $payment->id,
                'service_job_id' => (string) $payment->service_job_id,
                'receipt_number' => $payment->receipt_number,
                'amount_iqd' => (int) round((float) $payment->amount_iqd),
                'payment_method' => $payment->method,
                'note' => $payment->notes,
                'recorded_by' => $payment->recorded_by,
                'recorded_at' => $payment->recorded_at?->toIso8601String(),
                'job_code' => $payment->serviceJob?->job_code,
                'customer_name' => $payment->serviceJob?->customer_name,
                'job_status' => $payment->serviceJob?->status,
            ]);

        return response()->json([
            'payments' => $payments->all(),
            'summary' => [
                'total_amount_iqd' => (int) $payments->sum('amount_iqd'),
                'payments_count' => (int) $payments->count(),
                'cash_count' => (int) $payments->filter(fn (array $payment) => strtolower((string) $payment['payment_method']) === 'cash')->count(),
            ],
        ]);
    }

    public function debts(Request $request): JsonResponse
    {
        $this->ensureFinanceAccess($request);

        $debts = ServiceJob::query()
            ->with('assignedStaff:id,name')
            ->latest('updated_at')
            ->get()
            ->map(function (ServiceJob $job): array {
                $target = $this->amountForJob($job);
                $paid = (int) round((float) ($job->payment_received_iqd ?? 0));
                $remaining = max($target - $paid, 0);

                return [
                    'id' => (string) $job->id,
                    'job_code' => $job->job_code,
                    'customer_name' => $job->customer_name,
                    'assigned_staff_name' => $job->assignedStaff?->name,
                    'job_status' => (string) $job->status,
                    'target_iqd' => $target,
                    'paid_iqd' => $paid,
                    'remaining_iqd' => $remaining,
                    'updated_at' => $job->updated_at?->toIso8601String(),
                ];
            })
            ->filter(fn (array $debt): bool => $debt['remaining_iqd'] > 0)
            ->values();

        return response()->json([
            'debts' => $debts->all(),
            'summary' => [
                'open_debt_iqd' => (int) $debts->sum('remaining_iqd'),
                'open_jobs_count' => (int) $debts->count(),
            ],
        ]);
    }

    public function expenses(Request $request): JsonResponse
    {
        $this->ensureFinanceAccess($request);

        $monthInput = $request->string('month')->toString();
        $target = $monthInput ? Carbon::createFromFormat('Y-m', $monthInput) : Carbon::now();

        $rows = Expense::query()
            ->whereBetween('expense_date', [$target->copy()->startOfMonth()->toDateString(), $target->copy()->endOfMonth()->toDateString()])
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'expenses' => $rows->map(fn (Expense $expense): array => [
                'id' => (string) $expense->id,
                'expense_date' => $expense->expense_date?->toDateString(),
                'title' => $expense->title,
                'category' => $expense->category,
                'amount_iqd' => (int) round((float) $expense->amount_iqd),
                'note' => $expense->note,
                'recorded_by' => $expense->recorded_by,
                'recorded_at' => $expense->created_at?->toIso8601String(),
            ])->values()->all(),
            'summary' => [
                'total_expenses_iqd' => (int) round((float) $rows->sum('amount_iqd')),
                'items_count' => (int) $rows->count(),
            ],
        ]);
    }

    public function storeExpense(Request $request): JsonResponse
    {
        $this->ensureFinanceAccess($request);

        $payload = $request->validate([
            'expense_date' => ['required', 'date'],
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:40'],
            'amount_iqd' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string'],
        ]);

        $expense = Expense::create([
            'expense_date' => $payload['expense_date'],
            'title' => $payload['title'],
            'category' => $payload['category'],
            'amount_iqd' => $payload['amount_iqd'],
            'note' => $payload['note'] ?? null,
            'recorded_by' => $request->user()?->name,
        ]);

        return response()->json([
            'message' => 'Expense recorded successfully.',
            'expense' => [
                'id' => (string) $expense->id,
                'expense_date' => $expense->expense_date?->toDateString(),
                'title' => $expense->title,
                'category' => $expense->category,
                'amount_iqd' => (int) round((float) $expense->amount_iqd),
                'note' => $expense->note,
                'recorded_by' => $expense->recorded_by,
                'recorded_at' => $expense->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    public function dailyClose(Request $request): JsonResponse
    {
        $this->ensureFinanceAccess($request);

        $dateInput = $request->string('date')->toString();
        $targetDate = $dateInput
            ? Carbon::createFromFormat('Y-m-d', $dateInput)
            : Carbon::now();

        $start = $targetDate->copy()->startOfDay();
        $end = $targetDate->copy()->endOfDay();

        $payments = Payment::query()
            ->with('serviceJob:id,job_code,customer_name')
            ->whereBetween('recorded_at', [$start, $end])
            ->orderByDesc('recorded_at')
            ->get();

        return response()->json([
            'date' => $targetDate->toDateString(),
            'summary' => [
                'total_received_iqd' => (int) round((float) $payments->sum('amount_iqd')),
                'payments_count' => $payments->count(),
                'jobs_count' => $payments->pluck('service_job_id')->unique()->count(),
            ],
            'payments' => $payments->map(fn (Payment $payment): array => [
                'id' => (string) $payment->id,
                'receipt_number' => $payment->receipt_number,
                'job_code' => $payment->serviceJob?->job_code,
                'customer_name' => $payment->serviceJob?->customer_name,
                'amount_iqd' => (int) round((float) $payment->amount_iqd),
                'method' => $payment->method,
                'recorded_by' => $payment->recorded_by,
                'recorded_at' => $payment->recorded_at?->toIso8601String(),
            ])->values()->all(),
        ]);
    }

    public function monthlyCsv(Request $request): Response
    {
        $this->ensureFinanceAccess($request);

        $month = $request->string('month')->toString();
        $target = $month
            ? Carbon::createFromFormat('Y-m', $month)
            : Carbon::now();

        $start = $target->copy()->startOfMonth();
        $end = $target->copy()->endOfMonth();

        $payments = Payment::query()
            ->with('serviceJob:id,job_code,customer_name,tv_model')
            ->whereBetween('recorded_at', [$start, $end])
            ->orderBy('recorded_at')
            ->get();

        $rows = collect([
            ['Date', 'Receipt', 'Job Code', 'Customer', 'TV Model', 'Amount IQD', 'Method', 'Recorded By', 'Reference'],
        ])->concat(
            $payments->map(fn (Payment $payment): array => [
                (string) ($payment->recorded_at?->toDateString() ?? ''),
                (string) ($payment->receipt_number ?? ''),
                (string) ($payment->serviceJob?->job_code ?? ''),
                (string) ($payment->serviceJob?->customer_name ?? ''),
                (string) ($payment->serviceJob?->tv_model ?? ''),
                (string) ((int) round((float) $payment->amount_iqd)),
                (string) $payment->method,
                (string) ($payment->recorded_by ?? ''),
                (string) ($payment->reference ?? ''),
            ])
        );

        $csv = $rows
            ->map(fn (array $row): string => collect($row)
                ->map(fn (string $value): string => '"' . str_replace('"', '""', $value) . '"')
                ->implode(','))
            ->implode("\n");

        $filename = sprintf('payments-%s.csv', $target->format('Y-m'));

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
        ]);
    }

    public function monthlyReports(Request $request): JsonResponse
    {
        $this->ensureFinanceAccess($request);

        $currentMonth = Carbon::now()->startOfMonth();
        $computedCurrent = $this->computeMonthlySummary($currentMonth);

        $currentSummary = MonthlyFinanceSummary::query()->updateOrCreate(
            ['year' => $computedCurrent['year'], 'month' => $computedCurrent['month']],
            [
                ...$computedCurrent,
                'generated_at' => Carbon::now(),
            ],
        );

        $rows = MonthlyFinanceSummary::query()
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->limit(12)
            ->get()
            ->map(fn (MonthlyFinanceSummary $summary): array => [
                'id' => (string) $summary->id,
                'year' => (int) $summary->year,
                'month' => (int) $summary->month,
                'total_revenue_iqd' => (int) round((float) $summary->total_revenue_iqd),
                'total_expenses_iqd' => (int) round((float) $summary->total_expenses_iqd),
                'total_net_iqd' => (int) round((float) $summary->total_net_iqd),
                'total_jobs' => (int) $summary->total_jobs,
                'total_finished_jobs' => (int) $summary->total_finished_jobs,
                'total_open_debt_iqd' => (int) round((float) $summary->total_open_debt_iqd),
                'is_closed' => (bool) $summary->is_closed,
                'generated_at' => $summary->generated_at?->toIso8601String(),
            ]);

        return response()->json([
            'monthly_reports' => $rows->all(),
            'current' => [
                'id' => (string) $currentSummary->id,
                'year' => (int) $currentSummary->year,
                'month' => (int) $currentSummary->month,
                'total_revenue_iqd' => (int) round((float) $currentSummary->total_revenue_iqd),
                'total_expenses_iqd' => (int) round((float) $currentSummary->total_expenses_iqd),
                'total_net_iqd' => (int) round((float) $currentSummary->total_net_iqd),
                'total_jobs' => (int) $currentSummary->total_jobs,
                'total_finished_jobs' => (int) $currentSummary->total_finished_jobs,
                'total_open_debt_iqd' => (int) round((float) $currentSummary->total_open_debt_iqd),
                'is_closed' => (bool) $currentSummary->is_closed,
                'generated_at' => $currentSummary->generated_at?->toIso8601String(),
            ],
        ]);
    }

    private function ensureFinanceAccess(Request $request): void
    {
        $user = $request->user();
        abort_unless($user instanceof User && in_array($user->roleEnum()->value, ['admin', 'accountant'], true), 403);
    }

    private function amountForJob(ServiceJob $job): int
    {
        return (int) round((float) ($job->final_price_iqd ?? $job->estimated_price_iqd ?? 0));
    }

    private function openDebtForJob(ServiceJob $job): int
    {
        $target = $this->amountForJob($job);
        $paid = (int) round((float) ($job->payment_received_iqd ?? 0));

        return max($target - $paid, 0);
    }

    /**
     * @return array<string, int|bool>
     */
    private function computeMonthlySummary(Carbon $monthStart): array
    {
        $start = $monthStart->copy()->startOfMonth();
        $end = $monthStart->copy()->endOfMonth();

        $revenue = (int) round((float) Payment::query()
            ->whereBetween('recorded_at', [$start, $end])
            ->sum('amount_iqd'));

        $expenses = (int) round((float) Expense::query()
            ->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
            ->sum('amount_iqd'));

        $jobs = ServiceJob::query()
            ->whereBetween('created_at', [$start, $end])
            ->get();

        $openDebt = (int) $jobs->sum(fn (ServiceJob $job): int => $this->openDebtForJob($job));

        return [
            'year' => (int) $start->year,
            'month' => (int) $start->month,
            'total_revenue_iqd' => $revenue,
            'total_expenses_iqd' => $expenses,
            'total_net_iqd' => $revenue - $expenses,
            'total_jobs' => (int) $jobs->count(),
            'total_finished_jobs' => (int) $jobs->where('status', 'FINISHED')->count(),
            'total_open_debt_iqd' => $openDebt,
            'is_closed' => false,
        ];
    }

    /**
     * @return array<string, int|string|null>
     */
    private function transformInvoice(ServiceJob $job): array
    {
        $amount = (int) ($job->final_price_iqd ?? $job->estimated_price_iqd ?? 0);
        $paid = (int) ($job->payment_received_iqd ?? 0);
        $outstanding = max($amount - $paid, 0);

        return [
            'id' => 'INV-'.$job->job_code,
            'invoice_number' => 'INV-'.$job->job_code,
            'job_code' => $job->job_code,
            'customer_name' => $job->customer_name,
            'tv_model' => $job->tv_model,
            'assigned_staff_name' => $job->assignedStaff?->name,
            'job_status' => $job->status,
            'amount_iqd' => $amount,
            'paid_iqd' => $paid,
            'outstanding_iqd' => $outstanding,
            'status' => $outstanding === 0 && $amount > 0 ? 'PAID' : ($paid > 0 ? 'PARTIAL' : 'UNPAID'),
            'issued_at' => $job->finished_at?->toJSON() ?? $job->created_at?->toJSON(),
        ];
    }

}
