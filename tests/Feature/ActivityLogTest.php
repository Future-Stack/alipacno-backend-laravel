<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogTest extends TestCase
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

        $restaurant = \App\Models\Restaurant::create([
            'name' => 'Ali Pacino Headquarters',
            'slug' => 'ali-pacino-hq',
            'phone' => '+1234567890',
            'email' => 'info@alipacino.com',
            'address' => '123 Main St',
            'postcode' => '1000',
        ]);

        $this->branch = Branch::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Main Headquarters Branch',
            'code' => 'HQ001',
            'is_active' => true,
        ]);
    }

    public function test_can_list_activity_logs(): void
    {
        ActivityLog::create([
            'branch_id' => $this->branch->id,
            'user_type' => 'super_admin',
            'user_id' => $this->adminUser->id,
            'action' => 'login',
            'module' => 'Authentication',
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/activity-logs');

        $response->assertStatus(200)
            ->assertJsonFragment(['action' => 'login']);
    }

    public function test_can_filter_activity_logs_by_branch_module_action_and_search(): void
    {
        ActivityLog::create([
            'branch_id' => $this->branch->id,
            'user_type' => 'super_admin',
            'user_id' => $this->adminUser->id,
            'action' => 'update_settings',
            'module' => 'SystemSettings',
            'ip_address' => '192.168.1.1',
        ]);

        ActivityLog::create([
            'branch_id' => $this->branch->id,
            'user_type' => 'customer',
            'action' => 'place_order',
            'module' => 'Orders',
            'ip_address' => '10.0.0.1',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/activity-logs?branch_id={$this->branch->id}&module=SystemSettings&search=update_settings");

        $response->assertStatus(200)
            ->assertJsonFragment(['action' => 'update_settings'])
            ->assertJsonMissing(['action' => 'place_order']);
    }

    public function test_can_create_activity_log_with_context_autofill(): void
    {
        $payload = [
            'branch_id' => $this->branch->id,
            'action' => 'create_menu_item',
            'module' => 'Menu',
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/activity-logs', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['action' => 'create_menu_item'])
            ->assertJsonFragment(['user_id' => $this->adminUser->id]);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'create_menu_item',
            'module' => 'Menu',
            'user_id' => $this->adminUser->id,
        ]);
    }

    public function test_can_show_activity_log(): void
    {
        $log = ActivityLog::create([
            'branch_id' => $this->branch->id,
            'user_id' => $this->adminUser->id,
            'action' => 'delete_user',
            'module' => 'UserManagement',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/activity-logs/{$log->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['action' => 'delete_user']);
    }

    public function test_can_update_activity_log(): void
    {
        $log = ActivityLog::create([
            'action' => 'initial_action',
            'module' => 'Audit',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/activity-logs/{$log->id}", [
                'action' => 'updated_action',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['action' => 'updated_action']);

        $this->assertDatabaseHas('activity_logs', [
            'id' => $log->id,
            'action' => 'updated_action',
        ]);
    }

    public function test_can_delete_activity_log(): void
    {
        $log = ActivityLog::create([
            'action' => 'temp_action',
            'module' => 'TempModule',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/activity-logs/{$log->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('activity_logs', ['id' => $log->id]);
    }

    public function test_can_get_activity_log_statistics(): void
    {
        ActivityLog::create([
            'branch_id' => $this->branch->id,
            'action' => 'export_pdf',
            'module' => 'Reports',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/activity-logs/stats');

        $response->assertStatus(200)
            ->assertJsonStructure(['total_logs', 'by_module', 'by_action']);
    }

    public function test_can_get_distinct_modules_list(): void
    {
        ActivityLog::create([
            'action' => 'audit_check',
            'module' => 'Security',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/activity-logs/modules');

        $response->assertStatus(200)
            ->assertJsonFragment(['Security']);
    }
}
