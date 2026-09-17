<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_admin_can_login_and_receive_role(): void
    {
        User::factory()->create(['email' => 'admin@example.com', 'password' => 'secure-password', 'role' => 'pharmacy_admin', 'is_active' => true]);
        $this->postJson('/api/v1/admin/login', ['email' => 'admin@example.com', 'password' => 'secure-password'])
            ->assertOk()->assertJsonPath('data.user.role', 'pharmacy_admin')->assertJsonStructure(['data' => ['token', 'user']]);
    }

    public function test_inactive_admin_cannot_login(): void
    {
        User::factory()->create(['email' => 'inactive@example.com', 'password' => 'secure-password', 'is_active' => false]);
        $this->postJson('/api/v1/admin/login', ['email' => 'inactive@example.com', 'password' => 'secure-password'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_order_staff_cannot_manage_medicines(): void
    {
        $user = User::factory()->create(['role' => 'order_staff', 'is_active' => true]);
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/admin/medicines')->assertForbidden();
    }

    public function test_pharmacy_admin_can_create_medicine(): void
    {
        $user = User::factory()->create(['role' => 'pharmacy_admin', 'is_active' => true]);
        Sanctum::actingAs($user);
        $category = Category::create(['name' => 'Tablets', 'slug' => 'tablets', 'is_active' => true]);
        $payload = ['name' => 'Verified Medicine', 'slug' => 'verified-medicine', 'category_id' => $category->id, 'stock_status' => 'on_request', 'requires_prescription' => false, 'allow_backorder' => false, 'is_featured' => false, 'is_active' => false];
        $this->postJson('/api/v1/admin/medicines', $payload)->assertCreated()->assertJsonPath('data.name', 'Verified Medicine');
        $this->assertDatabaseHas('medicines', ['slug' => 'verified-medicine', 'is_active' => false]);
    }

    public function test_admin_dashboard_returns_operational_metrics(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'order_staff', 'is_active' => true]));
        $this->getJson('/api/v1/admin/dashboard')->assertOk()->assertJsonStructure(['data' => ['active_medicines', 'orders_today', 'pending_prescriptions', 'recent_orders']]);
    }

    public function test_only_super_admin_can_update_settings(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'pharmacy_admin', 'is_active' => true]));
        $this->putJson('/api/v1/admin/settings', ['settings' => []])->assertForbidden();
        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin', 'is_active' => true]));
        $payload = ['settings' => [['group' => 'business', 'key' => 'phone', 'value' => '0000000000', 'type' => 'string', 'is_public' => true]]];
        $this->putJson('/api/v1/admin/settings', $payload)->assertOk();
        $this->assertDatabaseHas('settings', ['key' => 'phone', 'is_public' => true]);
    }
}
