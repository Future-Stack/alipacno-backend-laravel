<?php

namespace Tests\Feature;

use App\Models\SavedReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SavedReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'user_type' => 'super_admin',
        ]);
    }

    public function test_can_list_saved_reports(): void
    {
        SavedReport::create([
            'name' => 'Monthly Sales Report',
            'filters' => ['start_date' => '2026-01-01', 'end_date' => '2026-01-31'],
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/saved-reports');

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Monthly Sales Report']);
    }

    public function test_can_filter_saved_reports_by_search(): void
    {
        SavedReport::create([
            'name' => 'Inventory Audit',
            'filters' => ['category' => 'beverages'],
            'created_by' => $this->adminUser->id,
        ]);

        SavedReport::create([
            'name' => 'Staff Attendance',
            'filters' => ['shift' => 'morning'],
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/saved-reports?search=Inventory');

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Inventory Audit'])
            ->assertJsonMissing(['name' => 'Staff Attendance']);
    }

    public function test_can_create_saved_report(): void
    {
        $payload = [
            'name' => 'Weekly Revenue Report',
            'filters' => ['format' => 'pdf', 'branch_id' => 1],
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/saved-reports', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Weekly Revenue Report']);

        $this->assertDatabaseHas('saved_reports', [
            'name' => 'Weekly Revenue Report',
            'created_by' => $this->adminUser->id,
        ]);
    }

    public function test_can_show_saved_report(): void
    {
        $report = SavedReport::create([
            'name' => 'Customer Feedback Summary',
            'filters' => ['min_rating' => 4],
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/saved-reports/{$report->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Customer Feedback Summary']);
    }

    public function test_can_update_saved_report(): void
    {
        $report = SavedReport::create([
            'name' => 'Old Report Name',
            'filters' => ['period' => 'daily'],
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/saved-reports/{$report->id}", [
                'name' => 'New Report Name',
                'filters' => ['period' => 'weekly'],
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'New Report Name']);

        $this->assertDatabaseHas('saved_reports', [
            'id' => $report->id,
            'name' => 'New Report Name',
        ]);
    }

    public function test_can_delete_saved_report(): void
    {
        $report = SavedReport::create([
            'name' => 'Temporary Report',
            'filters' => [],
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/saved-reports/{$report->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('saved_reports', ['id' => $report->id]);
    }

    public function test_can_run_saved_report(): void
    {
        $report = SavedReport::create([
            'name' => 'Executable Report',
            'filters' => ['status' => 'completed'],
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/saved-reports/{$report->id}/run");

        $response->assertStatus(200)
            ->assertJsonFragment(['success' => true])
            ->assertJsonFragment(['report_name' => 'Executable Report']);
    }

    public function test_can_duplicate_saved_report(): void
    {
        $report = SavedReport::create([
            'name' => 'Original Template',
            'filters' => ['module' => 'finance'],
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/saved-reports/{$report->id}/duplicate");

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Copy of Original Template']);

        $this->assertDatabaseHas('saved_reports', [
            'name' => 'Copy of Original Template',
        ]);
    }
}
