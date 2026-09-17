<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Medicine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MedicineApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalogue_returns_only_active_medicines(): void
    {
        $category = Category::create(['name' => 'Tablets', 'slug' => 'tablets', 'is_active' => true]);
        Medicine::create($this->medicine($category->id, 'Active Sample', 'active-sample', true));
        Medicine::create($this->medicine($category->id, 'Inactive Sample', 'inactive-sample', false));

        $this->getJson('/api/v1/medicines')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Active Sample');
    }

    public function test_catalogue_filters_prescription_and_availability(): void
    {
        $category = Category::create(['name' => 'Tablets', 'slug' => 'tablets', 'is_active' => true]);
        Medicine::create(array_merge($this->medicine($category->id, 'Prescription Sample', 'prescription-sample', true), ['requires_prescription' => true, 'stock_status' => 'low_stock']));
        Medicine::create($this->medicine($category->id, 'General Sample', 'general-sample', true));

        $this->getJson('/api/v1/medicines?prescription=1&availability=low_stock')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Prescription Sample');
    }

    public function test_catalogue_can_sort_by_price(): void
    {
        $category = Category::create(['name' => 'Tablets', 'slug' => 'tablets', 'is_active' => true]);
        Medicine::create(array_merge($this->medicine($category->id, 'Higher Price', 'higher-price', true), ['selling_price' => 150]));
        Medicine::create(array_merge($this->medicine($category->id, 'Lower Price', 'lower-price', true), ['selling_price' => 50]));

        $this->getJson('/api/v1/medicines?sort=price_low')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Lower Price')
            ->assertJsonPath('data.1.name', 'Higher Price');
    }

    public function test_catalogue_rejects_unknown_sort_modes(): void
    {
        $this->getJson('/api/v1/medicines?sort=random')->assertUnprocessable()->assertJsonValidationErrors('sort');
    }

    public function test_catalogue_and_detail_hide_medicines_in_inactive_categories(): void
    {
        $category = Category::create(['name' => 'Hidden', 'slug' => 'hidden', 'is_active' => false]);
        Medicine::create($this->medicine($category->id, 'Hidden Medicine', 'hidden-medicine', true));

        $this->getJson('/api/v1/medicines')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/medicines/hidden-medicine')->assertNotFound();
    }

    public function test_medicine_detail_exposes_verified_product_information_and_discount(): void
    {
        $category = Category::create(['name' => 'Tablets', 'slug' => 'tablets', 'is_active' => true]);
        Medicine::create(array_merge($this->medicine($category->id, 'Detailed Medicine', 'detailed-medicine', true), ['brand' => 'ARK Brand', 'manufacturer' => 'Verified Labs', 'generic_name' => 'Example Generic', 'composition' => 'Verified composition', 'description' => 'Verified description', 'mrp' => 100, 'selling_price' => 80]));

        $this->getJson('/api/v1/medicines/detailed-medicine')->assertOk()->assertJsonPath('data.brand', 'ARK Brand')->assertJsonPath('data.manufacturer', 'Verified Labs')->assertJsonPath('data.generic_name', 'Example Generic')->assertJsonPath('data.price.discount_percent', 20);
    }

    private function medicine(int $categoryId, string $name, string $slug, bool $active): array
    {
        return ['public_id' => (string) Str::ulid(), 'name' => $name, 'slug' => $slug, 'category_id' => $categoryId, 'stock_status' => 'in_stock', 'requires_prescription' => false, 'is_active' => $active];
    }
}
