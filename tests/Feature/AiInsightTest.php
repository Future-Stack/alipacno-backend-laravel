<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiInsightTest extends TestCase
{
    public function test_can_fetch_ai_insights_dashboard(): void
    {
        $user = User::first();
        if (!$user) {
            $user = User::factory()->create();
        }

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/ai-insights/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'generated_at',
                'is_ai_powered',
                'kpis' => [
                    'top_sold_product' => ['title', 'name', 'orders', 'growth', 'badge'],
                    'top_ordering_area' => ['title', 'name', 'orders', 'growth', 'badge'],
                    'most_loyal_customer' => ['title', 'name', 'orders', 'growth', 'badge'],
                    'best_campaign' => ['title', 'name', 'reached', 'growth', 'badge'],
                ],
                'ai_hero_banner' => ['headline', 'recommendation'],
                'top_selling_products',
                'top_customers',
                'top_ordering_areas',
                'ai_recommended_campaigns',
            ]);
    }

    public function test_can_refresh_ai_insights_dashboard(): void
    {
        $user = User::first();
        if (!$user) {
            $user = User::factory()->create();
        }

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/ai-insights/refresh');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'AI Insights refreshed successfully',
            ]);
    }
}
