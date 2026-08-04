<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\CampaignStatistic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignStatisticTest extends TestCase
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
            'name' => 'Autumn Loyalty Drive',
            'type' => 'email',
            'message' => 'Autumn promo content',
            'status' => 'running',
        ]);
    }

    public function test_can_list_campaign_statistics(): void
    {
        CampaignStatistic::create([
            'campaign_id' => $this->campaign->id,
            'sent' => 100,
            'delivered' => 95,
            'opened' => 40,
            'clicked' => 15,
            'converted' => 5,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/campaign-statistics');

        $response->assertStatus(200)
            ->assertJsonFragment(['sent' => 100]);
    }

    public function test_can_filter_campaign_statistics_by_campaign_and_search(): void
    {
        CampaignStatistic::create([
            'campaign_id' => $this->campaign->id,
            'sent' => 200,
            'delivered' => 190,
            'opened' => 80,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/campaign-statistics?campaign_id={$this->campaign->id}&search=Autumn");

        $response->assertStatus(200)
            ->assertJsonFragment(['sent' => 200]);
    }

    public function test_can_create_campaign_statistic(): void
    {
        $payload = [
            'campaign_id' => $this->campaign->id,
            'sent' => 50,
            'delivered' => 48,
            'opened' => 20,
            'clicked' => 10,
            'converted' => 2,
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/campaign-statistics', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['sent' => 50]);

        $this->assertDatabaseHas('campaign_statistics', [
            'campaign_id' => $this->campaign->id,
            'sent' => 50,
        ]);
    }

    public function test_can_show_campaign_statistic_with_rates(): void
    {
        $stat = CampaignStatistic::create([
            'campaign_id' => $this->campaign->id,
            'sent' => 100,
            'delivered' => 100,
            'opened' => 50,
            'clicked' => 25,
            'converted' => 10,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/campaign-statistics/{$stat->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['open_rate' => 50])
            ->assertJsonFragment(['click_rate' => 25]);
    }

    public function test_can_update_campaign_statistic(): void
    {
        $stat = CampaignStatistic::create([
            'campaign_id' => $this->campaign->id,
            'sent' => 10,
            'delivered' => 10,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/campaign-statistics/{$stat->id}", [
                'opened' => 5,
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['opened' => 5]);

        $this->assertDatabaseHas('campaign_statistics', [
            'id' => $stat->id,
            'opened' => 5,
        ]);
    }

    public function test_can_delete_campaign_statistic(): void
    {
        $stat = CampaignStatistic::create([
            'campaign_id' => $this->campaign->id,
            'sent' => 5,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/campaign-statistics/{$stat->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('campaign_statistics', ['id' => $stat->id]);
    }

    public function test_can_get_statistics_by_campaign(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/campaign-statistics/campaign/{$this->campaign->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['campaign_id' => $this->campaign->id]);
    }

    public function test_can_recalculate_campaign_statistics(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        CampaignRecipient::create([
            'campaign_id' => $this->campaign->id,
            'user_id' => $user1->id,
            'status' => 'sent',
        ]);

        CampaignRecipient::create([
            'campaign_id' => $this->campaign->id,
            'user_id' => $user2->id,
            'status' => 'sent',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/campaign-statistics/campaign/{$this->campaign->id}/recalculate");

        $response->assertStatus(200)
            ->assertJsonFragment(['sent' => 2]);

        $this->assertDatabaseHas('campaign_statistics', [
            'campaign_id' => $this->campaign->id,
            'sent' => 2,
        ]);
    }
}
