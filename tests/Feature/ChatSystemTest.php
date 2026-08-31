<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Conversation;
use App\Models\Driver;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatSystemTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $branchAdmin;
    protected User $customer1;
    protected User $customer2;
    protected User $driverUser;
    protected Driver $driver;
    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create([
            'name' => 'Main Branch',
            'email' => 'main@test.com',
            'phone' => '1234567890',
            'address' => '123 Main St',
            'status' => 'active',
        ]);

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@test.com',
            'password' => bcrypt('password'),
            'user_type' => 'super_admin',
        ]);

        $this->branchAdmin = User::create([
            'name' => 'Branch Admin',
            'email' => 'branchadmin@test.com',
            'password' => bcrypt('password'),
            'user_type' => 'branch_admin',
        ]);
        $this->branchAdmin->branchAdmin()->create(['branch_id' => $this->branch->id]);

        $this->customer1 = User::create([
            'name' => 'Customer One',
            'email' => 'customer1@test.com',
            'password' => bcrypt('password'),
            'user_type' => 'customer',
        ]);

        $this->customer2 = User::create([
            'name' => 'Customer Two',
            'email' => 'customer2@test.com',
            'password' => bcrypt('password'),
            'user_type' => 'customer',
        ]);

        $this->driverUser = User::create([
            'name' => 'Driver One',
            'email' => 'driver1@test.com',
            'password' => bcrypt('password'),
            'user_type' => 'driver',
        ]);

        $this->driver = Driver::create([
            'user_id' => $this->driverUser->id,
            'branch_id' => $this->branch->id,
            'name' => 'Driver One',
            'email' => 'driver1@test.com',
            'phone' => '0987654321',
            'status' => 'available',
            'kyc_status' => 'approved',
            'is_online' => true,
        ]);
    }

    public function test_super_admin_can_start_conversation_with_anyone(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $response = $this->postJson('/api/conversations', [
            'receiver_id' => $this->customer1->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_customer_cannot_message_another_customer(): void
    {
        Sanctum::actingAs($this->customer1);

        $response = $this->postJson('/api/conversations', [
            'receiver_id' => $this->customer2->id,
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_customer_can_message_branch_admin(): void
    {
        Sanctum::actingAs($this->customer1);

        $response = $this->postJson('/api/conversations', [
            'receiver_id' => $this->branchAdmin->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_driver_cannot_message_unrelated_customer(): void
    {
        Sanctum::actingAs($this->driverUser);

        $response = $this->postJson('/api/conversations', [
            'receiver_id' => $this->customer1->id,
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_driver_and_customer_can_message_when_order_is_assigned(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-123456',
            'user_id' => $this->customer1->id,
            'branch_id' => $this->branch->id,
            'assigned_driver_id' => $this->driver->id,
            'order_type' => 'delivery',
            'order_status' => 'out_for_delivery',
            'payment_status' => 'paid',
            'total' => 50.00,
        ]);

        // Customer initiates chat with Driver
        Sanctum::actingAs($this->customer1);
        $response = $this->postJson('/api/conversations', [
            'receiver_id' => $this->driverUser->id,
            'order_id' => $order->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $conversationId = $response->json('conversation.id');

        // Customer sends message
        $msgResponse = $this->postJson("/api/conversations/{$conversationId}/messages", [
            'message' => 'Hello Driver, please leave the package at the door.',
        ]);

        $msgResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.message', 'Hello Driver, please leave the package at the door.');

        // Driver views conversation and marks as read
        Sanctum::actingAs($this->driverUser);
        $convResponse = $this->getJson("/api/conversations/{$conversationId}");
        $convResponse->assertStatus(200);

        // Driver replies to customer
        $replyResponse = $this->postJson("/api/conversations/{$conversationId}/messages", [
            'message' => 'Sure, I am on the way!',
        ]);

        $replyResponse->assertStatus(201)
            ->assertJsonPath('success', true);
    }
}
