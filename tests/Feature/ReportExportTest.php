<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ReportExport;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'user_type' => 'super_admin',
        ]);

        $restaurant = Restaurant::create([
            'name' => 'Main Restaurant',
            'slug' => 'main-restaurant',
            'phone' => '1234567890',
            'email' => 'restaurant@example.com',
            'address' => '123 Main St',
            'postcode' => '12345',
            'status' => 'active',
        ]);

        $this->branch = Branch::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Downtown Branch',
            'status' => 'active',
        ]);
    }

    public function test_can_list_report_exports(): void
    {
        ReportExport::create([
            'branch_id' => $this->branch->id,
            'report_name' => 'Monthly Sales Report',
            'exported_by' => $this->adminUser->id,
            'format' => 'csv',
            'file' => 'exports/sales_jan.csv',
            'generated_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/report-exports');

        $response->assertStatus(200)
            ->assertJsonFragment(['report_name' => 'Monthly Sales Report']);
    }

    public function test_can_filter_report_exports_by_format_and_branch(): void
    {
        ReportExport::create([
            'branch_id' => $this->branch->id,
            'report_name' => 'PDF Financial Report',
            'exported_by' => $this->adminUser->id,
            'format' => 'pdf',
            'file' => 'exports/fin.pdf',
            'generated_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/report-exports?branch_id={$this->branch->id}&format=pdf");

        $response->assertStatus(200)
            ->assertJsonFragment(['report_name' => 'PDF Financial Report']);
    }

    public function test_can_create_report_export(): void
    {
        $payload = [
            'branch_id' => $this->branch->id,
            'report_name' => 'Inventory Audit Report',
            'format' => 'xlsx',
            'file' => 'exports/inventory.xlsx',
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/report-exports', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['report_name' => 'Inventory Audit Report']);

        $this->assertDatabaseHas('report_exports', [
            'report_name' => 'Inventory Audit Report',
            'format' => 'xlsx',
        ]);
    }

    public function test_can_show_report_export(): void
    {
        $export = ReportExport::create([
            'branch_id' => $this->branch->id,
            'report_name' => 'Customer Segment Report',
            'exported_by' => $this->adminUser->id,
            'format' => 'json',
            'file' => 'exports/cust.json',
            'generated_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/report-exports/{$export->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['report_name' => 'Customer Segment Report']);
    }

    public function test_can_update_report_export(): void
    {
        $export = ReportExport::create([
            'branch_id' => $this->branch->id,
            'report_name' => 'Old Report Name',
            'exported_by' => $this->adminUser->id,
            'format' => 'csv',
            'file' => 'exports/old.csv',
            'generated_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/report-exports/{$export->id}", [
                'report_name' => 'Updated Report Name',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['report_name' => 'Updated Report Name']);

        $this->assertDatabaseHas('report_exports', [
            'id' => $export->id,
            'report_name' => 'Updated Report Name',
        ]);
    }

    public function test_can_delete_report_export(): void
    {
        $export = ReportExport::create([
            'branch_id' => $this->branch->id,
            'report_name' => 'Report To Delete',
            'exported_by' => $this->adminUser->id,
            'format' => 'csv',
            'file' => 'exports/delete_me.csv',
            'generated_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/report-exports/{$export->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('report_exports', ['id' => $export->id]);
    }

    public function test_can_get_download_info(): void
    {
        $export = ReportExport::create([
            'branch_id' => $this->branch->id,
            'report_name' => 'Downloadable Report',
            'exported_by' => $this->adminUser->id,
            'format' => 'csv',
            'file' => 'exports/download.csv',
            'generated_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/report-exports/{$export->id}/download");

        $response->assertStatus(200)
            ->assertJsonFragment(['report_name' => 'Downloadable Report']);
    }

    public function test_can_generate_report_export(): void
    {
        $payload = [
            'report_type' => 'sales',
            'branch_id' => $this->branch->id,
            'format' => 'pdf',
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/report-exports/generate', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['success' => true]);

        $this->assertDatabaseHas('report_exports', [
            'branch_id' => $this->branch->id,
            'format' => 'pdf',
        ]);
    }
}
