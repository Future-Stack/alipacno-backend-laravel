<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemSettingTest extends TestCase
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

    public function test_can_list_system_settings(): void
    {
        SystemSetting::create([
            'setting_key' => 'site_name',
            'setting_value' => 'Alipacino Restaurant',
            'description' => 'Brand name of the application',
            'updated_by' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/system-settings');

        $response->assertStatus(200)
            ->assertJsonFragment(['setting_key' => 'site_name']);
    }

    public function test_can_list_system_settings_as_dictionary(): void
    {
        SystemSetting::create([
            'setting_key' => 'currency',
            'setting_value' => 'USD',
        ]);

        SystemSetting::create([
            'setting_key' => 'timezone',
            'setting_value' => 'UTC',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/system-settings?dictionary=true');

        $response->assertStatus(200)
            ->assertJsonFragment([
                'currency' => 'USD',
                'timezone' => 'UTC',
            ]);
    }

    public function test_can_create_system_setting(): void
    {
        $payload = [
            'setting_key' => 'tax_rate',
            'setting_value' => '5.0',
            'description' => 'Default VAT percentage',
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/system-settings', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['setting_key' => 'tax_rate']);

        $this->assertDatabaseHas('system_settings', [
            'setting_key' => 'tax_rate',
            'setting_value' => '5.0',
            'updated_by' => $this->adminUser->id,
        ]);
    }

    public function test_can_show_system_setting(): void
    {
        $setting = SystemSetting::create([
            'setting_key' => 'max_table_capacity',
            'setting_value' => '20',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/system-settings/{$setting->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['setting_key' => 'max_table_capacity']);
    }

    public function test_can_get_system_setting_by_key(): void
    {
        SystemSetting::create([
            'setting_key' => 'support_email',
            'setting_value' => 'support@alipacino.com',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/system-settings/key/support_email');

        $response->assertStatus(200)
            ->assertJsonFragment(['setting_value' => 'support@alipacino.com']);
    }

    public function test_can_update_system_setting(): void
    {
        $setting = SystemSetting::create([
            'setting_key' => 'maintenance_mode',
            'setting_value' => 'false',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/system-settings/{$setting->id}", [
                'setting_value' => 'true',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['setting_value' => 'true']);

        $this->assertDatabaseHas('system_settings', [
            'id' => $setting->id,
            'setting_value' => 'true',
            'updated_by' => $this->adminUser->id,
        ]);
    }

    public function test_can_delete_system_setting(): void
    {
        $setting = SystemSetting::create([
            'setting_key' => 'temp_key',
            'setting_value' => 'temp_val',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/system-settings/{$setting->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('system_settings', ['id' => $setting->id]);
    }

    public function test_can_bulk_update_system_settings(): void
    {
        $payload = [
            'settings' => [
                'theme' => 'dark',
                'items_per_page' => '25',
            ],
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/system-settings/bulk', $payload);

        $response->assertStatus(200)
            ->assertJsonFragment(['success' => true]);

        $this->assertDatabaseHas('system_settings', [
            'setting_key' => 'theme',
            'setting_value' => 'dark',
        ]);

        $this->assertDatabaseHas('system_settings', [
            'setting_key' => 'items_per_page',
            'setting_value' => '25',
        ]);
    }
}
