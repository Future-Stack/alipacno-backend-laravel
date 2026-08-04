<?php

namespace Tests\Feature;

use App\Models\SavedReport;
use App\Models\ScheduledReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduledReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected SavedReport $savedReport;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'user_type' => 'super_admin',
        ]);

        $this->savedReport = SavedReport::create([
            'name' => 'Executive Summary Template',
            'filters' => ['format' => 'pdf'],
            'created_by' => $this->adminUser->id,
        ]);
    }

    public function test_can_list_scheduled_reports(): void
    {
        ScheduledReport::create([
            'report_id' => $this->savedReport->id,
            'frequency' => 'daily',
            'email_to' => 'boss@example.com',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/scheduled-reports');

        $response->assertStatus(200)
            ->assertJsonFragment(['email_to' => 'boss@example.com']);
    }

    public function test_can_filter_scheduled_reports_by_frequency_and_status(): void
    {
        ScheduledReport::create([
            'report_id' => $this->savedReport->id,
            'frequency' => 'weekly',
            'email_to' => 'weekly@example.com',
            'status' => 'active',
        ]);

        ScheduledReport::create([
            'report_id' => $this->savedReport->id,
            'frequency' => 'monthly',
            'email_to' => 'monthly@example.com',
            'status' => 'inactive',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/scheduled-reports?frequency=weekly&status=active');

        $response->assertStatus(200)
            ->assertJsonFragment(['email_to' => 'weekly@example.com'])
            ->assertJsonMissing(['email_to' => 'monthly@example.com']);
    }

    public function test_can_create_scheduled_report(): void
    {
        $payload = [
            'report_id' => $this->savedReport->id,
            'frequency' => 'daily',
            'email_to' => 'manager@example.com',
            'status' => 'active',
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/scheduled-reports', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['email_to' => 'manager@example.com']);

        $this->assertDatabaseHas('scheduled_reports', [
            'email_to' => 'manager@example.com',
            'frequency' => 'daily',
        ]);
    }

    public function test_can_show_scheduled_report(): void
    {
        $schedule = ScheduledReport::create([
            'report_id' => $this->savedReport->id,
            'frequency' => 'monthly',
            'email_to' => 'accountant@example.com',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/scheduled-reports/{$schedule->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['email_to' => 'accountant@example.com']);
    }

    public function test_can_update_scheduled_report(): void
    {
        $schedule = ScheduledReport::create([
            'report_id' => $this->savedReport->id,
            'frequency' => 'daily',
            'email_to' => 'old@example.com',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/scheduled-reports/{$schedule->id}", [
                'email_to' => 'new@example.com',
                'frequency' => 'weekly',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['email_to' => 'new@example.com']);

        $this->assertDatabaseHas('scheduled_reports', [
            'id' => $schedule->id,
            'email_to' => 'new@example.com',
            'frequency' => 'weekly',
        ]);
    }

    public function test_can_delete_scheduled_report(): void
    {
        $schedule = ScheduledReport::create([
            'report_id' => $this->savedReport->id,
            'frequency' => 'daily',
            'email_to' => 'temp@example.com',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/scheduled-reports/{$schedule->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('scheduled_reports', ['id' => $schedule->id]);
    }

    public function test_can_toggle_scheduled_report_status(): void
    {
        $schedule = ScheduledReport::create([
            'report_id' => $this->savedReport->id,
            'frequency' => 'daily',
            'email_to' => 'toggle@example.com',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->patchJson("/api/v1/scheduled-reports/{$schedule->id}/toggle-status");

        $response->assertStatus(200)
            ->assertJsonFragment(['status' => 'inactive']);

        $this->assertDatabaseHas('scheduled_reports', [
            'id' => $schedule->id,
            'status' => 'inactive',
        ]);
    }

    public function test_can_trigger_scheduled_report_run_now(): void
    {
        $schedule = ScheduledReport::create([
            'report_id' => $this->savedReport->id,
            'frequency' => 'daily',
            'email_to' => 'runnow@example.com',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/scheduled-reports/{$schedule->id}/run-now");

        $response->assertStatus(200)
            ->assertJsonFragment(['success' => true]);

        $schedule->refresh();
        $this->assertNotNull($schedule->last_run);
        $this->assertNotNull($schedule->next_run);
    }
}
