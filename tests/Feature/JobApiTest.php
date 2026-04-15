<?php

namespace Tests\Feature;

use App\Models\ServiceJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JobApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_create_a_job_without_customer_id(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'staff']));

        $response = $this->postJson('/api/jobs', [
            'customer_name' => 'Renas Omar',
            'customer_phone' => '+964 750 111 2233',
            'tv_model' => 'Samsung 50"',
            'category' => 'LED',
            'priority' => 'normal',
            'issue' => 'No picture after startup.',
            'estimated_price_iqd' => 25000,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('job.customer_name', 'Renas Omar')
            ->assertJsonPath('job.customer_phone', '+964 750 111 2233')
            ->assertJsonPath('job.customer_record_id', null)
            ->assertJsonPath('job.tv_model', 'Samsung 50"')
            ->assertJsonPath('job.issue', 'No picture after startup.');

        $this->assertDatabaseHas('service_jobs', [
            'customer_name' => 'Renas Omar',
            'customer_phone' => '+964 750 111 2233',
            'tv_model' => 'Samsung 50"',
            'issue' => 'No picture after startup.',
        ]);
    }

    public function test_staff_can_create_a_job(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'staff']));

        $response = $this->postJson('/api/jobs', [
            'customer_name' => 'Baran Karim',
            'customer_phone' => '+964 750 222 4567',
            'tv_model' => 'LG 65"',
            'category' => 'OLED',
            'priority' => 'high',
            'issue' => 'Vertical line after transport damage.',
            'estimated_price_iqd' => 80000,
            'promised_completion_at' => '2026-04-18',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('job.customer_name', 'Baran Karim')
            ->assertJsonPath('job.status', 'PENDING')
            ->assertJsonPath('job.whatsapp_sent', false)
            ->assertJsonPath('job.promised_completion_at', fn (?string $value) => is_string($value) && str_starts_with($value, '2026-04-18'));

        $this->assertDatabaseCount('service_jobs', 1);
    }

    public function test_staff_can_update_promised_completion_date(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $job = ServiceJob::query()->create([
            'job_code' => 'NGS-260413-0001',
            'customer_id' => 'customer-260413-0001',
            'customer_name' => 'Lana Rzgar',
            'customer_phone' => '+964 750 700 1234',
            'tv_model' => 'LG 55"',
            'category' => 'LED',
            'issue' => 'Image cuts out after warmup.',
            'priority' => 'normal',
            'status' => 'PENDING',
            'created_by_user_id' => $staff->id,
        ]);

        Sanctum::actingAs($staff);

        $this->putJson("/api/jobs/{$job->job_code}", [
            'promised_completion_at' => '2026-04-21',
        ])
            ->assertOk()
            ->assertJsonPath('job.promised_completion_at', fn (?string $value) => is_string($value) && str_starts_with($value, '2026-04-21'));

        $this->assertDatabaseHas('service_jobs', [
            'id' => $job->id,
            'promised_completion_at' => '2026-04-21 00:00:00',
        ]);
    }

    public function test_accountant_can_list_jobs_but_cannot_create_or_change_status(): void
    {
        $accountant = User::factory()->create(['role' => 'accountant']);
        $job = ServiceJob::query()->create([
            'job_code' => 'NGS-260329-0001',
            'customer_id' => 'customer-260329-0001',
            'customer_name' => 'Fatima Aziz',
            'customer_phone' => '+964 772 444 9000',
            'tv_model' => 'TCL 50"',
            'category' => 'LED',
            'issue' => 'Audio board failure and HDMI port cleaning.',
            'priority' => 'normal',
            'status' => 'FINISHED',
            'created_by_user_id' => $accountant->id,
        ]);

        Sanctum::actingAs($accountant);

        $this->getJson('/api/jobs')
            ->assertOk()
            ->assertJsonPath('jobs.0.job_code', $job->job_code);

        $this->postJson('/api/jobs', [
            'customer_name' => 'Denied User',
            'customer_phone' => '+964 770 000 0000',
            'tv_model' => 'Sony 49"',
            'category' => 'LED',
            'priority' => 'normal',
            'issue' => 'Should fail.',
        ])->assertForbidden();

        $this->postJson("/api/jobs/{$job->job_code}/status")
            ->assertForbidden();
    }

    public function test_payment_action_is_limited_to_cashier_capable_roles(): void
    {
        $creator = User::factory()->create(['role' => 'admin']);
        $job = ServiceJob::query()->create([
            'job_code' => 'NGS-260329-0002',
            'customer_id' => 'customer-260329-0002',
            'customer_name' => 'Soran Ahmed',
            'customer_phone' => '+964 771 333 8888',
            'tv_model' => 'Sony 49"',
            'category' => 'LED',
            'issue' => 'Power supply replaced.',
            'priority' => 'normal',
            'status' => 'FINISHED',
            'created_by_user_id' => $creator->id,
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => 'staff']));
        $this->postJson("/api/jobs/{$job->job_code}/payments", ['amount' => 25000])
            ->assertForbidden();

        Sanctum::actingAs(User::factory()->create(['role' => 'staff', 'can_record_payment' => true]));
        $this->postJson("/api/jobs/{$job->job_code}/payments", ['amount' => 10000])
            ->assertOk()
            ->assertJsonPath('job.payment_received_iqd', 10000)
            ->assertJsonPath('job.final_price_iqd', 10000);

        Sanctum::actingAs(User::factory()->create(['role' => 'accountant']));
        $this->postJson("/api/jobs/{$job->job_code}/payments", ['amount' => 25000])
            ->assertOk()
            ->assertJsonPath('job.payment_received_iqd', 35000)
            ->assertJsonPath('job.final_price_iqd', 10000);
    }

    public function test_staff_can_advance_status_and_mark_whatsapp_sent(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $job = ServiceJob::query()->create([
            'job_code' => 'NGS-260329-0003',
            'customer_id' => 'customer-260329-0003',
            'customer_name' => 'Abdulla Mohammed',
            'customer_phone' => '+964 770 111 1234',
            'tv_model' => 'Samsung 55"',
            'category' => 'QLED',
            'issue' => 'Screen flicker and no backlight after 10 minutes.',
            'priority' => 'normal',
            'status' => 'PENDING',
            'whatsapp_sent' => false,
            'created_by_user_id' => $staff->id,
        ]);

        Sanctum::actingAs($staff);

        $this->postJson("/api/jobs/{$job->job_code}/status")
            ->assertOk()
            ->assertJsonPath('job.status', 'REPAIR')
            ->assertJsonPath('job.repair_started_at', fn (?string $value) => is_string($value) && $value !== '');

        $this->postJson("/api/jobs/{$job->job_code}/whatsapp-sent")
            ->assertOk()
            ->assertJsonPath('job.whatsapp_sent', true);

        $job->refresh();
        $this->assertNotNull($job->repair_started_at);
    }

    public function test_staff_can_list_assignable_staff_and_assign_job(): void
    {
        $actingStaff = User::factory()->create(['role' => 'staff']);
        $assignedStaff = User::factory()->create(['role' => 'staff', 'name' => 'Workshop One']);
        User::factory()->create(['role' => 'accountant', 'name' => 'Finance User']);

        $job = ServiceJob::query()->create([
            'job_code' => 'NGS-260330-0004',
            'customer_id' => 'customer-260330-0004',
            'customer_name' => 'Karzan Ali',
            'customer_phone' => '+964 750 999 9999',
            'tv_model' => 'Hisense 58"',
            'category' => 'LED',
            'issue' => 'No picture after startup.',
            'priority' => 'normal',
            'status' => 'PENDING',
            'created_by_user_id' => $actingStaff->id,
        ]);

        Sanctum::actingAs($actingStaff);

        $this->getJson('/api/jobs/staff-options')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Workshop One'])
            ->assertJsonMissing(['name' => 'Finance User']);

        $this->postJson("/api/jobs/{$job->job_code}/assign", [
            'assigned_staff_uid' => $assignedStaff->id,
        ])
            ->assertOk()
            ->assertJsonPath('job.assigned_staff_uid', (string) $assignedStaff->id)
            ->assertJsonPath('job.assigned_staff_name', 'Workshop One');

        $this->postJson("/api/jobs/{$job->job_code}/notes", [
            'technician_notes' => 'Power board tested. Main capacitor needs replacement.',
        ])
            ->assertOk()
            ->assertJsonPath('job.technician_notes', 'Power board tested. Main capacitor needs replacement.');
    }

    public function test_accountant_cannot_assign_staff_to_job(): void
    {
        $accountant = User::factory()->create(['role' => 'accountant']);
        $staff = User::factory()->create(['role' => 'staff']);
        $job = ServiceJob::query()->create([
            'job_code' => 'NGS-260330-0005',
            'customer_id' => 'customer-260330-0005',
            'customer_name' => 'Dara Hassan',
            'customer_phone' => '+964 771 101 2020',
            'tv_model' => 'Toshiba 43"',
            'category' => 'LED',
            'issue' => 'Power cycling issue.',
            'priority' => 'normal',
            'status' => 'PENDING',
            'created_by_user_id' => $staff->id,
        ]);

        Sanctum::actingAs($accountant);

        $this->postJson("/api/jobs/{$job->job_code}/assign", [
            'assigned_staff_uid' => $staff->id,
        ])->assertForbidden();

        $this->postJson("/api/jobs/{$job->job_code}/notes", [
            'technician_notes' => 'Should not be allowed.',
        ])->assertForbidden();
    }

    public function test_staff_can_filter_jobs_by_promised_day_and_overdue(): void
    {
        Carbon::setTestNow('2026-04-15 12:00:00');

        $staff = User::factory()->create(['role' => 'staff']);
        Sanctum::actingAs($staff);

        ServiceJob::query()->create([
            'job_code' => 'NGS-260415-0001',
            'customer_id' => 'customer-260415-0001',
            'customer_name' => 'Ari Omer',
            'customer_phone' => '+964 750 100 0001',
            'tv_model' => 'LG 43"',
            'category' => 'LED',
            'issue' => 'No display after power on.',
            'priority' => 'normal',
            'status' => 'PENDING',
            'promised_completion_at' => '2026-04-14 10:00:00',
            'created_by_user_id' => $staff->id,
        ]);

        ServiceJob::query()->create([
            'job_code' => 'NGS-260415-0002',
            'customer_id' => 'customer-260415-0002',
            'customer_name' => 'Bara Kawa',
            'customer_phone' => '+964 750 100 0002',
            'tv_model' => 'Samsung 50"',
            'category' => 'LED',
            'issue' => 'Backlight issue.',
            'priority' => 'normal',
            'status' => 'REPAIR',
            'promised_completion_at' => '2026-04-16 11:00:00',
            'created_by_user_id' => $staff->id,
        ]);

        ServiceJob::query()->create([
            'job_code' => 'NGS-260415-0003',
            'customer_id' => 'customer-260415-0003',
            'customer_name' => 'Cihan Nuri',
            'customer_phone' => '+964 750 100 0003',
            'tv_model' => 'TCL 55"',
            'category' => 'PANEL',
            'issue' => 'Panel burn line.',
            'priority' => 'high',
            'status' => 'PENDING',
            'created_by_user_id' => $staff->id,
        ]);

        $this->getJson('/api/jobs?overdue_promises=1')
            ->assertOk()
            ->assertJsonCount(1, 'jobs')
            ->assertJsonPath('jobs.0.job_code', 'NGS-260415-0001');

        $this->getJson('/api/jobs?promised_day=2026-04-16')
            ->assertOk()
            ->assertJsonCount(1, 'jobs')
            ->assertJsonPath('jobs.0.job_code', 'NGS-260415-0002');

        Carbon::setTestNow();
    }

    public function test_staff_can_sort_jobs_by_promise_modes(): void
    {
        Carbon::setTestNow('2026-04-15 12:00:00');

        $staff = User::factory()->create(['role' => 'staff']);
        Sanctum::actingAs($staff);

        ServiceJob::query()->create([
            'job_code' => 'NGS-260415-0101',
            'customer_id' => 'customer-260415-0101',
            'customer_name' => 'Dana Aso',
            'customer_phone' => '+964 750 200 0001',
            'tv_model' => 'LG 42"',
            'category' => 'LED',
            'issue' => 'No signal.',
            'priority' => 'normal',
            'status' => 'PENDING',
            'promised_completion_at' => '2026-04-14 09:00:00',
            'created_by_user_id' => $staff->id,
        ]);

        ServiceJob::query()->create([
            'job_code' => 'NGS-260415-0102',
            'customer_id' => 'customer-260415-0102',
            'customer_name' => 'Eman Dara',
            'customer_phone' => '+964 750 200 0002',
            'tv_model' => 'Samsung 65"',
            'category' => 'LED',
            'issue' => 'Restart loop.',
            'priority' => 'normal',
            'status' => 'REPAIR',
            'promised_completion_at' => '2026-04-16 09:00:00',
            'created_by_user_id' => $staff->id,
        ]);

        ServiceJob::query()->create([
            'job_code' => 'NGS-260415-0103',
            'customer_id' => 'customer-260415-0103',
            'customer_name' => 'Faris Barzan',
            'customer_phone' => '+964 750 200 0003',
            'tv_model' => 'Hisense 49"',
            'category' => 'M.B',
            'issue' => 'No power.',
            'priority' => 'normal',
            'status' => 'PENDING',
            'created_by_user_id' => $staff->id,
        ]);

        $this->getJson('/api/jobs?promise_sort=closest_first&per_page=10')
            ->assertOk()
            ->assertJsonPath('jobs.0.job_code', 'NGS-260415-0101')
            ->assertJsonPath('jobs.1.job_code', 'NGS-260415-0102')
            ->assertJsonPath('jobs.2.job_code', 'NGS-260415-0103');

        $this->getJson('/api/jobs?promise_sort=overdue_first&per_page=10')
            ->assertOk()
            ->assertJsonPath('jobs.0.job_code', 'NGS-260415-0101');

        Carbon::setTestNow();
    }

    public function test_staff_can_filter_jobs_by_promise_shortcuts(): void
    {
        Carbon::setTestNow('2026-04-15 12:00:00');

        $staff = User::factory()->create(['role' => 'staff']);
        Sanctum::actingAs($staff);

        ServiceJob::query()->create([
            'job_code' => 'NGS-260415-0201',
            'customer_id' => 'customer-260415-0201',
            'customer_name' => 'Havin Aram',
            'customer_phone' => '+964 750 300 0001',
            'tv_model' => 'LG 50"',
            'category' => 'LED',
            'issue' => 'No picture.',
            'priority' => 'normal',
            'status' => 'PENDING',
            'promised_completion_at' => '2026-04-15 09:00:00',
            'created_by_user_id' => $staff->id,
        ]);

        ServiceJob::query()->create([
            'job_code' => 'NGS-260415-0202',
            'customer_id' => 'customer-260415-0202',
            'customer_name' => 'Jwan Zana',
            'customer_phone' => '+964 750 300 0002',
            'tv_model' => 'Samsung 55"',
            'category' => 'LED',
            'issue' => 'Power cycling.',
            'priority' => 'normal',
            'status' => 'REPAIR',
            'promised_completion_at' => '2026-04-16 10:00:00',
            'created_by_user_id' => $staff->id,
        ]);

        ServiceJob::query()->create([
            'job_code' => 'NGS-260415-0203',
            'customer_id' => 'customer-260415-0203',
            'customer_name' => 'Lana Barzin',
            'customer_phone' => '+964 750 300 0003',
            'tv_model' => 'Hisense 49"',
            'category' => 'M.B',
            'issue' => 'No standby light.',
            'priority' => 'normal',
            'status' => 'PENDING',
            'promised_completion_at' => '2026-04-14 08:00:00',
            'created_by_user_id' => $staff->id,
        ]);

        $this->getJson('/api/jobs?promise=today&per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'jobs')
            ->assertJsonPath('jobs.0.job_code', 'NGS-260415-0201');

        $this->getJson('/api/jobs?promise=tomorrow&per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'jobs')
            ->assertJsonPath('jobs.0.job_code', 'NGS-260415-0202');

        $this->getJson('/api/jobs?promise=overdue&per_page=10')
            ->assertOk()
            ->assertJsonPath('jobs.0.job_code', 'NGS-260415-0203');

        Carbon::setTestNow();
    }

    public function test_explicit_promise_filters_override_promise_preset(): void
    {
        Carbon::setTestNow('2026-04-15 12:00:00');

        $staff = User::factory()->create(['role' => 'staff']);
        Sanctum::actingAs($staff);

        ServiceJob::query()->create([
            'job_code' => 'NGS-260415-0301',
            'customer_id' => 'customer-260415-0301',
            'customer_name' => 'Zhyan Kamal',
            'customer_phone' => '+964 750 400 0001',
            'tv_model' => 'LG 43"',
            'category' => 'LED',
            'issue' => 'No image output.',
            'priority' => 'normal',
            'status' => 'PENDING',
            'promised_completion_at' => '2026-04-14 08:30:00',
            'created_by_user_id' => $staff->id,
        ]);

        ServiceJob::query()->create([
            'job_code' => 'NGS-260415-0302',
            'customer_id' => 'customer-260415-0302',
            'customer_name' => 'Sina Yad',
            'customer_phone' => '+964 750 400 0002',
            'tv_model' => 'Samsung 65"',
            'category' => 'LED',
            'issue' => 'Restart loop.',
            'priority' => 'normal',
            'status' => 'PENDING',
            'promised_completion_at' => '2026-04-16 10:00:00',
            'created_by_user_id' => $staff->id,
        ]);

        $this->getJson('/api/jobs?promise=overdue&promised_day=2026-04-16&per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'jobs')
            ->assertJsonPath('jobs.0.job_code', 'NGS-260415-0302');

        $this->getJson('/api/jobs?promise=tomorrow&overdue_promises=1&per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'jobs')
            ->assertJsonPath('jobs.0.job_code', 'NGS-260415-0301');

        Carbon::setTestNow();
    }
}

