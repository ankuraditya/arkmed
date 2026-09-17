<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Medicine;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerOrderSafeguardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_online_ordering_switch_is_enforced_by_server(): void
    {
        Setting::create(['group' => 'ordering', 'key' => 'ordering_enabled', 'value' => 'false', 'type' => 'boolean', 'is_public' => true]);
        $medicine = $this->medicine();
        $this->postJson('/api/v1/orders', $this->payload($medicine->public_id))->assertServiceUnavailable();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_duplicate_medicine_lines_are_rejected(): void
    {
        $medicine = $this->medicine();
        $payload = $this->payload($medicine->public_id);
        $payload['items'][] = $payload['items'][0];
        $this->postJson('/api/v1/orders', $payload)->assertUnprocessable()->assertJsonValidationErrors('items.1.medicine_id');
    }

    public function test_medicine_in_inactive_category_cannot_be_ordered(): void
    {
        $category = Category::create(['name' => 'Hidden', 'slug' => 'hidden', 'is_active' => false]);
        $medicine = Medicine::create($this->medicineData($category->id));
        $this->postJson('/api/v1/orders', $this->payload($medicine->public_id))->assertUnprocessable()->assertJsonValidationErrors('items');
    }

    public function test_order_respects_medicine_maximum_quantity(): void
    {
        $medicine = $this->medicine(['max_order_quantity' => 2]);
        $payload = $this->payload($medicine->public_id);
        $payload['items'][0]['quantity'] = 3;
        $this->postJson('/api/v1/orders', $payload)->assertUnprocessable()->assertJsonValidationErrors('items');
    }

    public function test_order_respects_available_stock_without_backorder(): void
    {
        $medicine = $this->medicine(['stock_qty' => 1, 'allow_backorder' => false]);
        $payload = $this->payload($medicine->public_id);
        $payload['items'][0]['quantity'] = 2;
        $this->postJson('/api/v1/orders', $payload)->assertUnprocessable()->assertJsonValidationErrors('items');
    }

    public function test_backorder_can_exceed_current_stock_within_order_limit(): void
    {
        $medicine = $this->medicine(['stock_qty' => 1, 'allow_backorder' => true, 'max_order_quantity' => 5]);
        $payload = $this->payload($medicine->public_id);
        $payload['items'][0]['quantity'] = 3;
        $this->postJson('/api/v1/orders', $payload)->assertCreated()->assertJsonPath('data.item_count', 3);
    }

    public function test_order_response_contains_server_calculated_subtotal(): void
    {
        $medicine = $this->medicine(['selling_price' => 75]);
        $this->postJson('/api/v1/orders', $this->payload($medicine->public_id))->assertCreated()->assertJsonPath('data.subtotal', 150)->assertJsonPath('data.item_count', 2);
    }

    public function test_customer_and_address_values_are_normalized_before_storage(): void
    {
        $medicine = $this->medicine();
        $payload = $this->payload($medicine->public_id);
        $payload['customer'] = ['name' => '  Test Customer  ', 'mobile' => '(98765) 43210', 'email' => '  CUSTOMER@EXAMPLE.COM '];
        $payload['address']['line1'] = '  Test address  ';
        $payload['customer_note'] = '  Leave at reception  ';

        $this->postJson('/api/v1/orders', $payload)->assertCreated();
        $this->assertDatabaseHas('orders', ['customer_name' => 'Test Customer', 'mobile' => '9876543210', 'email' => 'customer@example.com', 'address_line_1' => 'Test address', 'customer_note' => 'Leave at reception']);
    }

    public function test_medicine_resource_exposes_customer_quantity_constraints_and_savings(): void
    {
        $medicine = $this->medicine(['mrp' => 100, 'selling_price' => 75, 'stock_qty' => 4, 'max_order_quantity' => 3]);
        $this->getJson("/api/v1/medicines/{$medicine->slug}")->assertOk()->assertJsonPath('data.price.savings', 25)->assertJsonPath('data.stock_qty', 4)->assertJsonPath('data.max_order_quantity', 3);
    }

    public function test_duplicate_prescription_files_are_rejected(): void
    {
        Storage::fake('prescriptions');
        $content = '%PDF-1.4 same prescription';
        $this->postJson('/api/v1/prescription-uploads', ['files' => [UploadedFile::fake()->createWithContent('first.pdf', $content), UploadedFile::fake()->createWithContent('second.pdf', $content)]])->assertUnprocessable()->assertJsonValidationErrors('files');
        $this->assertDatabaseCount('temporary_prescription_uploads', 0);
    }

    private function medicine(array $overrides = []): Medicine
    {
        $category = Category::firstOrCreate(['slug' => 'tablets'], ['name' => 'Tablets', 'is_active' => true]);

        return Medicine::create(array_merge($this->medicineData($category->id), $overrides));
    }

    private function medicineData(int $categoryId): array
    {
        return ['public_id' => (string) Str::ulid(), 'name' => 'Safe Medicine', 'slug' => 'safe-'.Str::lower(Str::random(5)), 'category_id' => $categoryId, 'mrp' => 100, 'selling_price' => 50, 'stock_status' => 'in_stock', 'stock_qty' => 10, 'max_order_quantity' => 10, 'requires_prescription' => false, 'allow_backorder' => false, 'is_active' => true];
    }

    private function payload(string $medicineId): array
    {
        return ['customer' => ['name' => 'Test Customer', 'mobile' => '9876543210'], 'address' => ['line1' => 'Test address', 'city' => 'Hyderabad', 'state' => 'Telangana', 'pincode' => '500001'], 'items' => [['medicine_id' => $medicineId, 'quantity' => 2]]];
    }
}
