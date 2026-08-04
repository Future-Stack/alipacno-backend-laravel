<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomatedActivityLogTest extends TestCase
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

    public function test_creating_restaurant_automatically_records_activity_log(): void
    {
        $payload = [
            'name' => 'Auto Log Restaurant',
            'slug' => 'auto-log-restaurant',
            'phone' => '+1999888777',
            'email' => 'autolog@example.com',
            'address' => '456 Automation Way',
            'postcode' => '2000',
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
    }

    public function test_updating_restaurant_automatically_records_activity_log(): void
    {
        $restaurant = Restaurant::create([
            'name' => 'Original Name',
            'slug' => 'original-slug',
            'phone' => '+111111111',
            'email' => 'orig@example.com',
            'address' => '123 St',
            'postcode' => '1000',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/restaurants/{$restaurant->id}", [
                'name' => 'Updated Name',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('activity_logs', [
            'module' => 'Restaurant',
            'action' => 'updated',
            'user_id' => $this->adminUser->id,
        ]);
    }

    public function test_deleting_restaurant_automatically_records_activity_log(): void
    {
        $restaurant = Restaurant::create([
            'name' => 'Delete Me Restaurant',
            'slug' => 'delete-me-slug',
            'phone' => '+222222222',
            'email' => 'del@example.com',
            'address' => '123 St',
            'postcode' => '1000',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/restaurants/{$restaurant->id}");

        $response->assertStatus(204);

        $this->assertDatabaseHas('activity_logs', [
            'module' => 'Restaurant',
            'action' => 'deleted',
            'user_id' => $this->adminUser->id,
        ]);
    }
}
