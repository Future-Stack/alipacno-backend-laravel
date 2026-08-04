<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Restaurant;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierTest extends TestCase
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

    public function test_can_list_suppliers(): void
    {
        Supplier::create([
            'branch_id' => $this->branch->id,
            'name' => 'Fresh Meats Co.',
            'phone' => '1112223333',
            'email' => 'contact@freshmeats.com',
            'address' => '100 Meat St',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/suppliers');

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Fresh Meats Co.']);
    }

    public function test_can_filter_suppliers_by_branch_and_search(): void
    {
        Supplier::create([
            'branch_id' => $this->branch->id,
            'name' => 'Organic Produce Ltd',
            'phone' => '4445556666',
            'email' => 'info@organicproduce.com',
        ]);

        Supplier::create([
            'branch_id' => $this->branch->id,
            'name' => 'Seafood Express',
            'phone' => '7778889999',
            'email' => 'sales@seafoodexpress.com',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/suppliers?branch_id={$this->branch->id}&search=Organic");

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Organic Produce Ltd'])
            ->assertJsonMissing(['name' => 'Seafood Express']);
    }

    public function test_can_create_supplier(): void
    {
        $payload = [
            'branch_id' => $this->branch->id,
            'name' => 'Dairy Supplies Inc',
            'phone' => '5551234567',
            'email' => 'orders@dairysupplies.com',
            'address' => '456 Dairy Farm Way',
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/suppliers', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Dairy Supplies Inc']);

        $this->assertDatabaseHas('suppliers', [
            'branch_id' => $this->branch->id,
            'name' => 'Dairy Supplies Inc',
            'email' => 'orders@dairysupplies.com',
        ]);
    }

    public function test_can_show_supplier(): void
    {
        $supplier = Supplier::create([
            'branch_id' => $this->branch->id,
            'name' => 'Bakery Goods Wholesale',
            'email' => 'hello@bakerygoods.com',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/suppliers/{$supplier->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Bakery Goods Wholesale']);
    }

    public function test_can_update_supplier(): void
    {
        $supplier = Supplier::create([
            'branch_id' => $this->branch->id,
            'name' => 'Old Supplier Name',
            'phone' => '1231231234',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/suppliers/{$supplier->id}", [
                'name' => 'Updated Supplier Name',
                'phone' => '9999999999',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Updated Supplier Name']);

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'name' => 'Updated Supplier Name',
            'phone' => '9999999999',
        ]);
    }

    public function test_can_delete_supplier(): void
    {
        $supplier = Supplier::create([
            'branch_id' => $this->branch->id,
            'name' => 'Temp Supplier',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/suppliers/{$supplier->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
    }
}
