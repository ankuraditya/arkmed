<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Medicine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_medicine_queue_filters_by_search_category_and_state(): void
    {
        $this->asPharmacyAdmin();
        $category = Category::create(['name' => 'Tablets', 'slug' => 'tablets', 'is_active' => true]);
        Medicine::create($this->medicine($category->id, ['name' => 'Filtered Medicine', 'slug' => 'filtered-medicine', 'sku' => 'FILTER-1', 'is_active' => true, 'requires_prescription' => true]));
        Medicine::create($this->medicine($category->id, ['name' => 'Other Medicine', 'slug' => 'other-medicine']));

        $this->getJson("/api/v1/admin/medicines?q=FILTER&category={$category->id}&active=1&prescription=1")
            ->assertOk()->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0.name', 'Filtered Medicine');
    }

    public function test_medicines_can_be_bulk_updated_and_action_is_audited(): void
    {
        $this->asPharmacyAdmin();
        $category = Category::create(['name' => 'Tablets', 'slug' => 'tablets', 'is_active' => true]);
        $first = Medicine::create($this->medicine($category->id, ['name' => 'First', 'slug' => 'first']));
        $second = Medicine::create($this->medicine($category->id, ['name' => 'Second', 'slug' => 'second']));

        $this->postJson('/api/v1/admin/medicines-bulk', ['ids' => [$first->id, $second->id], 'action' => 'activate'])->assertOk()->assertJsonPath('data.updated', 2);
        $this->assertDatabaseCount('medicines', 2);
        $this->assertSame(2, Medicine::where('is_active', true)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'medicines_bulk_updated']);
    }

    public function test_medicine_can_be_duplicated_archived_and_restored(): void
    {
        $this->asPharmacyAdmin();
        $category = Category::create(['name' => 'Tablets', 'slug' => 'tablets', 'is_active' => true]);
        $medicine = Medicine::create($this->medicine($category->id, ['name' => 'Original', 'slug' => 'original', 'sku' => 'ORIGINAL']));

        $this->postJson("/api/v1/admin/medicines/{$medicine->id}/duplicate")->assertCreated()->assertJsonPath('data.is_active', false);
        $this->deleteJson("/api/v1/admin/medicines/{$medicine->id}")->assertOk();
        $this->getJson('/api/v1/admin/medicines?archived=1')->assertOk()->assertJsonCount(1, 'data.data');
        $this->postJson("/api/v1/admin/medicines/{$medicine->id}/restore")->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'medicine_restored', 'auditable_id' => $medicine->id]);
    }

    public function test_selling_price_cannot_exceed_mrp(): void
    {
        $this->asPharmacyAdmin();
        $category = Category::create(['name' => 'Tablets', 'slug' => 'tablets', 'is_active' => true]);
        $payload = $this->medicine($category->id, ['name' => 'Invalid Price', 'slug' => 'invalid-price', 'mrp' => 50, 'selling_price' => 60]);
        unset($payload['public_id']);

        $this->postJson('/api/v1/admin/medicines', $payload)->assertUnprocessable()->assertJsonValidationErrors('selling_price');
    }

    public function test_categories_include_usage_count_and_used_category_cannot_be_deleted(): void
    {
        $this->asPharmacyAdmin();
        $category = Category::create(['name' => 'Tablets', 'slug' => 'tablets', 'is_active' => true]);
        Medicine::create($this->medicine($category->id, ['name' => 'Used Medicine', 'slug' => 'used-medicine']));

        $this->getJson('/api/v1/admin/categories')->assertOk()->assertJsonPath('data.0.medicines_count', 1);
        $this->deleteJson("/api/v1/admin/categories/{$category->id}")->assertUnprocessable();
    }

    public function test_user_queue_filters_by_role_and_active_state(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin', 'is_active' => true]));
        User::factory()->create(['name' => 'Filtered Staff', 'email' => 'filtered@example.com', 'role' => 'order_staff', 'is_active' => false]);

        $this->getJson('/api/v1/admin/users?q=Filtered&role=order_staff&active=0')->assertOk()->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0.email', 'filtered@example.com');
    }

    public function test_last_active_super_admin_cannot_remove_own_access(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        Sanctum::actingAs($admin);
        $this->putJson("/api/v1/admin/users/{$admin->id}", ['name' => $admin->name, 'email' => $admin->email, 'password' => '', 'role' => 'order_staff', 'is_active' => true])->assertUnprocessable();
    }

    public function test_security_change_revokes_existing_user_tokens(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin', 'is_active' => true]));
        $staff = User::factory()->create(['role' => 'order_staff', 'is_active' => true]);
        $staff->createToken('existing-session');

        $this->putJson("/api/v1/admin/users/{$staff->id}", ['name' => $staff->name, 'email' => $staff->email, 'password' => '', 'role' => 'order_staff', 'is_active' => false])->assertOk();
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $staff->id]);
    }

    public function test_audit_log_can_be_filtered_and_exported(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        Sanctum::actingAs($admin);
        AuditLog::create(['user_id' => $admin->id, 'action' => 'medicine_updated', 'ip_address' => '127.0.0.1']);
        AuditLog::create(['user_id' => $admin->id, 'action' => 'category_updated', 'ip_address' => '127.0.0.1']);

        $this->getJson('/api/v1/admin/audit-logs?action=medicine_updated')->assertOk()->assertJsonCount(1, 'data.data');
        $response = $this->get('/api/v1/admin/audit-logs-export?action=medicine_updated')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $this->assertStringContainsString('medicine_updated', $csv);
        $this->assertStringNotContainsString('category_updated', $csv);
    }

    private function asPharmacyAdmin(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'pharmacy_admin', 'is_active' => true]));
    }

    private function medicine(int $categoryId, array $overrides = []): array
    {
        return array_merge(['public_id' => (string) Str::ulid(), 'name' => 'Medicine', 'slug' => 'medicine-'.Str::lower(Str::random(5)), 'category_id' => $categoryId, 'mrp' => 100, 'selling_price' => 80, 'stock_status' => 'on_request', 'requires_prescription' => false, 'allow_backorder' => false, 'is_featured' => false, 'is_active' => false], $overrides);
    }
}
