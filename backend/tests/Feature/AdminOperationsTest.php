<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_requires_preview_and_imports_valid_rows_as_inactive(): void
    {
        Storage::fake('imports');
        $admin = User::factory()->create(['role' => 'pharmacy_admin', 'is_active' => true]);
        Sanctum::actingAs($admin);
        Category::create(['name' => 'Tablets', 'slug' => 'tablets', 'is_active' => true]);
        $csv = "sku,name,category,brand,manufacturer,generic_name,composition,strength,dosage_form,pack_size,mrp,selling_price,stock_qty,stock_status,requires_prescription,is_active\nSKU-1,Verified Sample,Tablets,,,,,500 mg,Tablet,,,,,on_request,0,1\n";
        $preview = $this->postJson('/api/v1/admin/medicine-imports/preview', ['file' => UploadedFile::fake()->createWithContent('medicines.csv', $csv)])->assertCreated()->assertJsonPath('data.success_rows', 1)->json('data');
        $this->postJson("/api/v1/admin/medicine-imports/{$preview['public_id']}/commit")->assertOk();
        $this->assertDatabaseHas('medicines', ['sku' => 'SKU-1', 'is_active' => false]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'medicine_import_committed']);
    }

    public function test_super_admin_can_create_staff_account(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin', 'is_active' => true]));
        $this->postJson('/api/v1/admin/users', ['name' => 'Order Staff', 'email' => 'orders@example.com', 'password' => 'a-secure-password', 'role' => 'order_staff', 'is_active' => true])->assertCreated()->assertJsonPath('data.role', 'order_staff');
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin_user_created']);
    }

    public function test_order_update_creates_status_history(): void
    {
        $admin = User::factory()->create(['role' => 'order_staff', 'is_active' => true]);
        Sanctum::actingAs($admin);
        $order = Order::create(['public_id' => (string) Str::ulid(), 'reference' => 'ARK-TEST-001', 'status' => 'new', 'prescription_status' => 'not_required', 'customer_name' => 'Customer', 'mobile' => '9876543210', 'address_line_1' => 'Address', 'city' => 'Hyderabad', 'state' => 'Telangana', 'pincode' => '500001', 'pricing_status' => 'confirm_on_whatsapp', 'source' => 'web']);
        $this->putJson("/api/v1/admin/orders/{$order->id}", ['status' => 'contacted', 'note' => 'Called customer'])->assertOk();
        $this->assertDatabaseHas('order_status_histories', ['order_id' => $order->id, 'status' => 'contacted', 'note' => 'Called customer']);
    }
}
