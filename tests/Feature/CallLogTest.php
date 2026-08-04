<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CallLog;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CallLogTest extends TestCase
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

    public function test_can_list_call_logs(): void
    {
        CallLog::create([
            'branch_id' => $this->branch->id,
            'customer_name' => 'John Customer',
            'phone' => '+1234567890',
            'call_type' => 'incoming',
            'call_status' => 'answered',
            'started_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/call-logs');

        $response->assertStatus(200)
            ->assertJsonFragment(['phone' => '+1234567890']);
    }

    public function test_can_filter_call_logs_by_type_status_and_search(): void
    {
        CallLog::create([
            'branch_id' => $this->branch->id,
            'customer_name' => 'Alice Smith',
            'phone' => '+1987654321',
            'call_type' => 'incoming',
            'call_status' => 'answered',
            'started_at' => now(),
        ]);

        CallLog::create([
            'branch_id' => $this->branch->id,
            'customer_name' => 'Bob Jones',
            'phone' => '+1555444333',
            'call_type' => 'outgoing',
            'call_status' => 'missed',
            'started_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/call-logs?call_type=incoming&search=Alice");

        $response->assertStatus(200)
            ->assertJsonFragment(['customer_name' => 'Alice Smith'])
            ->assertJsonMissing(['customer_name' => 'Bob Jones']);
    }

    public function test_can_create_call_log_with_duration_calculation(): void
    {
        $now = now();
        $startedAt = $now->copy()->subMinutes(5)->toDateTimeString();
        $endedAt = $now->toDateTimeString();

        $payload = [
            'branch_id' => $this->branch->id,
            'customer_name' => 'Charlie Brown',
            'phone' => '+1122334455',
            'call_type' => 'incoming',
            'call_status' => 'answered',
            'call_outcome' => 'converted',
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/call-logs', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['customer_name' => 'Charlie Brown']);

        $this->assertDatabaseHas('call_logs', [
            'branch_id' => $this->branch->id,
            'customer_name' => 'Charlie Brown',
            'call_duration' => 300,
        ]);
    }

    public function test_can_show_call_log(): void
    {
        $log = CallLog::create([
            'branch_id' => $this->branch->id,
            'phone' => '+1777888999',
            'started_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/call-logs/{$log->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['phone' => '+1777888999']);
    }

    public function test_can_update_call_log(): void
    {
        $log = CallLog::create([
            'branch_id' => $this->branch->id,
            'phone' => '+1777888999',
            'call_outcome' => 'inquiry',
            'started_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/call-logs/{$log->id}", [
                'call_outcome' => 'converted',
                'notes' => 'Customer placed order after inquiry.',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['call_outcome' => 'converted']);

        $this->assertDatabaseHas('call_logs', [
            'id' => $log->id,
            'call_outcome' => 'converted',
        ]);
    }

    public function test_can_delete_call_log(): void
    {
        $log = CallLog::create([
            'branch_id' => $this->branch->id,
            'phone' => '+1777888999',
            'started_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/call-logs/{$log->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('call_logs', ['id' => $log->id]);
    }

    public function test_can_get_call_log_statistics(): void
    {
        CallLog::create([
            'branch_id' => $this->branch->id,
            'phone' => '+1000000001',
            'call_status' => 'answered',
            'call_duration' => 120,
            'call_outcome' => 'converted',
            'started_at' => now(),
        ]);

        CallLog::create([
            'branch_id' => $this->branch->id,
            'phone' => '+1000000002',
            'call_status' => 'missed',
            'call_duration' => 0,
            'started_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/call-logs/stats');

        $response->assertStatus(200)
            ->assertJsonFragment([
                'total_calls' => 2,
                'answered_calls' => 1,
                'missed_calls' => 1,
            ]);
    }
}
