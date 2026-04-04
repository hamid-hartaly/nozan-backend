<?php

namespace Tests\Feature;

use App\Models\ServiceJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}

