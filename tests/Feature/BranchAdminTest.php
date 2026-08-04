<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchAdmin;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchAdminTest extends TestCase
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

    public function test_can_list_branch_admins(): void
    {
        BranchAdmin::create([
            'branch_id' => $this->branch->id,
            'name' => 'Alex Manager',
            'email' => 'alex@branch.com',
            'password' => 'secret123',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/branch-admins');

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Alex Manager']);
    }

    public function test_can_filter_branch_admins_by_branch_and_search(): void
    {
        BranchAdmin::create([
            'branch_id' => $this->branch->id,
            'name' => 'Sarah Admin',
            'email' => 'sarah@branch.com',
            'password' => 'secret123',
            'status' => 'active',
        ]);

        BranchAdmin::create([
            'branch_id' => $this->branch->id,
            'name' => 'Bob Supervisor',
            'email' => 'bob@branch.com',
            'password' => 'secret123',
            'status' => 'inactive',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/branch-admins?branch_id={$this->branch->id}&search=Sarah");

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Sarah Admin'])
            ->assertJsonMissing(['name' => 'Bob Supervisor']);
    }

    public function test_can_create_branch_admin(): void
    {
        $payload = [
            'branch_id' => $this->branch->id,
            'name' => 'Carl Lead',
            'email' => 'carl@branch.com',
            'phone' => '1234987650',
            'password' => 'password123',
            'status' => 'active',
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/branch-admins', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Carl Lead']);

        $this->assertDatabaseHas('branch_admins', [
            'branch_id' => $this->branch->id,
            'email' => 'carl@branch.com',
        ]);
    }

    public function test_can_show_branch_admin(): void
    {
        $admin = BranchAdmin::create([
            'branch_id' => $this->branch->id,
            'name' => 'Diana Director',
            'email' => 'diana@branch.com',
            'password' => 'secret123',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/branch-admins/{$admin->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Diana Director']);
    }

    public function test_can_update_branch_admin(): void
    {
        $admin = BranchAdmin::create([
            'branch_id' => $this->branch->id,
            'name' => 'Old Name',
            'email' => 'old@branch.com',
            'password' => 'secret123',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/branch-admins/{$admin->id}", [
                'name' => 'Updated Name',
                'email' => 'updated@branch.com',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Updated Name']);

        $this->assertDatabaseHas('branch_admins', [
            'id' => $admin->id,
            'name' => 'Updated Name',
            'email' => 'updated@branch.com',
        ]);
    }

    public function test_can_delete_branch_admin(): void
    {
        $admin = BranchAdmin::create([
            'branch_id' => $this->branch->id,
            'name' => 'Temp Admin',
            'email' => 'temp@branch.com',
            'password' => 'secret123',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/branch-admins/{$admin->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('branch_admins', ['id' => $admin->id]);
    }

    public function test_can_toggle_branch_admin_status(): void
    {
        $admin = BranchAdmin::create([
            'branch_id' => $this->branch->id,
            'name' => 'Status Admin',
            'email' => 'status@branch.com',
            'password' => 'secret123',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->patchJson("/api/v1/branch-admins/{$admin->id}/toggle-status");

        $response->assertStatus(200)
            ->assertJsonFragment(['status' => 'inactive']);

        $this->assertDatabaseHas('branch_admins', [
            'id' => $admin->id,
            'status' => 'inactive',
        ]);
    }
}
