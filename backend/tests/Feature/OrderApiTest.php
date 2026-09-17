<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Medicine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_is_created_from_server_side_medicine_data(): void
    {
        $category = Category::create(['name' => 'Tablets', 'slug' => 'tablets', 'is_active' => true]);
        $medicine = Medicine::create(['public_id' => (string) Str::ulid(), 'name' => 'Verified Sample', 'slug' => 'verified-sample', 'category_id' => $category->id, 'selling_price' => 50, 'stock_status' => 'in_stock', 'requires_prescription' => false, 'is_active' => true]);

        $this->postJson('/api/v1/orders', $this->payload($medicine->public_id))
            ->assertCreated()
            ->assertJsonPath('data.status', 'new')
            ->assertJsonPath('data.pricing_status', 'priced');

        $this->assertDatabaseHas('order_items', ['medicine_id' => $medicine->id, 'unit_price' => 50, 'quantity' => 2]);
    }

    public function test_prescription_medicine_cannot_bypass_prescription_rule(): void
    {
        $category = Category::create(['name' => 'Inhalers', 'slug' => 'inhalers', 'is_active' => true]);
        $medicine = Medicine::create(['public_id' => (string) Str::ulid(), 'name' => 'Rx Sample', 'slug' => 'rx-sample', 'category_id' => $category->id, 'stock_status' => 'in_stock', 'requires_prescription' => true, 'is_active' => true]);

        $this->postJson('/api/v1/orders', $this->payload($medicine->public_id))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('prescription_upload_tokens');
    }

    private function payload(string $medicineId): array
    {
        return ['customer' => ['name' => 'Test Customer', 'mobile' => '9876543210'], 'address' => ['line1' => 'Test address', 'city' => 'Hyderabad', 'state' => 'Telangana', 'pincode' => '500001'], 'items' => [['medicine_id' => $medicineId, 'quantity' => 2]]];
    }
}
