<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthAndWorkflowSafeguardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_and_logout_are_audited(): void
    {
        User::factory()->create(['email' => 'secure@example.com', 'password' => 'secure-password', 'role' => 'super_admin', 'is_active' => true]);
        $token = $this->postJson('/api/v1/admin/login', ['email' => 'secure@example.com', 'password' => 'secure-password'])->assertOk()->json('data.token');
        $this->withToken($token)->postJson('/api/v1/admin/logout')->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin_logged_in']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin_logged_out']);
    }

    public function test_admin_can_update_own_profile(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        Sanctum::actingAs($admin);
        $this->putJson('/api/v1/admin/profile', ['name' => 'Updated Admin', 'email' => 'updated@example.com'])->assertOk()->assertJsonPath('data.name', 'Updated Admin');
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin_profile_updated', 'auditable_id' => $admin->id]);
    }

    public function test_password_change_requires_current_password(): void
    {
        $admin = User::factory()->create(['password' => 'old-secure-password', 'role' => 'super_admin', 'is_active' => true]);
        Sanctum::actingAs($admin);
        $this->putJson('/api/v1/admin/password', ['current_password' => 'wrong-password', 'password' => 'new-secure-password', 'password_confirmation' => 'new-secure-password'])->assertUnprocessable();
        $this->assertTrue(Hash::check('old-secure-password', $admin->fresh()->password));
    }

    public function test_password_change_revokes_tokens_and_is_audited(): void
    {
        $admin = User::factory()->create(['password' => 'old-secure-password', 'role' => 'super_admin', 'is_active' => true]);
        $token = $admin->createToken('browser')->plainTextToken;
        $this->withToken($token)->putJson('/api/v1/admin/password', ['current_password' => 'old-secure-password', 'password' => 'new-secure-password', 'password_confirmation' => 'new-secure-password'])->assertOk();
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $admin->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin_password_changed']);
    }

    public function test_order_rejects_invalid_status_jump(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'order_staff', 'is_active' => true]));
        $order = Order::create($this->orderData());
        $this->putJson("/api/v1/admin/orders/{$order->id}", ['status' => 'completed'])->assertUnprocessable();
        $this->assertSame('new', $order->fresh()->status);
    }

    public function test_order_rejects_duplicate_status_event(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'order_staff', 'is_active' => true]));
        $order = Order::create($this->orderData());
        $this->putJson("/api/v1/admin/orders/{$order->id}", ['status' => 'new'])->assertUnprocessable();
        $this->assertDatabaseCount('order_status_histories', 0);
    }

    public function test_terminal_order_cannot_be_reopened(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'order_staff', 'is_active' => true]));
        $order = Order::create(array_merge($this->orderData(), ['status' => 'completed']));
        $this->putJson("/api/v1/admin/orders/{$order->id}", ['status' => 'contacted'])->assertUnprocessable();
    }

    public function test_more_information_prescription_status_requires_note(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'prescription_reviewer', 'is_active' => true]));
        $prescription = Prescription::create(['public_id' => (string) Str::ulid(), 'status' => 'pending']);
        $this->putJson("/api/v1/admin/prescriptions/{$prescription->id}", ['status' => 'more_info_required', 'review_note' => ''])->assertUnprocessable();
        $this->putJson("/api/v1/admin/prescriptions/{$prescription->id}", ['status' => 'more_info_required', 'review_note' => 'Upload a clearer image.'])->assertOk();
    }

    public function test_closed_prescription_cannot_be_reopened(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'prescription_reviewer', 'is_active' => true]));
        $prescription = Prescription::create(['public_id' => (string) Str::ulid(), 'status' => 'closed']);
        $this->putJson("/api/v1/admin/prescriptions/{$prescription->id}", ['status' => 'accepted'])->assertUnprocessable();
    }

    public function test_enquiry_contact_data_is_normalised(): void
    {
        $this->postJson('/api/v1/enquiries', ['type' => 'contact', 'name' => '  Test Customer  ', 'mobile' => '+919876543210', 'email' => 'CUSTOMER@EXAMPLE.COM', 'message' => '  Call me  ', 'consent' => true])->assertCreated();
        $this->assertDatabaseHas('enquiries', ['name' => 'Test Customer', 'email' => 'customer@example.com', 'message' => 'Call me']);
    }

    private function orderData(): array
    {
        return ['public_id' => (string) Str::ulid(), 'reference' => 'ARK-SAFE-001', 'status' => 'new', 'prescription_status' => 'not_required', 'customer_name' => 'Customer', 'mobile' => '9876543210', 'address_line_1' => 'Address', 'city' => 'Hyderabad', 'state' => 'Telangana', 'pincode' => '500001', 'pricing_status' => 'confirm_on_whatsapp', 'source' => 'web'];
    }
}
