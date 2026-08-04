<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignMessageTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Campaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'user_type' => 'super_admin',
        ]);

        $this->campaign = Campaign::create([
            'name' => 'Flash Promotion',
            'type' => 'email',
            'message' => 'Flash sale content',
            'status' => 'draft',
        ]);
    }

    public function test_can_list_campaign_messages(): void
    {
        CampaignMessage::create([
            'campaign_id' => $this->campaign->id,
            'channel' => 'email',
            'message' => 'Hello customer, 50% discount on all burgers!',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/campaign-messages');

        $response->assertStatus(200)
            ->assertJsonFragment(['channel' => 'email']);
    }

    public function test_can_filter_campaign_messages_by_campaign_channel_and_search(): void
    {
        CampaignMessage::create([
            'campaign_id' => $this->campaign->id,
            'channel' => 'sms',
            'message' => 'SMS: Free delivery code: FREEDEL',
        ]);

        CampaignMessage::create([
            'campaign_id' => $this->campaign->id,
            'channel' => 'push',
            'message' => 'Push: Check your app notifications',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/campaign-messages?campaign_id={$this->campaign->id}&channel=sms&search=FREEDEL");

        $response->assertStatus(200)
            ->assertJsonFragment(['channel' => 'sms'])
            ->assertJsonMissing(['channel' => 'push']);
    }

    public function test_can_create_campaign_message(): void
    {
        $payload = [
            'campaign_id' => $this->campaign->id,
            'channel' => 'whatsapp',
            'message' => 'WhatsApp VIP exclusive voucher link.',
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/campaign-messages', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['channel' => 'whatsapp']);

        $this->assertDatabaseHas('campaign_messages', [
            'campaign_id' => $this->campaign->id,
            'channel' => 'whatsapp',
        ]);
    }

    public function test_can_show_campaign_message(): void
    {
        $msg = CampaignMessage::create([
            'campaign_id' => $this->campaign->id,
            'channel' => 'email',
            'message' => 'Sample body',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/campaign-messages/{$msg->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Sample body']);
    }

    public function test_can_update_campaign_message(): void
    {
        $msg = CampaignMessage::create([
            'campaign_id' => $this->campaign->id,
            'channel' => 'email',
            'message' => 'Original text',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/campaign-messages/{$msg->id}", [
                'message' => 'Updated body text',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Updated body text']);

        $this->assertDatabaseHas('campaign_messages', [
            'id' => $msg->id,
            'message' => 'Updated body text',
        ]);
    }

    public function test_can_delete_campaign_message(): void
    {
        $msg = CampaignMessage::create([
            'campaign_id' => $this->campaign->id,
            'channel' => 'sms',
            'message' => 'Temp message',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/campaign-messages/{$msg->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('campaign_messages', ['id' => $msg->id]);
    }

    public function test_can_bulk_create_campaign_messages(): void
    {
        $payload = [
            'campaign_id' => $this->campaign->id,
            'messages' => [
                ['channel' => 'email', 'message' => 'Email template'],
                ['channel' => 'sms', 'message' => 'SMS template'],
            ],
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/campaign-messages/bulk', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['success' => true]);

        $this->assertDatabaseHas('campaign_messages', [
            'campaign_id' => $this->campaign->id,
            'channel' => 'email',
        ]);

        $this->assertDatabaseHas('campaign_messages', [
            'campaign_id' => $this->campaign->id,
            'channel' => 'sms',
        ]);
    }
}
