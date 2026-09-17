<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\Category;
use App\Models\Media;
use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContentOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_queue_filters_publication_state_and_duplicates_as_draft(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'content_manager', 'is_active' => true]));
        $page = Page::create(['title' => 'Published Page', 'slug' => 'published-page', 'body' => 'Body', 'is_active' => true, 'published_at' => now()]);
        Page::create(['title' => 'Scheduled Page', 'slug' => 'scheduled-page', 'body' => 'Body', 'is_active' => true, 'published_at' => now()->addDay()]);

        $this->getJson('/api/v1/admin/pages?q=Published&status=published')->assertOk()->assertJsonCount(1, 'data.data');
        $this->postJson("/api/v1/admin/pages/{$page->id}/duplicate")->assertCreated()->assertJsonPath('data.is_active', false)->assertJsonPath('data.published_at', null);
        $this->assertDatabaseHas('audit_logs', ['action' => 'page_duplicated']);
    }

    public function test_banner_queue_filters_and_duplicate_is_inactive(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'content_manager', 'is_active' => true]));
        $banner = Banner::create(['title' => 'Seasonal Banner', 'sort_order' => 1, 'is_active' => true]);
        Banner::create(['title' => 'Hidden Banner', 'sort_order' => 2, 'is_active' => false]);

        $this->getJson('/api/v1/admin/banners?q=Seasonal&active=1')->assertOk()->assertJsonCount(1, 'data.data');
        $this->postJson("/api/v1/admin/banners/{$banner->id}/duplicate")->assertCreated()->assertJsonPath('data.is_active', false);
    }

    public function test_banner_rejects_end_before_start(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'content_manager', 'is_active' => true]));
        $this->postJson('/api/v1/admin/banners', ['title' => 'Invalid', 'sort_order' => 0, 'starts_at' => now()->addDay()->toIso8601String(), 'ends_at' => now()->toIso8601String(), 'is_active' => true])->assertUnprocessable()->assertJsonValidationErrors('ends_at');
    }

    public function test_typed_settings_are_validated_and_updates_are_audited(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin', 'is_active' => true]));
        $this->putJson('/api/v1/admin/settings', ['settings' => [['group' => 'ordering', 'key' => 'limit', 'value' => 'not-a-number', 'type' => 'integer', 'is_public' => false]]])->assertUnprocessable();
        $this->putJson('/api/v1/admin/settings', ['settings' => [['group' => 'ordering', 'key' => 'limit', 'value' => '25', 'type' => 'integer', 'is_public' => false]]])->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings_updated']);
    }

    public function test_public_settings_exclude_private_configuration(): void
    {
        Setting::create(['group' => 'business', 'key' => 'phone', 'value' => '9876543210', 'type' => 'string', 'is_public' => true]);
        Setting::create(['group' => 'internal', 'key' => 'private_note', 'value' => 'secret', 'type' => 'string', 'is_public' => false]);
        $this->getJson('/api/v1/settings/public')->assertOk()->assertJsonPath('data.phone', '9876543210')->assertJsonMissingPath('data.private_note');
    }

    public function test_media_search_and_alt_text_update_are_audited(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'content_manager', 'is_active' => true]));
        $media = Media::create(['disk' => 'public', 'path' => 'cms/image.jpg', 'original_name' => 'pharmacy.jpg', 'mime_type' => 'image/jpeg', 'size_bytes' => 100, 'alt_text' => 'Old text']);
        $this->getJson('/api/v1/admin/media?q=pharmacy')->assertOk()->assertJsonCount(1, 'data.data');
        $this->putJson("/api/v1/admin/media/{$media->id}", ['alt_text' => 'Pharmacist at ARK med'])->assertOk()->assertJsonPath('data.alt_text', 'Pharmacist at ARK med');
        $this->assertDatabaseHas('audit_logs', ['action' => 'media_updated']);
    }

    public function test_import_jobs_can_be_filtered_inspected_and_errors_exported(): void
    {
        Storage::fake('imports');
        Sanctum::actingAs(User::factory()->create(['role' => 'pharmacy_admin', 'is_active' => true]));
        Category::create(['name' => 'Tablets', 'slug' => 'tablets', 'is_active' => true]);
        $csv = "wrong,headers\nvalue,value\n";
        $job = $this->postJson('/api/v1/admin/medicine-imports/preview', ['file' => UploadedFile::fake()->createWithContent('invalid.csv', $csv)])->assertCreated()->json('data');

        $this->getJson('/api/v1/admin/medicine-imports?q=invalid&status=invalid')->assertOk()->assertJsonCount(1, 'data.data');
        $this->getJson("/api/v1/admin/medicine-imports/{$job['public_id']}")->assertOk()->assertJsonCount(1, 'data.errors');
        $response = $this->get("/api/v1/admin/medicine-imports/{$job['public_id']}/errors")->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('CSV headers do not match', $response->streamedContent());
    }
}
