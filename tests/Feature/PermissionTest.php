<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionTest extends TestCase
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

    public function test_can_list_permissions(): void
    {
        Permission::create(['name' => 'create_users', 'module' => 'users']);
        Permission::create(['name' => 'edit_users', 'module' => 'users']);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/permissions');

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'create_users']);
    }

    public function test_can_filter_permissions_by_module(): void
    {
        Permission::create(['name' => 'create_orders', 'module' => 'orders']);
        Permission::create(['name' => 'view_reports', 'module' => 'reports']);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/permissions?module=orders');

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'create_orders'])
            ->assertJsonMissing(['name' => 'view_reports']);
    }

    public function test_can_group_permissions_by_module(): void
    {
        Permission::create(['name' => 'create_orders', 'module' => 'orders']);
        Permission::create(['name' => 'delete_orders', 'module' => 'orders']);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/permissions?grouped=true');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['orders']]);
    }

    public function test_can_create_permission(): void
    {
        $payload = [
            'name' => 'manage_inventory',
            'module' => 'inventory',
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/permissions', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'manage_inventory', 'module' => 'inventory']);

        $this->assertDatabaseHas('permissions', [
            'name' => 'manage_inventory',
            'module' => 'inventory',
        ]);
    }

    public function test_can_show_permission(): void
    {
        $permission = Permission::create([
            'name' => 'view_logs',
            'module' => 'system',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/permissions/{$permission->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'view_logs']);
    }

    public function test_can_update_permission(): void
    {
        $permission = Permission::create([
            'name' => 'view_analytics',
            'module' => 'reports',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/permissions/{$permission->id}", [
                'name' => 'view_advanced_analytics',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'view_advanced_analytics']);

        $this->assertDatabaseHas('permissions', [
            'id' => $permission->id,
            'name' => 'view_advanced_analytics',
        ]);
    }

    public function test_can_delete_permission(): void
    {
        $permission = Permission::create([
            'name' => 'temp_permission',
            'module' => 'testing',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/permissions/{$permission->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('permissions', ['id' => $permission->id]);
    }

    public function test_can_list_distinct_modules(): void
    {
        Permission::create(['name' => 'perm1', 'module' => 'users']);
        Permission::create(['name' => 'perm2', 'module' => 'orders']);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/permissions/modules');

        $response->assertStatus(200)
            ->assertJson(['data' => ['orders', 'users']]);
    }

    public function test_can_bulk_create_permissions(): void
    {
        $payload = [
            'module' => 'kitchen',
            'permissions' => [
                'kitchen.view_orders',
                'kitchen.update_status',
                'kitchen.print_ticket',
            ],
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/permissions/bulk', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['success' => true]);

        $this->assertDatabaseHas('permissions', ['name' => 'kitchen.view_orders', 'module' => 'kitchen']);
        $this->assertDatabaseHas('permissions', ['name' => 'kitchen.update_status', 'module' => 'kitchen']);
        $this->assertDatabaseHas('permissions', ['name' => 'kitchen.print_ticket', 'module' => 'kitchen']);
    }
}
