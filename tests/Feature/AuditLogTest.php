<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
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

    public function test_can_list_audit_logs(): void
    {
        AuditLog::create([
            'user_type' => 'super_admin',
            'user_id' => $this->adminUser->id,
            'module' => 'Restaurant',
            'module_id' => 1,
            'action' => 'created',
            'old_data' => null,
            'new_data' => ['name' => 'Sample Restaurant'],
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/audit-logs');

        $response->assertStatus(200)
            ->assertJsonFragment(['action' => 'created']);
    }

    public function test_can_filter_audit_logs(): void
    {
        AuditLog::create([
            'user_id' => $this->adminUser->id,
            'module' => 'Restaurant',
            'action' => 'updated',
        ]);

        AuditLog::create([
            'user_id' => $this->adminUser->id,
            'module' => 'Order',
            'action' => 'deleted',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/audit-logs?module=Restaurant&action=updated');

        $response->assertStatus(200)
            ->assertJsonFragment(['action' => 'updated'])
            ->assertJsonMissing(['action' => 'deleted']);
    }

    public function test_can_create_audit_log_manually(): void
    {
        $payload = [
            'module' => 'SystemSetting',
            'action' => 'update_setting',
            'old_data' => ['key' => 'old'],
            'new_data' => ['key' => 'new'],
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/audit-logs', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['module' => 'SystemSetting']);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'SystemSetting',
            'action' => 'update_setting',
        ]);
    }

    public function test_can_show_audit_log(): void
    {
        $log = AuditLog::create([
            'module' => 'Branch',
            'action' => 'created',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/audit-logs/{$log->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['module' => 'Branch']);
    }

    public function test_can_update_audit_log(): void
    {
        $log = AuditLog::create([
            'module' => 'Category',
            'action' => 'created',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/audit-logs/{$log->id}", [
                'action' => 'updated',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['action' => 'updated']);
    }

    public function test_can_delete_audit_log(): void
    {
        $log = AuditLog::create([
            'module' => 'TempModule',
            'action' => 'temp_action',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/audit-logs/{$log->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('audit_logs', ['id' => $log->id]);
    }

    public function test_can_get_audit_log_statistics(): void
    {
        AuditLog::create([
            'module' => 'MenuItem',
            'action' => 'created',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/audit-logs/stats');

        $response->assertStatus(200)
            ->assertJsonStructure(['total_logs', 'by_module', 'by_action']);
    }

    public function test_can_get_distinct_audited_modules_list(): void
    {
        AuditLog::create([
            'module' => 'InventoryItem',
            'action' => 'updated',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/audit-logs/modules');

        $response->assertStatus(200)
            ->assertJsonFragment(['InventoryItem']);
    }

    public function test_creating_restaurant_triggers_both_activity_and_audit_logs(): void
    {
        $payload = [
            'name' => 'Dual Log Restaurant',
            'slug' => 'dual-log-restaurant',
            'phone' => '+1555444333',
            'email' => 'duallog@example.com',
            'address' => '789 Audit St',
            'postcode' => '3000',
            'status' => 'active',
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/restaurants', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('activity_logs', [
            'module' => 'Restaurant',
            'action' => 'created',
            'user_id' => $this->adminUser->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'Restaurant',
            'action' => 'created',
            'user_id' => $this->adminUser->id,
        ]);
    }
}
