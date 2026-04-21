<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ServiceJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinanceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_invoices_summary(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        ServiceJob::query()->create([
            'job_code' => 'NGS-260330-0001',
            'customer_id' => 'customer-260330-0001',
            'customer_name' => 'Baran Karim',
            'customer_phone' => '+964 750 222 4567',
            'tv_model' => 'LG 65"',
            'category' => 'OLED',
            'issue' => 'Panel issue',
            'priority' => 'high',
            'status' => 'FINISHED',
            'final_price_iqd' => 100000,
            'payment_received_iqd' => 40000,
            'created_by_user_id' => $admin->id,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/finance/invoices')
            ->assertOk()
            ->assertJsonPath('invoices.0.invoice_number', 'INV-NGS-260330-0001')
            ->assertJsonPath('invoices.0.job_status', 'FINISHED')
            ->assertJsonPath('invoices.0.outstanding_iqd', 60000)
            ->assertJsonPath('summary.total_invoiced_iqd', 100000)
            ->assertJsonPath('summary.total_paid_iqd', 40000)
            ->assertJsonPath('summary.outstanding_iqd', 60000);
    }

    public function test_cashier_staff_can_list_payments(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        ServiceJob::query()->create([
            'job_code' => 'NGS-260330-0002',
            'customer_id' => 'customer-260330-0002',
            'customer_name' => 'Soran Ahmed',
            'customer_phone' => '+964 771 333 8888',
            'tv_model' => 'Sony 49"',
            'category' => 'LED',
            'issue' => 'Power supply replaced.',
            'priority' => 'normal',
            'status' => 'OUT',
            'final_price_iqd' => 70000,
            'payment_received_iqd' => 50000,
            'created_by_user_id' => $admin->id,
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => 'staff', 'can_record_payment' => true]));

        $this->getJson('/api/finance/payments')
            ->assertOk()
            ->assertJsonPath('payments.0.job_code', 'NGS-260330-0002')
            ->assertJsonPath('payments.0.job_status', 'OUT')
            ->assertJsonPath('payments.0.remaining_iqd', 20000)
            ->assertJsonPath('summary.received_iqd', 50000);
    }

    public function test_regular_staff_cannot_open_finance_lists(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'staff']));

        $this->getJson('/api/finance/invoices')->assertForbidden();
        $this->getJson('/api/finance/payments')->assertForbidden();
    }

    public function test_invoice_payment_route_is_registered_at_runtime(): void
    {
        $route = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($candidate) => in_array('POST', $candidate->methods(), true)
                && $candidate->uri() === 'api/finance/invoices/{invoiceId}/payments');

        $this->assertNotNull($route, 'Invoice payment route is missing from the runtime route table.');
        $this->assertSame(
            'App\Http\Controllers\Api\FinanceController@recordInvoicePayment',
            ltrim($route->getActionName(), '\\'),
        );
    }

    public function test_admin_can_create_part_sale_invoice_without_job(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Sanctum::actingAs($admin);

        $this->postJson('/api/finance/invoices', [
            'customer_name' => 'Dara Ali',
            'customer_phone' => '+964 750 000 1111',
            'customer_address' => 'Slemani',
            'extra_items' => [
                [
                    'description' => 'Remote control replacement',
                    'quantity' => 1,
                    'unit_price_iqd' => 15000,
                ],
                [
                    'description' => 'Wall mount fitting',
                    'quantity' => 1,
                    'unit_price_iqd' => 10000,
                ],
            ],
            'discount_iqd' => 5000,
            'tax_iqd' => 0,
        ])
            ->assertCreated()
            ->assertJsonPath('invoice.customer_name', 'Dara Ali')
            ->assertJsonPath('invoice.customer_phone', '+964 750 000 1111')
            ->assertJsonPath('invoice.amount_iqd', 20000);

        $this->getJson('/api/finance/invoices')
            ->assertOk()
            ->assertJsonPath('invoices.0.customer_name', 'Dara Ali')
            ->assertJsonPath('invoices.0.customer_phone', '+964 750 000 1111')
            ->assertJsonPath('invoices.0.customer_address', 'Slemani')
            ->assertJsonPath('invoices.0.amount_iqd', 20000);
    }

    public function test_admin_can_record_invoice_payment_and_see_it_in_ledger(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Sanctum::actingAs($admin);

        $invoiceResponse = $this->postJson('/api/finance/invoices', [
            'customer_name' => 'Shwan Omer',
            'customer_phone' => '+964 770 111 2222',
            'extra_items' => [
                [
                    'description' => 'T-Con board replacement',
                    'quantity' => 1,
                    'unit_price_iqd' => 30000,
                ],
            ],
        ])->assertCreated();

        $invoiceNumber = (string) $invoiceResponse->json('invoice.invoice_number');
        $invoiceId = (string) Invoice::query()->where('invoice_number', $invoiceNumber)->value('id');

        $this->postJson("/api/finance/invoices/{$invoiceId}/payments", [
            'amount_iqd' => 10000,
            'method' => 'cash',
            'reference' => 'front-desk',
            'notes' => 'Advance collected',
        ])
            ->assertOk()
            ->assertJsonPath('invoice.invoice_number', $invoiceNumber)
            ->assertJsonPath('invoice.paid_iqd', 10000)
            ->assertJsonPath('invoice.outstanding_iqd', 20000)
            ->assertJsonPath('invoice.status', 'PARTIAL');

        $this->assertDatabaseHas('invoice_payments', [
            'invoice_id' => (int) $invoiceId,
            'amount_iqd' => 10000,
            'method' => 'cash',
            'reference' => 'front-desk',
        ]);

        $this->getJson('/api/finance/payments')
            ->assertOk()
            ->assertJsonFragment([
                'job_code' => $invoiceNumber,
                'customer_name' => 'Shwan Omer',
                'amount_iqd' => 10000,
            ]);
    }

    public function test_dashboard_counts_job_linked_invoice_payment_once(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $job = ServiceJob::query()->create([
            'job_code' => 'NGS-260422-0099',
            'customer_id' => 'customer-260422-0099',
            'customer_name' => 'Aram Hasan',
            'customer_phone' => '+964 750 999 1111',
            'tv_model' => 'Samsung 55"',
            'category' => 'LED',
            'issue' => 'Backlight repair',
            'priority' => 'normal',
            'status' => 'FINISHED',
            'final_price_iqd' => 30000,
            'payment_received_iqd' => 0,
            'created_by_user_id' => $admin->id,
        ]);

        Sanctum::actingAs($admin);

        $invoiceResponse = $this->postJson('/api/finance/invoices', [
            'service_job_ids' => [$job->id],
        ])->assertCreated();

        $invoiceNumber = (string) $invoiceResponse->json('invoice.invoice_number');
        $invoiceId = (string) Invoice::query()->where('invoice_number', $invoiceNumber)->value('id');

        $this->postJson("/api/finance/invoices/{$invoiceId}/payments", [
            'amount_iqd' => 10000,
            'method' => 'cash',
        ])->assertOk();

        $this->getJson('/api/finance/dashboard')
            ->assertOk()
            ->assertJsonPath('dashboard.total_revenue_today_iqd', 10000)
            ->assertJsonPath('dashboard.total_revenue_this_month_iqd', 10000)
            ->assertJsonPath('dashboard.daily_close_status.payments_count', 1);
    }

    public function test_mixed_invoice_payment_tracks_extra_residual_after_job_balance_is_filled(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $job = ServiceJob::query()->create([
            'job_code' => 'NGS-260422-0100',
            'customer_id' => 'customer-260422-0100',
            'customer_name' => 'Lana Aziz',
            'customer_phone' => '+964 750 222 3333',
            'tv_model' => 'LG 43"',
            'category' => 'LED',
            'issue' => 'Power issue',
            'priority' => 'normal',
            'status' => 'FINISHED',
            'final_price_iqd' => 30000,
            'payment_received_iqd' => 10000,
            'created_by_user_id' => $admin->id,
        ]);

        Payment::query()->create([
            'service_job_id' => $job->id,
            'amount_iqd' => 10000,
            'method' => 'cash',
            'reference' => 'advance',
        ]);

        Sanctum::actingAs($admin);

        $invoiceResponse = $this->postJson('/api/finance/invoices', [
            'service_job_ids' => [$job->id],
            'extra_items' => [
                [
                    'description' => 'Wall mount fitting',
                    'quantity' => 1,
                    'unit_price_iqd' => 10000,
                ],
            ],
        ])->assertCreated();

        $invoiceNumber = (string) $invoiceResponse->json('invoice.invoice_number');
        $invoiceId = (string) Invoice::query()->where('invoice_number', $invoiceNumber)->value('id');

        $this->postJson("/api/finance/invoices/{$invoiceId}/payments", [
            'amount_iqd' => 25000,
            'method' => 'cash',
        ])
            ->assertOk()
            ->assertJsonPath('invoice.paid_iqd', 25000)
            ->assertJsonPath('invoice.outstanding_iqd', 15000)
            ->assertJsonPath('invoice.status', 'PARTIAL');

        $this->assertDatabaseHas('payments', [
            'service_job_id' => $job->id,
            'invoice_payment_id' => 1,
            'amount_iqd' => 20000,
        ]);

        $this->getJson('/api/finance/payments')
            ->assertOk()
            ->assertJsonPath('summary.received_iqd', 35000)
            ->assertJsonFragment([
                'job_code' => 'NGS-260422-0100',
                'amount_iqd' => 20000,
            ])
            ->assertJsonFragment([
                'job_code' => $invoiceNumber,
                'amount_iqd' => 5000,
            ]);

        $this->getJson('/api/finance/dashboard')
            ->assertOk()
            ->assertJsonPath('dashboard.total_revenue_today_iqd', 35000)
            ->assertJsonPath('dashboard.total_revenue_this_month_iqd', 35000);
    }
}
