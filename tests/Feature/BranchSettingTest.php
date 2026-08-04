<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchSettingTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'user_type' => 'super_admin',
        ]);

        $restaurant = Restaurant::create([
            'name' => 'Main Restaurant',
            'slug' => 'main-restaurant',
            'phone' => '1234567890',
            'email' => 'restaurant@example.com',
            'address' => '123 Main St',
            'postcode' => '12345',
            'status' => 'active',
        ]);

        $this->branch = Branch::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Downtown Branch',
            'status' => 'active',
        ]);
    }

    public function test_can_list_branch_settings(): void
    {
        BranchSetting::create([
            'branch_id' => $this->branch->id,
            'tax_rate' => 10.00,
            'minimum_order' => 15.00,
            'currency' => 'GBP',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/branch-settings');

        $response->assertStatus(200)
            ->assertJsonFragment(['currency' => 'GBP']);
    }

    public function test_can_filter_branch_settings_by_branch(): void
    {
        BranchSetting::create([
            'branch_id' => $this->branch->id,
            'tax_rate' => 5.00,
            'minimum_order' => 20.00,
            'currency' => 'EUR',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/branch-settings?branch_id={$this->branch->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['currency' => 'EUR']);
    }

    public function test_can_create_branch_setting(): void
    {
        $payload = [
            'branch_id' => $this->branch->id,
            'tax_rate' => 7.50,
            'minimum_order' => 25.00,
            'delivery_radius' => 10.00,
            'opening_time' => '09:00',
            'closing_time' => '22:00',
            'currency' => 'USD',
            'timezone' => 'America/New_York',
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/branch-settings', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['currency' => 'USD']);

        $this->assertDatabaseHas('branch_settings', [
            'branch_id' => $this->branch->id,
            'currency' => 'USD',
        ]);
    }

    public function test_can_show_branch_setting(): void
    {
        $setting = BranchSetting::create([
            'branch_id' => $this->branch->id,
            'tax_rate' => 12.00,
            'minimum_order' => 10.00,
            'currency' => 'GBP',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/branch-settings/{$setting->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['currency' => 'GBP']);
    }

    public function test_can_update_branch_setting(): void
    {
        $setting = BranchSetting::create([
            'branch_id' => $this->branch->id,
            'tax_rate' => 5.00,
            'minimum_order' => 15.00,
            'currency' => 'GBP',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/branch-settings/{$setting->id}", [
                'minimum_order' => 30.00,
                'currency' => 'USD',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['currency' => 'USD']);

        $this->assertDatabaseHas('branch_settings', [
            'id' => $setting->id,
            'currency' => 'USD',
        ]);
    }

    public function test_can_delete_branch_setting(): void
    {
        $setting = BranchSetting::create([
            'branch_id' => $this->branch->id,
            'tax_rate' => 5.00,
            'currency' => 'GBP',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/branch-settings/{$setting->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('branch_settings', ['id' => $setting->id]);
    }

    public function test_can_get_setting_by_branch_id(): void
    {
        BranchSetting::create([
            'branch_id' => $this->branch->id,
            'tax_rate' => 15.00,
            'currency' => 'CAD',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/branch-settings/branch/{$this->branch->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['currency' => 'CAD']);
    }
}
