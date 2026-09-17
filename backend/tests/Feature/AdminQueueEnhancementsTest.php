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

class AdminQueueEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_returns_actionable_workload_and_value_metrics(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'order_staff', 'is_active' => true]));
        Order::create(array_merge($this->orderData(), ['subtotal' => 125]));
        Enquiry::create(['type' => 'contact', 'name' => 'Customer', 'mobile' => '9876543210', 'status' => 'new']);
        Prescription::create(['public_id' => (string) Str::ulid(), 'status' => 'pending']);

        $this->getJson('/api/v1/admin/dashboard')->assertOk()
            ->assertJsonPath('data.open_orders', 1)
            ->assertJsonPath('data.revenue_today', 125)
            ->assertJsonPath('data.new_enquiries', 1)
            ->assertJsonPath('data.pending_prescriptions', 1)
            ->assertJsonCount(1, 'data.recent_enquiries');
    }

    public function test_order_detail_returns_only_valid_next_transitions(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'order_staff', 'is_active' => true]));
        $order = Order::create($this->orderData());
        $this->getJson("/api/v1/admin/orders/{$order->id}")->assertOk()
            ->assertJsonPath('data.available_transitions', ['contacted', 'confirmed', 'cancelled', 'unable_to_fulfil']);
    }

    public function test_order_status_note_is_trimmed(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'order_staff', 'is_active' => true]));
        $order = Order::create($this->orderData());
        $this->putJson("/api/v1/admin/orders/{$order->id}", ['status' => 'contacted', 'note' => '  Customer called  '])->assertOk();
        $this->assertDatabaseHas('order_status_histories', ['order_id' => $order->id, 'note' => 'Customer called']);
    }

    public function test_enquiry_status_transitions_are_forward_only(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'order_staff', 'is_active' => true]));
        $enquiry = Enquiry::create(['type' => 'contact', 'name' => 'Customer', 'mobile' => '9876543210', 'status' => 'contacted']);
        $this->getJson("/api/v1/admin/enquiries/{$enquiry->id}")->assertOk()->assertJsonPath('data.available_transitions', ['closed']);
        $this->putJson("/api/v1/admin/enquiries/{$enquiry->id}", ['status' => 'new'])->assertUnprocessable();
        $this->putJson("/api/v1/admin/enquiries/{$enquiry->id}", ['status' => 'closed'])->assertOk()->assertJsonPath('data.available_transitions', []);
    }

    public function test_enquiry_queue_supports_date_filters(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'order_staff', 'is_active' => true]));
        Enquiry::create(['type' => 'contact', 'name' => 'Recent', 'mobile' => '9876543210', 'status' => 'new']);
        $old = Enquiry::create(['type' => 'contact', 'name' => 'Old', 'mobile' => '9876543211', 'status' => 'new']);
        Enquiry::whereKey($old->id)->update(['created_at' => now()->subMonth()]);
        $this->getJson('/api/v1/admin/enquiries?from='.today()->toDateString())->assertOk()->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0.name', 'Recent');
    }

    public function test_prescription_queue_supports_dates_and_returns_transitions(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'prescription_reviewer', 'is_active' => true]));
        $prescription = Prescription::create(['public_id' => (string) Str::ulid(), 'status' => 'pending']);
        $old = Prescription::create(['public_id' => (string) Str::ulid(), 'status' => 'pending']);
        Prescription::whereKey($old->id)->update(['created_at' => now()->subMonth()]);
        $this->getJson('/api/v1/admin/prescriptions?from='.today()->toDateString())->assertOk()->assertJsonCount(1, 'data.data');
        $this->getJson("/api/v1/admin/prescriptions/{$prescription->id}")->assertOk()->assertJsonPath('data.available_transitions', ['reviewed', 'more_info_required', 'accepted', 'unable_to_fulfil']);
    }

    private function orderData(): array
    {
        return ['public_id' => (string) Str::ulid(), 'reference' => 'ARK-QUEUE-ENHANCED', 'status' => 'new', 'prescription_status' => 'not_required', 'customer_name' => 'Customer', 'mobile' => '9876543210', 'address_line_1' => 'Address', 'city' => 'Hyderabad', 'state' => 'Telangana', 'pincode' => '500001', 'pricing_status' => 'priced', 'source' => 'web'];
    }
}
