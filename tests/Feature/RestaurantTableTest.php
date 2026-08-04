<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestaurantTableTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Restaurant $restaurant;
    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'user_type' => 'super_admin',
        ]);

        $this->restaurant = Restaurant::create([
            'name' => 'Main Restaurant',
            'slug' => 'main-restaurant',
            'phone' => '1234567890',
            'email' => 'restaurant@example.com',
            'address' => '123 Main St',
            'postcode' => '12345',
            'status' => 'active',
        ]);

        $this->branch = Branch::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Downtown Branch',
            'status' => 'active',
        ]);
    }

    public function test_can_list_restaurant_tables(): void
    {
        RestaurantTable::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'table_number' => 'T-01',
            'capacity' => 4,
            'status' => 'available',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/restaurant-tables');

        $response->assertStatus(200)
            ->assertJsonFragment(['table_number' => 'T-01']);
    }

    public function test_can_filter_restaurant_tables_by_branch_status_and_capacity(): void
    {
        RestaurantTable::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'table_number' => 'T-02',
            'capacity' => 6,
            'status' => 'available',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/restaurant-tables?branch_id={$this->branch->id}&status=available&min_capacity=4");

        $response->assertStatus(200)
            ->assertJsonFragment(['table_number' => 'T-02']);
    }

    public function test_can_create_restaurant_table(): void
    {
        $payload = [
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'table_number' => 'T-03',
            'capacity' => 2,
            'status' => 'available',
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/restaurant-tables', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['table_number' => 'T-03']);

        $this->assertDatabaseHas('restaurant_tables', [
            'table_number' => 'T-03',
            'capacity' => 2,
        ]);
    }

    public function test_can_show_restaurant_table(): void
    {
        $table = RestaurantTable::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'table_number' => 'T-04',
            'capacity' => 4,
            'status' => 'available',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/restaurant-tables/{$table->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['table_number' => 'T-04']);
    }

    public function test_can_update_restaurant_table(): void
    {
        $table = RestaurantTable::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'table_number' => 'T-05',
            'capacity' => 4,
            'status' => 'available',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/restaurant-tables/{$table->id}", [
                'capacity' => 8,
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['capacity' => 8]);

        $this->assertDatabaseHas('restaurant_tables', [
            'id' => $table->id,
            'capacity' => 8,
        ]);
    }

    public function test_can_delete_restaurant_table(): void
    {
        $table = RestaurantTable::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'table_number' => 'T-06',
            'capacity' => 2,
            'status' => 'available',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/restaurant-tables/{$table->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('restaurant_tables', ['id' => $table->id]);
    }

    public function test_can_update_table_status(): void
    {
        $table = RestaurantTable::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'table_number' => 'T-07',
            'capacity' => 4,
            'status' => 'available',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->patchJson("/api/v1/restaurant-tables/{$table->id}/status", [
                'status' => 'occupied',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['status' => 'occupied']);
    }

    public function test_can_generate_qr_code(): void
    {
        $table = RestaurantTable::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'table_number' => 'T-08',
            'capacity' => 4,
            'status' => 'available',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/restaurant-tables/{$table->id}/qr-code");

        $response->assertStatus(200)
            ->assertJsonFragment(['success' => true]);
    }
}
