<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignTest extends TestCase
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

    public function test_can_list_campaigns(): void
    {
        Campaign::create([
            'name' => 'Newsletter May',
            'type' => 'email',
            'message' => 'Monthly updates and special deals.',
            'status' => 'draft',
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/campaigns');

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Newsletter May']);
    }

    public function test_can_filter_campaigns_by_type_status_and_search(): void
    {
        Campaign::create([
            'name' => 'Black Friday SMS',
            'type' => 'sms',
            'message' => 'Huge discounts today!',
            'status' => 'scheduled',
            'created_by' => $this->adminUser->id,
        ]);

        Campaign::create([
            'name' => 'Weekly Push',
            'type' => 'push',
            'message' => 'Check out our new menu items.',
            'status' => 'draft',
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/campaigns?type=sms&search=Black');

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Black Friday SMS'])
            ->assertJsonMissing(['name' => 'Weekly Push']);
    }

    public function test_can_create_campaign(): void
    {
        $payload = [
            'name' => 'Holiday Offer',
            'type' => 'email',
            'subject' => 'Season Greetings & 20% Off',
            'message' => 'Happy holidays! Enjoy 20% off your next order.',
            'status' => 'draft',
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/campaigns', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Holiday Offer']);

        $this->assertDatabaseHas('campaigns', [
            'name' => 'Holiday Offer',
            'created_by' => $this->adminUser->id,
        ]);
    }

    public function test_can_show_campaign(): void
    {
        $campaign = Campaign::create([
            'name' => 'Loyalty Reward Email',
            'type' => 'email',
            'message' => 'You earned 500 bonus points.',
            'status' => 'draft',
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/campaigns/{$campaign->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Loyalty Reward Email']);
    }

    public function test_can_update_campaign(): void
    {
        $campaign = Campaign::create([
            'name' => 'Old Campaign Title',
            'type' => 'email',
            'message' => 'Old message text',
            'status' => 'draft',
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/campaigns/{$campaign->id}", [
                'name' => 'Updated Campaign Title',
                'message' => 'New message content',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Updated Campaign Title']);

        $this->assertDatabaseHas('campaigns', [
            'id' => $campaign->id,
            'name' => 'Updated Campaign Title',
        ]);
    }

    public function test_can_delete_campaign(): void
    {
        $campaign = Campaign::create([
            'name' => 'Temp Campaign',
            'type' => 'sms',
            'message' => 'Temporary text',
            'status' => 'draft',
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/campaigns/{$campaign->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('campaigns', ['id' => $campaign->id]);
    }

    public function test_can_send_campaign(): void
    {
        $campaign = Campaign::create([
            'name' => 'Flash Sale',
            'type' => 'email',
            'message' => 'Flash sale is now live!',
            'status' => 'draft',
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/campaigns/{$campaign->id}/send");

        $response->assertStatus(200)
            ->assertJsonFragment(['status' => 'running']);

        $this->assertDatabaseHas('campaigns', [
            'id' => $campaign->id,
            'status' => 'running',
        ]);

        $this->assertDatabaseHas('campaign_statistics', [
            'campaign_id' => $campaign->id,
        ]);
    }

    public function test_can_cancel_campaign(): void
    {
        $campaign = Campaign::create([
            'name' => 'Scheduled Promo',
            'type' => 'email',
            'message' => 'Upcoming promo',
            'status' => 'scheduled',
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/campaigns/{$campaign->id}/cancel");

        $response->assertStatus(200)
            ->assertJsonFragment(['status' => 'cancelled']);

        $this->assertDatabaseHas('campaigns', [
            'id' => $campaign->id,
            'status' => 'cancelled',
        ]);
    }
}
