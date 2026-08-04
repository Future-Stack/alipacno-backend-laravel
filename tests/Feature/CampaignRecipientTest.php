<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignRecipientTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $customerUser;
    protected Campaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'user_type' => 'super_admin',
        ]);

        $this->customerUser = User::factory()->create([
            'name' => 'Alice Recipient',
            'email' => 'alice@example.com',
            'user_type' => 'customer',
        ]);

        $this->campaign = Campaign::create([
            'name' => 'Summer Special Offer',
            'type' => 'email',
            'message' => 'Summer promo content',
            'status' => 'draft',
        ]);
    }

    public function test_can_list_campaign_recipients(): void
    {
        CampaignRecipient::create([
            'campaign_id' => $this->campaign->id,
            'user_id' => $this->customerUser->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/campaign-recipients');

        $response->assertStatus(200)
            ->assertJsonFragment(['user_id' => $this->customerUser->id]);
    }

    public function test_can_filter_campaign_recipients_by_campaign_status_and_search(): void
    {
        $anotherCustomer = User::factory()->create([
            'name' => 'Bob Target',
            'email' => 'bob@example.com',
        ]);

        CampaignRecipient::create([
            'campaign_id' => $this->campaign->id,
            'user_id' => $this->customerUser->id,
            'status' => 'sent',
        ]);

        CampaignRecipient::create([
            'campaign_id' => $this->campaign->id,
            'user_id' => $anotherCustomer->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/campaign-recipients?campaign_id={$this->campaign->id}&status=sent&search=Alice");

        $response->assertStatus(200)
            ->assertJsonFragment(['user_id' => $this->customerUser->id])
            ->assertJsonMissing(['user_id' => $anotherCustomer->id]);
    }

    public function test_can_create_campaign_recipient(): void
    {
        $payload = [
            'campaign_id' => $this->campaign->id,
            'user_id' => $this->customerUser->id,
            'status' => 'pending',
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/campaign-recipients', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['user_id' => $this->customerUser->id]);

        $this->assertDatabaseHas('campaign_recipients', [
            'campaign_id' => $this->campaign->id,
            'user_id' => $this->customerUser->id,
        ]);
    }

    public function test_can_show_campaign_recipient(): void
    {
        $recipient = CampaignRecipient::create([
            'campaign_id' => $this->campaign->id,
            'user_id' => $this->customerUser->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/campaign-recipients/{$recipient->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['id' => $recipient->id]);
    }

    public function test_can_update_campaign_recipient(): void
    {
        $recipient = CampaignRecipient::create([
            'campaign_id' => $this->campaign->id,
            'user_id' => $this->customerUser->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/campaign-recipients/{$recipient->id}", [
                'status' => 'failed',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['status' => 'failed']);

        $this->assertDatabaseHas('campaign_recipients', [
            'id' => $recipient->id,
            'status' => 'failed',
        ]);
    }

    public function test_can_delete_campaign_recipient(): void
    {
        $recipient = CampaignRecipient::create([
            'campaign_id' => $this->campaign->id,
            'user_id' => $this->customerUser->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/campaign-recipients/{$recipient->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('campaign_recipients', ['id' => $recipient->id]);
    }

    public function test_can_bulk_create_campaign_recipients(): void
    {
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        $payload = [
            'campaign_id' => $this->campaign->id,
            'user_ids' => [$user2->id, $user3->id],
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/campaign-recipients/bulk', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['success' => true]);

        $this->assertDatabaseHas('campaign_recipients', [
            'campaign_id' => $this->campaign->id,
            'user_id' => $user2->id,
        ]);

        $this->assertDatabaseHas('campaign_recipients', [
            'campaign_id' => $this->campaign->id,
            'user_id' => $user3->id,
        ]);
    }

    public function test_can_mark_campaign_recipient_as_sent(): void
    {
        $recipient = CampaignRecipient::create([
            'campaign_id' => $this->campaign->id,
            'user_id' => $this->customerUser->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->patchJson("/api/v1/campaign-recipients/{$recipient->id}/mark-sent");

        $response->assertStatus(200)
            ->assertJsonFragment(['status' => 'sent']);

        $this->assertDatabaseHas('campaign_recipients', [
            'id' => $recipient->id,
            'status' => 'sent',
        ]);
    }
}
