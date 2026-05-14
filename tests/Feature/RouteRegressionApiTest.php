<?php

namespace Tests\Feature;

use App\Models\ServiceJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RouteRegressionApiTest extends TestCase
{
    use RefreshDatabase;

    private function createBaseReturnSourceJob(): ServiceJob
    {
        $creator = User::factory()->create(['role' => 'staff']);

        return ServiceJob::query()->create([
            'job_code' => 'NGS-260514-9010',
            'customer_id' => 'customer-260514-9010',
            'customer_name' => 'Return Source',
            'customer_phone' => '07509998877',
            'tv_model' => 'TCL 55"',
            'category' => 'LED',
            'issue' => 'Backlight issue',
            'priority' => 'normal',
            'status' => 'FINISHED',
            'created_by_user_id' => $creator->id,
        ]);
    }

    public function test_admin_can_access_whatsapp_status_endpoint(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/whatsapp-status');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'configured',
                'missing',
                'support_number',
                'phone_number_id_preview',
                'business_account_id_preview',
                'diagnostics',
            ]);
    }

    public function test_non_admin_cannot_access_whatsapp_status_endpoint(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        Sanctum::actingAs($staff);

        $this->getJson('/api/admin/whatsapp-status')->assertForbidden();
    }

    public function test_guest_cannot_access_whatsapp_status_endpoint(): void
    {
        $this->getJson('/api/admin/whatsapp-status')->assertUnauthorized();
    }

    public function test_admin_whatsapp_test_endpoint_returns_validation_when_not_configured(): void
    {
        config()->set('services.whatsapp.phone_number_id', '');
        config()->set('services.whatsapp.business_account_id', '');
        config()->set('services.whatsapp.access_token', '');

        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/whatsapp-test', [
            'phone' => '07701234567',
            'customer_name' => 'Route Test',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('message', 'WhatsApp is not configured correctly.');
    }

    public function test_guest_cannot_access_whatsapp_test_endpoint(): void
    {
        $this->postJson('/api/admin/whatsapp-test', [
            'phone' => '07701234567',
            'customer_name' => 'Guest',
        ])->assertUnauthorized();
    }

    public function test_public_tracking_requires_valid_token(): void
    {
        $creator = User::factory()->create(['role' => 'staff']);

        $job = ServiceJob::query()->create([
            'job_code' => 'NGS-260514-9001',
            'customer_id' => 'customer-260514-9001',
            'customer_name' => 'Tracking User',
            'customer_phone' => '07501234567',
            'tv_model' => 'Samsung 50"',
            'category' => 'LED',
            'issue' => 'No display',
            'priority' => 'normal',
            'status' => 'PENDING',
            'created_by_user_id' => $creator->id,
        ]);

        $this->getJson("/api/public/jobs/{$job->job_code}/tracking?token=invalid-token")
            ->assertStatus(403)
            ->assertJsonPath('message', 'Invalid tracking token.');
    }

    public function test_public_tracking_returns_job_data_with_valid_token(): void
    {
        $creator = User::factory()->create(['role' => 'staff']);

        $job = ServiceJob::query()->create([
            'job_code' => 'NGS-260514-9002',
            'customer_id' => 'customer-260514-9002',
            'customer_name' => 'Tracking User 2',
            'customer_phone' => '07507654321',
            'tv_model' => 'LG 43"',
            'category' => 'panel',
            'issue' => 'Line on screen',
            'priority' => 'normal',
            'status' => 'REPAIR',
            'created_by_user_id' => $creator->id,
        ]);

        $token = $job->trackingToken();

        $this->getJson("/api/public/jobs/{$job->job_code}/tracking?token={$token}")
            ->assertOk()
            ->assertJsonPath('job.job_code', $job->job_code)
            ->assertJsonPath('job.customer_name', 'Tracking User 2')
            ->assertJsonPath('job.status', 'REPAIR')
            ->assertJsonPath('job.category', 'PANEL');
    }

    public function test_accountant_can_create_return_job_via_return_route(): void
    {
        config()->set('services.whatsapp.phone_number_id', '');
        config()->set('services.whatsapp.business_account_id', '');
        config()->set('services.whatsapp.access_token', '');

        $sourceJob = $this->createBaseReturnSourceJob();
        $accountant = User::factory()->create(['role' => 'accountant']);
        Sanctum::actingAs($accountant);

        $this->postJson("/api/jobs/{$sourceJob->job_code}/return", [
            'notes' => 'Customer requested warranty re-check.',
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Return job created successfully.')
            ->assertJsonPath('job.returned_from_job_id', (string) $sourceJob->id)
            ->assertJsonPath('job.status', 'PENDING');
    }

    public function test_cashier_cannot_create_return_job_via_return_route(): void
    {
        $sourceJob = $this->createBaseReturnSourceJob();
        $cashier = User::factory()->create(['role' => 'cashier']);
        Sanctum::actingAs($cashier);

        $this->postJson("/api/jobs/{$sourceJob->job_code}/return", [
            'notes' => 'Should be rejected for cashier role.',
        ])->assertForbidden();
    }

    public function test_staff_can_create_return_job_via_return_route(): void
    {
        config()->set('services.whatsapp.phone_number_id', '');
        config()->set('services.whatsapp.business_account_id', '');
        config()->set('services.whatsapp.access_token', '');

        $sourceJob = $this->createBaseReturnSourceJob();
        $staff = User::factory()->create(['role' => 'staff']);
        Sanctum::actingAs($staff);

        $this->postJson("/api/jobs/{$sourceJob->job_code}/return", [
            'notes' => 'Staff-triggered warranty return.',
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Return job created successfully.')
            ->assertJsonPath('job.returned_from_job_id', (string) $sourceJob->id)
            ->assertJsonPath('job.status', 'PENDING');
    }

    public function test_admin_can_create_return_job_via_return_route(): void
    {
        config()->set('services.whatsapp.phone_number_id', '');
        config()->set('services.whatsapp.business_account_id', '');
        config()->set('services.whatsapp.access_token', '');

        $sourceJob = $this->createBaseReturnSourceJob();
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $this->postJson("/api/jobs/{$sourceJob->job_code}/return", [
            'notes' => 'Admin-triggered warranty return.',
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Return job created successfully.')
            ->assertJsonPath('job.returned_from_job_id', (string) $sourceJob->id)
            ->assertJsonPath('job.status', 'PENDING');
    }

    public function test_guest_cannot_create_return_job_via_return_route(): void
    {
        $sourceJob = $this->createBaseReturnSourceJob();

        $this->postJson("/api/jobs/{$sourceJob->job_code}/return", [
            'notes' => 'Guest should not be able to create return job.',
        ])->assertUnauthorized();
    }
}
