<?php

namespace Tests\Feature;

use App\Models\Enquiry;
use App\Models\Order;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderTrackingAndQueuesTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_track_order_with_matching_reference_and_mobile(): void
    {
        $order = Order::create($this->orderData());
        $order->statusHistory()->create(['status' => 'confirmed', 'note' => 'Confirmed by pharmacy']);

        $this->postJson('/api/v1/orders/track', ['reference' => strtolower($order->reference), 'mobile' => $order->mobile])
            ->assertOk()
            ->assertJsonPath('data.reference', $order->reference)
            ->assertJsonPath('data.status', 'new')
            ->assertJsonMissingPath('data.customer_name')
            ->assertJsonCount(1, 'data.history');
    }

    public function test_tracking_does_not_disclose_order_for_wrong_mobile(): void
    {
        $order = Order::create($this->orderData());
        $this->postJson('/api/v1/orders/track', ['reference' => $order->reference, 'mobile' => '9000000000'])->assertNotFound();
    }

    public function test_tracking_returns_safe_order_summary_without_customer_details(): void
    {
        $order = Order::create(array_merge($this->orderData(), ['subtotal' => 125, 'pricing_status' => 'priced']));
        $order->items()->create(['name_snapshot' => 'Test Medicine', 'unit_price' => 62.5, 'quantity' => 2, 'line_total' => 125, 'requires_prescription_snapshot' => false]);

        $this->postJson('/api/v1/orders/track', ['reference' => $order->reference, 'mobile' => '(98765) 43210'])
            ->assertOk()
            ->assertJsonPath('data.item_count', 2)
            ->assertJsonPath('data.subtotal', 125)
            ->assertJsonPath('data.pricing_status', 'priced')
            ->assertJsonMissingPath('data.email')
            ->assertJsonMissingPath('data.address_line_1');
    }

    public function test_admin_order_queue_supports_search_and_status_filters(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'order_staff', 'is_active' => true]));
        Order::create($this->orderData());
        Order::create(array_merge($this->orderData(), ['public_id' => (string) Str::ulid(), 'reference' => 'ARK-QUEUE-002', 'customer_name' => 'Another Customer', 'status' => 'completed']));

        $this->getJson('/api/v1/admin/orders?q=QUEUE-001&status=new')->assertOk()->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0.reference', 'ARK-QUEUE-001');
    }

    public function test_order_export_is_csv_and_audited(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'order_staff', 'is_active' => true]));
        Order::create($this->orderData());

        $response = $this->get('/api/v1/admin/orders-export?status=new')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('ARK-QUEUE-001', $response->streamedContent());
        $this->assertDatabaseHas('audit_logs', ['action' => 'orders_exported']);
    }

    public function test_prescription_queue_filters_and_review_is_audited(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'prescription_reviewer', 'is_active' => true]));
        $prescription = Prescription::create(['public_id' => (string) Str::ulid(), 'status' => 'pending']);

        $this->getJson('/api/v1/admin/prescriptions?status=pending')->assertOk()->assertJsonCount(1, 'data.data');
        $this->putJson("/api/v1/admin/prescriptions/{$prescription->id}", ['status' => 'accepted', 'review_note' => 'Readable and valid'])->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'prescription_review_updated', 'auditable_id' => $prescription->id]);
    }

    public function test_enquiry_queue_supports_search_type_and_status(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'order_staff', 'is_active' => true]));
        Enquiry::create(['type' => 'contact', 'name' => 'Queue Customer', 'mobile' => '9876543210', 'email' => 'queue@example.com', 'status' => 'new']);
        Enquiry::create(['type' => 'health_checkup', 'name' => 'Other Customer', 'mobile' => '9876543211', 'status' => 'closed']);

        $this->getJson('/api/v1/admin/enquiries?q=Queue&type=contact&status=new')->assertOk()->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0.email', 'queue@example.com');
    }

    public function test_queue_filters_reject_invalid_values(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'order_staff', 'is_active' => true]));
        $this->getJson('/api/v1/admin/orders?status=unknown')->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->getJson('/api/v1/admin/enquiries?type=unknown')->assertUnprocessable()->assertJsonValidationErrors('type');
    }

    private function orderData(): array
    {
        return ['public_id' => (string) Str::ulid(), 'reference' => 'ARK-QUEUE-001', 'status' => 'new', 'prescription_status' => 'not_required', 'customer_name' => 'Queue Customer', 'mobile' => '9876543210', 'address_line_1' => 'Test address', 'city' => 'Hyderabad', 'state' => 'Telangana', 'pincode' => '500001', 'pricing_status' => 'confirm_on_whatsapp', 'source' => 'web'];
    }
}
