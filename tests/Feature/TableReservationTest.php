<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\TableReservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TableReservationTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Restaurant $restaurant;
    protected RestaurantTable $table;

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

        $this->table = RestaurantTable::create([
            'restaurant_id' => $this->restaurant->id,
            'table_number' => 'T-01',
            'capacity' => 4,
            'status' => 'available',
        ]);
    }

    public function test_can_list_table_reservations(): void
    {
        TableReservation::create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'user_id' => $this->adminUser->id,
            'reservation_date' => '2026-08-15',
            'reservation_time' => '19:00',
            'guest_count' => 4,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/table-reservations');

        $response->assertStatus(200)
            ->assertJsonFragment(['guest_count' => 4]);
    }

    public function test_can_filter_table_reservations_by_restaurant_and_status(): void
    {
        TableReservation::create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'user_id' => $this->adminUser->id,
            'reservation_date' => '2026-08-15',
            'reservation_time' => '19:00',
            'guest_count' => 2,
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/table-reservations?restaurant_id={$this->restaurant->id}&status=confirmed");

        $response->assertStatus(200)
            ->assertJsonFragment(['guest_count' => 2]);
    }

    public function test_can_create_table_reservation(): void
    {
        $payload = [
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'reservation_date' => '2026-08-20',
            'reservation_time' => '20:30',
            'guest_count' => 6,
            'status' => 'pending',
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/table-reservations', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['guest_count' => 6]);

        $this->assertDatabaseHas('table_reservations', [
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'user_id' => $this->adminUser->id,
            'guest_count' => 6,
        ]);
    }

    public function test_can_show_table_reservation(): void
    {
        $reservation = TableReservation::create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'user_id' => $this->adminUser->id,
            'reservation_date' => '2026-08-15',
            'reservation_time' => '18:00',
            'guest_count' => 3,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/table-reservations/{$reservation->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['guest_count' => 3]);
    }

    public function test_can_update_table_reservation(): void
    {
        $reservation = TableReservation::create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'user_id' => $this->adminUser->id,
            'reservation_date' => '2026-08-15',
            'reservation_time' => '18:00',
            'guest_count' => 2,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/table-reservations/{$reservation->id}", [
                'guest_count' => 5,
                'reservation_time' => '19:30',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['guest_count' => 5]);

        $this->assertDatabaseHas('table_reservations', [
            'id' => $reservation->id,
            'guest_count' => 5,
        ]);
    }

    public function test_can_delete_table_reservation(): void
    {
        $reservation = TableReservation::create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'user_id' => $this->adminUser->id,
            'reservation_date' => '2026-08-15',
            'reservation_time' => '18:00',
            'guest_count' => 2,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/table-reservations/{$reservation->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('table_reservations', ['id' => $reservation->id]);
    }

    public function test_can_update_table_reservation_status(): void
    {
        $reservation = TableReservation::create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'user_id' => $this->adminUser->id,
            'reservation_date' => '2026-08-15',
            'reservation_time' => '18:00',
            'guest_count' => 2,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->patchJson("/api/v1/table-reservations/{$reservation->id}/status", [
                'status' => 'confirmed',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['status' => 'confirmed']);

        $this->assertDatabaseHas('table_reservations', [
            'id' => $reservation->id,
            'status' => 'confirmed',
        ]);
    }
}
