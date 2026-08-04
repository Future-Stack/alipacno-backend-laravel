<?php

namespace Tests\Feature;

use App\Models\CustomerSegment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerSegmentTest extends TestCase
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

    public function test_can_list_customer_segments(): void
    {
        CustomerSegment::create([
            'name' => 'High Value Customers',
            'conditions' => ['user_type' => 'customer', 'status' => 'active'],
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/customer-segments');

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'High Value Customers']);
    }

    public function test_can_filter_customer_segments_by_search(): void
    {
        CustomerSegment::create([
            'name' => 'VIP Members',
            'conditions' => ['status' => 'active'],
        ]);

        CustomerSegment::create([
            'name' => 'Churned Users',
            'conditions' => ['status' => 'inactive'],
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/customer-segments?search=VIP');

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'VIP Members'])
            ->assertJsonMissing(['name' => 'Churned Users']);
    }

    public function test_can_create_customer_segment(): void
    {
        $payload = [
            'name' => 'New Signups',
            'conditions' => ['user_type' => 'customer'],
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/customer-segments', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'New Signups']);

        $this->assertDatabaseHas('customer_segments', [
            'name' => 'New Signups',
        ]);
    }

    public function test_can_show_customer_segment(): void
    {
        $segment = CustomerSegment::create([
            'name' => 'Inactive Customers',
            'conditions' => ['status' => 'inactive'],
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/customer-segments/{$segment->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Inactive Customers']);
    }

    public function test_can_update_customer_segment(): void
    {
        $segment = CustomerSegment::create([
            'name' => 'Old Segment Name',
            'conditions' => ['user_type' => 'customer'],
        ]);

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/customer-segments/{$segment->id}", [
                'name' => 'Updated Segment Name',
                'conditions' => ['user_type' => 'customer', 'status' => 'active'],
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Updated Segment Name']);

        $this->assertDatabaseHas('customer_segments', [
            'id' => $segment->id,
            'name' => 'Updated Segment Name',
        ]);
    }

    public function test_can_delete_customer_segment(): void
    {
        $segment = CustomerSegment::create([
            'name' => 'Temp Segment',
            'conditions' => [],
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/customer-segments/{$segment->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('customer_segments', ['id' => $segment->id]);
    }

    public function test_can_get_matching_customers_for_segment(): void
    {
        User::factory()->create([
            'user_type' => 'customer',
            'status' => 'active',
            'name' => 'John Doe',
        ]);

        User::factory()->create([
            'user_type' => 'staff',
            'status' => 'active',
            'name' => 'Staff Jane',
        ]);

        $segment = CustomerSegment::create([
            'name' => 'Active Customers',
            'conditions' => ['user_type' => 'customer', 'status' => 'active'],
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/customer-segments/{$segment->id}/customers");

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'John Doe'])
            ->assertJsonMissing(['name' => 'Staff Jane']);
    }
}
