<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleTest extends TestCase
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

    public function test_can_list_roles(): void
    {
        Role::create(['name' => 'Manager', 'description' => 'Branch Manager']);
        Role::create(['name' => 'Cashier', 'description' => 'POS Operator']);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/roles');

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Manager'])
            ->assertJsonFragment(['name' => 'Cashier']);
    }

    public function test_can_create_role_with_permissions(): void
    {
        $perm1 = Permission::create(['name' => 'create_orders', 'module' => 'orders']);
        $perm2 = Permission::create(['name' => 'view_reports', 'module' => 'reports']);

        $payload = [
            'name' => 'Supervisor',
            'description' => 'Shift Supervisor',
            'permissions' => [$perm1->id, $perm2->id],
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/roles', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Supervisor']);

        $this->assertDatabaseHas('roles', ['name' => 'Supervisor']);
        $this->assertDatabaseHas('role_permissions', ['permission_id' => $perm1->id]);
    }

    public function test_can_show_role(): void
    {
        $role = Role::create(['name' => 'Inventory Manager', 'description' => 'Stock controller']);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/roles/{$role->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Inventory Manager']);
    }

    public function test_can_update_role(): void
    {
        $role = Role::create(['name' => 'Assistant Manager']);

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/roles/{$role->id}", [
                'name' => 'Assistant Branch Manager',
                'description' => 'Updated role description',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Assistant Branch Manager']);

        $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => 'Assistant Branch Manager']);
    }

    public function test_can_delete_role_without_assigned_users(): void
    {
        $role = Role::create(['name' => 'Temporary Role']);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/roles/{$role->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_cannot_delete_role_with_assigned_users(): void
    {
        $role = Role::create(['name' => 'Active Role']);
        User::factory()->create([
            'role_id' => $role->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/roles/{$role->id}");

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Cannot delete role because it is currently assigned to one or more users.']);
    }

    public function test_can_sync_role_permissions(): void
    {
        $role = Role::create(['name' => 'Chef']);
        $perm1 = Permission::create(['name' => 'view_kitchen', 'module' => 'kitchen']);
        $perm2 = Permission::create(['name' => 'cook_items', 'module' => 'kitchen']);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/roles/{$role->id}/sync-permissions", [
                'permissions' => [$perm1->id, $perm2->id],
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['success' => true]);

        $this->assertDatabaseHas('role_permissions', ['role_id' => $role->id, 'permission_id' => $perm1->id]);
        $this->assertDatabaseHas('role_permissions', ['role_id' => $role->id, 'permission_id' => $perm2->id]);
    }

    public function test_can_assign_role_permissions_without_detaching(): void
    {
        $role = Role::create(['name' => 'Waiter']);
        $perm1 = Permission::create(['name' => 'take_order', 'module' => 'pos']);
        $perm2 = Permission::create(['name' => 'serve_table', 'module' => 'pos']);

        $role->permissions()->attach($perm1->id);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/roles/{$role->id}/assign-permissions", [
                'permissions' => [$perm2->id],
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['success' => true]);

        $this->assertDatabaseHas('role_permissions', ['role_id' => $role->id, 'permission_id' => $perm1->id]);
        $this->assertDatabaseHas('role_permissions', ['role_id' => $role->id, 'permission_id' => $perm2->id]);
    }
}
