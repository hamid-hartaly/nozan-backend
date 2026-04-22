<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\InvoicePayment;
use App\Models\MonthlyFinanceSummary;
use App\Models\Payment;
use App\Models\ServiceJob;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
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

        $todayResidualInvoicePayments = $this->invoicePaymentResidualEntries($todayStart, $todayEnd);
        $monthResidualInvoicePayments = $this->invoicePaymentResidualEntries($monthStart, $monthEnd);

        $revenueToday = (int) round((float) Payment::query()
            ->whereBetween('recorded_at', [$todayStart, $todayEnd])
            ->sum('amount_iqd'))
            + (int) $todayResidualInvoicePayments->sum('amount_iqd');

        $revenueThisMonth = (int) round((float) Payment::query()
            ->whereBetween('recorded_at', [$monthStart, $monthEnd])
            ->sum('amount_iqd'))
            + (int) $monthResidualInvoicePayments->sum('amount_iqd');

        $expensesThisMonth = (int) round((float) Expense::query()
            ->whereBetween('expense_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->sum('amount_iqd'));

        $openDebt = (int) ServiceJob::query()->get()->sum(function (ServiceJob $job): int {
            return $this->openDebtForJob($job);
        }) + (int) round((float) Invoice::query()->doesntHave('serviceJobs')->sum('outstanding_iqd'));

        $pendingWithBalance = (int) ServiceJob::query()
            ->whereNotIn('status', ['OUT', 'CHECKED_OUT'])
            ->get()
            ->filter(fn (ServiceJob $job): bool => $this->openDebtForJob($job) > 0)
            ->count();

        $todayPaymentsCount = Payment::query()
            ->whereBetween('recorded_at', [$todayStart, $todayEnd])
            ->count()
            + $todayResidualInvoicePayments->count();

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
            ->with([
                'serviceJobs:id,job_code,customer_name,customer_phone,tv_model,status,assigned_staff_uid,customer_record_id,issue,technician_notes,estimated_price_iqd,final_price_iqd,payment_received_iqd',
                'serviceJobs.assignedStaff:id,name',
                'serviceJobs.customer:id,address',
                'serviceJobs.stockMovements:id,inventory_item_id,service_job_id,quantity,type,reason',
                'serviceJobs.stockMovements.inventoryItem:id,name,sku',
                'lineItems:id,invoice_id,item_type,description,quantity,unit_price_iqd,line_total_iqd',
                'payments:id,invoice_id,amount_iqd,method,reference,receipt_number,recorded_by,recorded_at,notes',
            ])
            ->latest('issued_at')
            ->get();

        if ($invoices->isEmpty()) {
            $jobs = ServiceJob::query()
                ->with([
                    'assignedStaff:id,name',
                    'customer:id,address',
                    'stockMovements:id,inventory_item_id,service_job_id,quantity,type,reason',
                    'stockMovements.inventoryItem:id,name,sku',
                ])
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
                'customer_name' => $customers->join(', ') ?: $invoice->customer_name,
                'customer_phone' => $invoice->serviceJobs->pluck('customer_phone')->filter()->unique()->join(', ') ?: $invoice->customer_phone,
                'tv_model' => $models->join(' | '),
                'assigned_staff_name' => $staffNames->join(', '),
                'job_status' => (string) ($invoice->serviceJobs->first()?->status ?? 'FINISHED'),
                'amount_iqd' => (int) round((float) $invoice->total_iqd),
                'paid_iqd' => (int) round((float) $invoice->paid_iqd),
                'outstanding_iqd' => (int) round((float) $invoice->outstanding_iqd),
                'status' => $invoice->status,
                'issued_at' => $invoice->issued_at?->toIso8601String(),
                'recorded_by' => $invoice->recorded_by,
                'subtotal_iqd' => (int) round((float) $invoice->subtotal_iqd),
                'discount_iqd' => (int) round((float) $invoice->discount_iqd),
                'tax_iqd' => (int) round((float) $invoice->tax_iqd),
                'customer_address' => $invoice->serviceJobs->map(fn (ServiceJob $job): ?string => $job->customer?->address)->filter()->unique()->join(', ') ?: $invoice->customer_address,
                'line_items' => $invoice->lineItems->map(fn (InvoiceLineItem $item): array => [
                    'id' => (string) $item->id,
                    'item_type' => $item->item_type,
                    'description' => $item->description,
                    'quantity' => (float) $item->quantity,
                    'unit_price_iqd' => (int) round((float) $item->unit_price_iqd),
                    'line_total_iqd' => (int) round((float) $item->line_total_iqd),
                ])->values()->all(),
                'job_details' => $invoice->serviceJobs->map(fn (ServiceJob $job): array => $this->transformInvoiceJob($job))->values()->all(),
                'payment_entries' => $invoice->payments->map(fn (InvoicePayment $payment): array => [
                    'id' => (string) $payment->id,
                    'amount_iqd' => (int) round((float) $payment->amount_iqd),
                    'method' => $payment->method,
                    'reference' => $payment->reference,
                    'receipt_number' => $payment->receipt_number,
                    'recorded_by' => $payment->recorded_by,
                    'recorded_at' => $payment->recorded_at?->toIso8601String(),
                    'notes' => $payment->notes,
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
            'service_job_ids' => ['nullable', 'array'],
            'service_job_ids.*' => ['integer', 'exists:service_jobs,id'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:255'],
            'customer_address' => ['nullable', 'string', 'max:255'],
            'extra_items' => ['nullable', 'array'],
            'extra_items.*.description' => ['required_with:extra_items', 'string', 'max:255'],
            'extra_items.*.model' => ['nullable', 'string', 'max:255'],
            'extra_items.*.quantity' => ['nullable', 'numeric', 'min:0.01'],
            'extra_items.*.unit_price_iqd' => ['nullable', 'numeric', 'min:0'],
            'discount_iqd' => ['nullable', 'numeric', 'min:0'],
            'tax_iqd' => ['nullable', 'numeric', 'min:0'],
        ]);

        $jobIds = collect($payload['service_job_ids'] ?? [])->filter()->values();
        $extraItemsPayload = collect($payload['extra_items'] ?? [])->filter(fn (mixed $item): bool => is_array($item));

        abort_if($jobIds->isEmpty() && $extraItemsPayload->isEmpty(), 422, 'Select a job or add at least one invoice item.');
        abort_if($jobIds->isEmpty() && blank($payload['customer_name'] ?? null), 422, 'Customer name is required when no job is selected.');

        $invoice = DB::transaction(function () use ($payload, $request): Invoice {
            /** @var EloquentCollection<int, ServiceJob> $jobs */
            $jobs = ServiceJob::query()
                ->whereIn('id', $payload['service_job_ids'] ?? [])
                ->get();

            $jobSubtotal = (float) $jobs->sum(fn (ServiceJob $job): int => $this->amountForJob($job));

            $extraItems = collect($payload['extra_items'] ?? [])->map(function (array $item): array {
                $qty = (float) ($item['quantity'] ?? 1);
                $unit = (float) ($item['unit_price_iqd'] ?? 0);
                $model = trim((string) ($item['model'] ?? ''));

                return [
                    'description' => $item['description'],
                    'model' => $model !== '' ? $model : null,
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
                'customer_name' => $jobs->pluck('customer_name')->filter()->unique()->join(', ') ?: ($payload['customer_name'] ?? null),
                'customer_phone' => $jobs->pluck('customer_phone')->filter()->unique()->join(', ') ?: ($payload['customer_phone'] ?? null),
                'customer_address' => $jobs->map(fn (ServiceJob $job): ?string => $job->customer?->address)->filter()->unique()->join(', ') ?: ($payload['customer_address'] ?? null),
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

            if ($jobs->isNotEmpty()) {
                $invoice->serviceJobs()->sync($jobs->pluck('id')->all());
            }

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
                    'description' => $item['model']
                        ? sprintf('%s (Model: %s)', $item['description'], $item['model'])
                        : $item['description'],
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
                'customer_name' => $invoice->customer_name,
                'customer_phone' => $invoice->customer_phone,
                'amount_iqd' => (int) round((float) $invoice->total_iqd),
                'status' => $invoice->status,
                'issued_at' => $invoice->issued_at?->toIso8601String(),
            ],
        ], 201);
    }

    public function recordInvoicePayment(Request $request, string $invoiceId): JsonResponse
    {
        $this->ensurePaymentsAccess($request);

        $invoiceModel = Invoice::query()->findOrFail((int) $invoiceId);

        $payload = $request->validate([
            'amount_iqd' => ['required', 'numeric', 'min:1'],
            'method' => ['nullable', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $amount = (int) round((float) $payload['amount_iqd']);

        $payment = DB::transaction(function () use ($invoiceModel, $payload, $amount): InvoicePayment {
            /** @var Invoice $lockedInvoice */
            $lockedInvoice = Invoice::query()
                ->whereKey($invoiceModel->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_if($amount > (int) round((float) $lockedInvoice->outstanding_iqd), 422, 'Payment cannot exceed invoice outstanding balance.');

            $lockedInvoice->loadMissing(['serviceJobs', 'lineItems']);

            $payment = InvoicePayment::create([
                'invoice_id' => $lockedInvoice->id,
                'amount_iqd' => $amount,
                'method' => $payload['method'] ?? 'cash',
                'reference' => $payload['reference'] ?? null,
                'notes' => $payload['notes'] ?? null,
            ]);

            $remainingAmount = $amount;
            $jobs = $lockedInvoice->serviceJobs()->get();

            foreach ($jobs as $job) {
                if ($remainingAmount <= 0) {
                    break;
                }

                $jobRemaining = max($this->amountForJob($job) - (int) round((float) ($job->payment_received_iqd ?? 0)), 0);

                if ($jobRemaining <= 0) {
                    continue;
                }

                $allocated = min($remainingAmount, $jobRemaining);

                Payment::create([
                    'service_job_id' => $job->id,
                    'invoice_payment_id' => $payment->id,
                    'amount_iqd' => $allocated,
                    'method' => $payload['method'] ?? 'cash',
                    'reference' => $payload['reference'] ?? null,
                    'notes' => $payload['notes'] ?? null,
                ]);

                $job->increment('payment_received_iqd', $allocated);
                $remainingAmount -= $allocated;
            }

            $lockedInvoice->paid_iqd = (float) $lockedInvoice->paid_iqd + $amount;
            $lockedInvoice->outstanding_iqd = max((float) $lockedInvoice->total_iqd - (float) $lockedInvoice->paid_iqd, 0);
            $lockedInvoice->status = $lockedInvoice->outstanding_iqd <= 0
                ? 'PAID'
                : ((float) $lockedInvoice->paid_iqd > 0 ? 'PARTIAL' : 'UNPAID');
            $lockedInvoice->save();

            return $payment->fresh();
        });

        $invoiceModel->refresh();

        return response()->json([
            'message' => 'Invoice payment recorded successfully.',
            'payment' => [
                'id' => (string) $payment->id,
                'amount_iqd' => (int) round((float) $payment->amount_iqd),
                'method' => $payment->method,
                'reference' => $payment->reference,
                'receipt_number' => $payment->receipt_number,
                'recorded_by' => $payment->recorded_by,
                'recorded_at' => $payment->recorded_at?->toIso8601String(),
                'notes' => $payment->notes,
            ],
            'invoice' => [
                'id' => (string) $invoiceModel->id,
                'invoice_number' => $invoiceModel->invoice_number,
                'paid_iqd' => (int) round((float) $invoiceModel->paid_iqd),
                'outstanding_iqd' => (int) round((float) $invoiceModel->outstanding_iqd),
                'status' => $invoiceModel->status,
            ],
        ]);
    }

    public function payments(Request $request): JsonResponse
    {
        $this->ensurePaymentsAccess($request);

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
                'remaining_iqd' => max(
                    (int) round((float) ($payment->serviceJob?->final_price_iqd ?? $payment->serviceJob?->estimated_price_iqd ?? 0))
                    - (int) round((float) ($payment->serviceJob?->payment_received_iqd ?? 0)),
                    0,
                ),
            ]);

        if ($payments->isEmpty()) {
            $payments = ServiceJob::query()
                ->where(function ($query): void {
                    $query->whereNotNull('payment_received_iqd')->where('payment_received_iqd', '>', 0);
                })
                ->latest('updated_at')
                ->get()
                ->map(fn (ServiceJob $job): array => [
                    'id' => 'job-'.(string) $job->id,
                    'service_job_id' => (string) $job->id,
                    'receipt_number' => null,
                    'amount_iqd' => (int) round((float) ($job->payment_received_iqd ?? 0)),
                    'payment_method' => 'cash',
                    'note' => null,
                    'recorded_by' => $job->createdBy?->name,
                    'recorded_at' => $job->updated_at?->toIso8601String(),
                    'job_code' => $job->job_code,
                    'customer_name' => $job->customer_name,
                    'job_status' => $job->status,
                    'remaining_iqd' => max(
                        (int) round((float) ($job->final_price_iqd ?? $job->estimated_price_iqd ?? 0))
                        - (int) round((float) ($job->payment_received_iqd ?? 0)),
                        0,
                    ),
                ]);
        }

        $invoicePayments = $this->invoicePaymentResidualEntries();

        $allPayments = $payments->concat($invoicePayments)->sortByDesc(fn (array $payment) => $payment['recorded_at'] ?? '')->values();

        return response()->json([
            'payments' => $allPayments->all(),
            'summary' => [
                'total_amount_iqd' => (int) $allPayments->sum('amount_iqd'),
                'received_iqd' => (int) $allPayments->sum('amount_iqd'),
                'payments_count' => (int) $allPayments->count(),
                'cash_count' => (int) $allPayments->filter(fn (array $payment) => strtolower((string) $payment['payment_method']) === 'cash')->count(),
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

        $invoicePayments = $this->invoicePaymentResidualEntries($start, $end);

        $combinedPayments = $payments->map(fn (Payment $payment): array => [
            'id' => (string) $payment->id,
            'receipt_number' => $payment->receipt_number,
            'job_code' => $payment->serviceJob?->job_code,
            'customer_name' => $payment->serviceJob?->customer_name,
            'amount_iqd' => (int) round((float) $payment->amount_iqd),
            'method' => $payment->method,
            'recorded_by' => $payment->recorded_by,
            'recorded_at' => $payment->recorded_at?->toIso8601String(),
        ])->concat(
            $invoicePayments->map(fn (array $payment): array => [
                'id' => $payment['id'],
                'receipt_number' => $payment['receipt_number'],
                'job_code' => $payment['job_code'],
                'customer_name' => $payment['customer_name'],
                'amount_iqd' => $payment['amount_iqd'],
                'method' => $payment['payment_method'],
                'recorded_by' => $payment['recorded_by'],
                'recorded_at' => $payment['recorded_at'],
            ])
        )->sortByDesc(fn (array $payment) => $payment['recorded_at'] ?? '')->values();

        return response()->json([
            'date' => $targetDate->toDateString(),
            'summary' => [
                'total_received_iqd' => (int) round((float) $combinedPayments->sum('amount_iqd')),
                'payments_count' => $combinedPayments->count(),
                'jobs_count' => $combinedPayments->pluck('job_code')->filter()->unique()->count(),
            ],
            'payments' => $combinedPayments->all(),
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

        $invoicePayments = $this->invoicePaymentResidualEntries($start, $end)->sortBy('recorded_at')->values();

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
        )->concat(
            $invoicePayments->map(fn (array $payment): array => [
                (string) (filled($payment['recorded_at']) ? Carbon::parse((string) $payment['recorded_at'])->toDateString() : ''),
                (string) ($payment['receipt_number'] ?? ''),
                (string) ($payment['job_code'] ?? ''),
                (string) ($payment['customer_name'] ?? ''),
                (string) ($payment['tv_model'] ?? 'Invoice payment'),
                (string) ((int) ($payment['amount_iqd'] ?? 0)),
                (string) ($payment['payment_method'] ?? ''),
                (string) ($payment['recorded_by'] ?? ''),
                (string) ($payment['reference'] ?? ''),
            ])
        );

        $csv = $rows
            ->map(fn (array $row): string => collect($row)
                ->map(fn (string $value): string => '"'.str_replace('"', '""', $value).'"')
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

    private function ensurePaymentsAccess(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user instanceof User
            && (in_array($user->roleEnum()->value, ['admin', 'accountant'], true) || $user->canRecordPaymentPermission()),
            403,
        );
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
     * @return Collection<int, array<string, int|string|null>>
     */
    private function invoicePaymentResidualEntries(?Carbon $start = null, ?Carbon $end = null): Collection
    {
        return InvoicePayment::query()
            ->with([
                'invoice:id,invoice_number,customer_name,outstanding_iqd,status',
                'invoice.serviceJobs:id,job_code',
                'allocatedPayments:id,invoice_payment_id,amount_iqd',
            ])
            ->when($start && $end, fn ($query) => $query->whereBetween('recorded_at', [$start, $end]))
            ->latest('recorded_at')
            ->get()
            ->map(function (InvoicePayment $payment): ?array {
                $invoice = $payment->invoice;
                if (! $invoice instanceof Invoice) {
                    return null;
                }

                $amount = (int) round((float) $payment->amount_iqd);
                $allocatedAmount = (int) round((float) $payment->allocatedPayments->sum('amount_iqd'));
                $hasJobLinks = $invoice->serviceJobs->isNotEmpty();

                $residualAmount = $hasJobLinks
                    ? max($amount - $allocatedAmount, 0)
                    : $amount;

                if ($residualAmount <= 0) {
                    return null;
                }

                return [
                    'id' => 'invoice-payment-'.(string) $payment->id,
                    'service_job_id' => 'invoice-'.(string) $payment->invoice_id,
                    'receipt_number' => $payment->receipt_number,
                    'amount_iqd' => $residualAmount,
                    'payment_method' => $payment->method,
                    'note' => $payment->notes,
                    'recorded_by' => $payment->recorded_by,
                    'recorded_at' => $payment->recorded_at?->toIso8601String(),
                    'job_code' => $invoice->invoice_number,
                    'customer_name' => $invoice->customer_name,
                    'job_status' => 'CHECKED_OUT',
                    'remaining_iqd' => (int) round((float) $invoice->outstanding_iqd),
                    'tv_model' => $hasJobLinks ? 'Invoice extras' : 'Invoice payment',
                    'status' => (float) $invoice->outstanding_iqd <= 0 ? 'CLEARED' : 'PARTIAL',
                    'reference' => $payment->reference,
                ];
            })
            ->filter()
            ->values();
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
            ->sum('amount_iqd'))
            + (int) $this->invoicePaymentResidualEntries($start, $end)->sum('amount_iqd');

        $expenses = (int) round((float) Expense::query()
            ->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
            ->sum('amount_iqd'));

        $jobs = ServiceJob::query()
            ->whereBetween('created_at', [$start, $end])
            ->get();

        $openDebt = (int) $jobs->sum(fn (ServiceJob $job): int => $this->openDebtForJob($job))
            + (int) round((float) Invoice::query()
                ->doesntHave('serviceJobs')
                ->whereBetween('created_at', [$start, $end])
                ->sum('outstanding_iqd'));

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
            'job_codes' => [$job->job_code],
            'customer_name' => $job->customer_name,
            'customer_phone' => $job->customer_phone,
            'customer_address' => $job->customer?->address,
            'tv_model' => $job->tv_model,
            'assigned_staff_name' => $job->assignedStaff?->name,
            'job_status' => $job->status,
            'amount_iqd' => $amount,
            'paid_iqd' => $paid,
            'outstanding_iqd' => $outstanding,
            'status' => $outstanding === 0 && $amount > 0 ? 'PAID' : ($paid > 0 ? 'PARTIAL' : 'UNPAID'),
            'issued_at' => $job->finished_at?->toJSON() ?? $job->created_at?->toJSON(),
            'recorded_by' => $job->createdBy?->name,
            'subtotal_iqd' => $amount,
            'discount_iqd' => 0,
            'tax_iqd' => 0,
            'line_items' => [[
                'id' => 'job-'.$job->id,
                'item_type' => 'job',
                'description' => sprintf('%s - %s', $job->job_code, $job->tv_model),
                'quantity' => 1,
                'unit_price_iqd' => $amount,
                'line_total_iqd' => $amount,
            ]],
            'job_details' => [$this->transformInvoiceJob($job)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transformInvoiceJob(ServiceJob $job): array
    {
        $amount = $this->amountForJob($job);
        $paid = (int) round((float) ($job->payment_received_iqd ?? 0));

        return [
            'id' => (string) $job->id,
            'job_code' => $job->job_code,
            'customer_name' => $job->customer_name,
            'customer_phone' => $job->customer_phone,
            'customer_address' => $job->customer?->address,
            'tv_model' => $job->tv_model,
            'issue' => $job->issue ?: $job->problem,
            'technician_notes' => $job->technician_notes,
            'assigned_staff_name' => $job->assignedStaff?->name,
            'job_status' => (string) $job->status,
            'amount_iqd' => $amount,
            'paid_iqd' => $paid,
            'remaining_iqd' => max($amount - $paid, 0),
            'parts_used' => $job->stockMovements
                ->where('type', 'out')
                ->values()
                ->map(fn ($movement): array => [
                    'id' => (string) $movement->id,
                    'part_name' => $movement->inventoryItem?->name,
                    'part_sku' => $movement->inventoryItem?->sku,
                    'quantity' => (int) $movement->quantity,
                    'reason' => $movement->reason,
                ])
                ->all(),
        ];
    }
}
