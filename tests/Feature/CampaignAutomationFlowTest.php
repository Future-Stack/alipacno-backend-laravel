<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignAutomationFlow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignAutomationFlowTest extends TestCase
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
            'name' => 'Summer Sale Campaign',
            'type' => 'email',
            'message' => 'Special summer discount for loyal customers!',
            'status' => 'draft',
        ]);
    }

    public function test_can_list_campaign_automation_flows(): void
    {
        CampaignAutomationFlow::create([
            'campaign_id' => $this->campaign->id,
            'trigger' => 'user_signup',
            'condition' => 'is_first_purchase',
            'action' => 'send_discount_coupon',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/campaign-automation-flows');

        $response->assertStatus(200)
            ->assertJsonFragment(['trigger' => 'user_signup']);
    }

    public function test_can_filter_campaign_automation_flows_by_campaign_status_and_search(): void
    {
        CampaignAutomationFlow::create([
            'campaign_id' => $this->campaign->id,
            'trigger' => 'cart_abandoned',
            'condition' => 'cart_total > 50',
            'action' => 'send_reminder_email',
            'status' => 'active',
        ]);

        CampaignAutomationFlow::create([
            'campaign_id' => $this->campaign->id,
            'trigger' => 'user_birthday',
            'action' => 'send_gift_voucher',
            'status' => 'inactive',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/campaign-automation-flows?campaign_id={$this->campaign->id}&status=active&search=abandoned");

        $response->assertStatus(200)
            ->assertJsonFragment(['trigger' => 'cart_abandoned'])
            ->assertJsonMissing(['trigger' => 'user_birthday']);
    }

    public function test_can_create_campaign_automation_flow(): void
    {
        $payload = [
            'campaign_id' => $this->campaign->id,
            'trigger' => 'order_placed',
            'condition' => 'order_amount > 100',
            'action' => 'grant_vip_points',
            'status' => 'active',
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/campaign-automation-flows', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['trigger' => 'order_placed']);

        $this->assertDatabaseHas('campaign_automation_flows', [
            'campaign_id' => $this->campaign->id,
            'trigger' => 'order_placed',
        ]);
    }

    public function test_can_show_campaign_automation_flow(): void
    {
        $flow = CampaignAutomationFlow::create([
            'campaign_id' => $this->campaign->id,
            'trigger' => 'feedback_submitted',
            'action' => 'thank_you_sms',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/campaign-automation-flows/{$flow->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['trigger' => 'feedback_submitted']);
    }

    public function test_can_update_campaign_automation_flow(): void
    {
        $flow = CampaignAutomationFlow::create([
            'campaign_id' => $this->campaign->id,
            'trigger' => 'old_trigger',
            'action' => 'old_action',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/campaign-automation-flows/{$flow->id}", [
                'trigger' => 'new_trigger',
                'action' => 'new_action',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['trigger' => 'new_trigger']);

        $this->assertDatabaseHas('campaign_automation_flows', [
            'id' => $flow->id,
            'trigger' => 'new_trigger',
        ]);
    }

    public function test_can_delete_campaign_automation_flow(): void
    {
        $flow = CampaignAutomationFlow::create([
            'campaign_id' => $this->campaign->id,
            'trigger' => 'temp_trigger',
            'action' => 'temp_action',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/campaign-automation-flows/{$flow->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('campaign_automation_flows', ['id' => $flow->id]);
    }

    public function test_can_toggle_campaign_automation_flow_status(): void
    {
        $flow = CampaignAutomationFlow::create([
            'campaign_id' => $this->campaign->id,
            'trigger' => 'status_trigger',
            'action' => 'status_action',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->patchJson("/api/v1/campaign-automation-flows/{$flow->id}/toggle-status");

        $response->assertStatus(200)
            ->assertJsonFragment(['status' => 'inactive']);

        $this->assertDatabaseHas('campaign_automation_flows', [
            'id' => $flow->id,
            'status' => 'inactive',
        ]);
    }
}
