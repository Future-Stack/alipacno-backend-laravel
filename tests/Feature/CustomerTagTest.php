<?php

namespace Tests\Feature;

use App\Models\CustomerTag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerTagTest extends TestCase
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

    public function test_can_list_customer_tags(): void
    {
        CustomerTag::create([
            'name' => 'VIP Customer',
            'color' => '#FFD700',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/customer-tags');

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'VIP Customer']);
    }

    public function test_can_filter_customer_tags_by_search(): void
    {
        CustomerTag::create([
            'name' => 'High Spender',
            'color' => '#00FF00',
        ]);

        CustomerTag::create([
            'name' => 'Inactive User',
            'color' => '#888888',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/customer-tags?search=Spender');

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'High Spender'])
            ->assertJsonMissing(['name' => 'Inactive User']);
    }

    public function test_can_create_customer_tag(): void
    {
        $payload = [
            'name' => 'Loyal Member',
            'color' => '#0000FF',
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/customer-tags', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Loyal Member']);

        $this->assertDatabaseHas('customer_tags', [
            'name' => 'Loyal Member',
            'color' => '#0000FF',
        ]);
    }

    public function test_can_show_customer_tag(): void
    {
        $tag = CustomerTag::create([
            'name' => 'Frequent Diner',
            'color' => '#FFA500',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/customer-tags/{$tag->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Frequent Diner']);
    }

    public function test_can_update_customer_tag(): void
    {
        $tag = CustomerTag::create([
            'name' => 'Old Tag Name',
            'color' => '#111111',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/customer-tags/{$tag->id}", [
                'name' => 'Updated Tag Name',
                'color' => '#999999',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Updated Tag Name']);

        $this->assertDatabaseHas('customer_tags', [
            'id' => $tag->id,
            'name' => 'Updated Tag Name',
            'color' => '#999999',
        ]);
    }

    public function test_can_delete_customer_tag(): void
    {
        $tag = CustomerTag::create([
            'name' => 'Temp Tag',
            'color' => '#333333',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/customer-tags/{$tag->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('customer_tags', ['id' => $tag->id]);
    }

    public function test_can_bulk_create_customer_tags(): void
    {
        $payload = [
            'tags' => [
                ['name' => 'Bulk Tag 1', 'color' => '#112233'],
                ['name' => 'Bulk Tag 2', 'color' => '#445566'],
            ],
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/customer-tags/bulk', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['success' => true]);

        $this->assertDatabaseHas('customer_tags', ['name' => 'Bulk Tag 1']);
        $this->assertDatabaseHas('customer_tags', ['name' => 'Bulk Tag 2']);
    }
}
