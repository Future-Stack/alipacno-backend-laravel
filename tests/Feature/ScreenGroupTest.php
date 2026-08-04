<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\DigitalScreen;
use App\Models\Restaurant;
use App\Models\ScreenGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScreenGroupTest extends TestCase
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

    public function test_can_list_screen_groups(): void
    {
        ScreenGroup::create([
            'branch_id' => $this->branch->id,
            'name' => 'Lobby Displays',
            'description' => 'Screens in the main entrance',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/screen-groups');

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Lobby Displays']);
    }

    public function test_can_filter_screen_groups_by_branch_and_search(): void
    {
        ScreenGroup::create([
            'branch_id' => $this->branch->id,
            'name' => 'Menu Boards',
            'description' => 'Front counter menu displays',
        ]);

        ScreenGroup::create([
            'branch_id' => $this->branch->id,
            'name' => 'Outdoor Signage',
            'description' => 'Drive thru boards',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/screen-groups?branch_id={$this->branch->id}&search=Menu");

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Menu Boards'])
            ->assertJsonMissing(['name' => 'Outdoor Signage']);
    }

    public function test_can_create_screen_group_with_assigned_screens(): void
    {
        $screen1 = DigitalScreen::create([
            'branch_id' => $this->branch->id,
            'screen_name' => 'Screen 1',
            'device_uuid' => 'uuid-001',
            'resolution' => '1920x1080',
            'status' => 'online',
        ]);

        $payload = [
            'branch_id' => $this->branch->id,
            'name' => 'Promo Group',
            'description' => 'Special promotion screens',
            'screen_ids' => [$screen1->id],
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/screen-groups', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Promo Group']);

        $this->assertDatabaseHas('screen_groups', ['name' => 'Promo Group']);
        $this->assertDatabaseHas('screen_group_screens', ['screen_id' => $screen1->id]);
    }

    public function test_can_show_screen_group(): void
    {
        $group = ScreenGroup::create([
            'branch_id' => $this->branch->id,
            'name' => 'KDS Screens',
            'description' => 'Kitchen display group',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/screen-groups/{$group->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'KDS Screens']);
    }

    public function test_can_update_screen_group(): void
    {
        $group = ScreenGroup::create([
            'branch_id' => $this->branch->id,
            'name' => 'Old Group Name',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/screen-groups/{$group->id}", [
                'name' => 'Updated Group Name',
                'description' => 'New description',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Updated Group Name']);

        $this->assertDatabaseHas('screen_groups', [
            'id' => $group->id,
            'name' => 'Updated Group Name',
        ]);
    }

    public function test_can_delete_screen_group(): void
    {
        $group = ScreenGroup::create([
            'branch_id' => $this->branch->id,
            'name' => 'Temp Group',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/screen-groups/{$group->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('screen_groups', ['id' => $group->id]);
    }

    public function test_can_sync_screen_group_screens(): void
    {
        $group = ScreenGroup::create([
            'branch_id' => $this->branch->id,
            'name' => 'Sync Group',
        ]);

        $screen = DigitalScreen::create([
            'branch_id' => $this->branch->id,
            'screen_name' => 'Screen 2',
            'device_uuid' => 'uuid-002',
            'resolution' => '1920x1080',
            'status' => 'online',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/screen-groups/{$group->id}/sync-screens", [
                'screen_ids' => [$screen->id],
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['success' => true]);

        $this->assertDatabaseHas('screen_group_screens', [
            'screen_group_id' => $group->id,
            'screen_id' => $screen->id,
        ]);
    }

    public function test_can_assign_screens_without_detaching(): void
    {
        $group = ScreenGroup::create([
            'branch_id' => $this->branch->id,
            'name' => 'Assign Group',
        ]);

        $screen1 = DigitalScreen::create([
            'branch_id' => $this->branch->id,
            'screen_name' => 'Screen A',
            'device_uuid' => 'uuid-A',
            'resolution' => '1920x1080',
            'status' => 'online',
        ]);

        $screen2 = DigitalScreen::create([
            'branch_id' => $this->branch->id,
            'screen_name' => 'Screen B',
            'device_uuid' => 'uuid-B',
            'resolution' => '1920x1080',
            'status' => 'online',
        ]);

        $group->screens()->attach($screen1->id);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/screen-groups/{$group->id}/assign-screens", [
                'screen_ids' => [$screen2->id],
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['success' => true]);

        $this->assertDatabaseHas('screen_group_screens', ['screen_group_id' => $group->id, 'screen_id' => $screen1->id]);
        $this->assertDatabaseHas('screen_group_screens', ['screen_group_id' => $group->id, 'screen_id' => $screen2->id]);
    }
}
