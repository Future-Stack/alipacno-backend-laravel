<?php

namespace Tests\Feature;

use App\Models\PaymentGateway;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentGatewayTest extends TestCase
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

    public function test_can_list_payment_gateways(): void
    {
        PaymentGateway::create([
            'gateway_name' => 'Stripe',
            'api_key' => 'pk_test_123',
            'secret_key' => 'sk_test_123',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/payment-gateways');

        $response->assertStatus(200)
            ->assertJsonFragment(['gateway_name' => 'Stripe']);
    }

    public function test_can_create_payment_gateway(): void
    {
        $payload = [
            'gateway_name' => 'PayPal',
            'api_key' => 'paypal_client_id',
            'secret_key' => 'paypal_secret',
            'webhook' => 'https://example.com/webhook/paypal',
            'status' => 'active',
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/payment-gateways', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['gateway_name' => 'PayPal']);

        $this->assertDatabaseHas('payment_gateways', [
            'gateway_name' => 'PayPal',
        ]);
    }

    public function test_can_show_payment_gateway(): void
    {
        $gateway = PaymentGateway::create([
            'gateway_name' => 'Square',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/payment-gateways/{$gateway->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['gateway_name' => 'Square']);
    }

    public function test_can_update_payment_gateway(): void
    {
        $gateway = PaymentGateway::create([
            'gateway_name' => 'Razorpay',
            'status' => 'inactive',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/payment-gateways/{$gateway->id}", [
                'status' => 'active',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['status' => 'active']);

        $this->assertDatabaseHas('payment_gateways', [
            'id' => $gateway->id,
            'status' => 'active',
        ]);
    }

    public function test_can_delete_payment_gateway(): void
    {
        $gateway = PaymentGateway::create([
            'gateway_name' => 'Klarna',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/payment-gateways/{$gateway->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('payment_gateways', ['id' => $gateway->id]);
    }

    public function test_can_toggle_payment_gateway_status(): void
    {
        $gateway = PaymentGateway::create([
            'gateway_name' => 'Mollie',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->patchJson("/api/v1/payment-gateways/{$gateway->id}/toggle-status");

        $response->assertStatus(200)
            ->assertJsonFragment(['status' => 'inactive']);
    }

    public function test_can_test_connection(): void
    {
        $gateway = PaymentGateway::create([
            'gateway_name' => 'Adyen',
            'api_key' => 'test_key',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/payment-gateways/{$gateway->id}/test-connection");

        $response->assertStatus(200)
            ->assertJsonFragment(['success' => true]);
    }

    public function test_can_get_active_payment_gateways_publicly(): void
    {
        PaymentGateway::create(['gateway_name' => 'Stripe', 'status' => 'active']);
        PaymentGateway::create(['gateway_name' => 'OldGateway', 'status' => 'inactive']);

        $response = $this->getJson('/api/v1/payment-gateways/active');

        $response->assertStatus(200)
            ->assertJsonFragment(['gateway_name' => 'Stripe'])
            ->assertJsonMissing(['gateway_name' => 'OldGateway']);
    }
}
