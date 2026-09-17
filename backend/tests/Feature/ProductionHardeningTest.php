<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Order;
use App\Models\Prescription;
use App\Models\PrescriptionFile;
use App\Models\TemporaryPrescriptionUpload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductionHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_and_security_headers_are_available(): void
    {
        $this->getJson('/api/v1/health/ready')
            ->assertOk()
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.checks.database', true)
            ->assertJsonPath('data.checks.prescription_storage', true)
            ->assertJsonPath('data.checks.import_storage', true)
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
            ->assertHeader('Cross-Origin-Resource-Policy', 'same-origin');
    }

    public function test_production_check_reports_unsafe_runtime_configuration(): void
    {
        config(['services.whatsapp.number' => null, 'session.driver' => 'array', 'cache.default' => 'array', 'queue.default' => 'sync']);
        $this->artisan('app:production-check --allow-development')->assertFailed();
    }

    public function test_production_check_passes_with_required_runtime_configuration(): void
    {
        config(['services.whatsapp.number' => '919876543210', 'session.driver' => 'database', 'cache.default' => 'database', 'queue.default' => 'database']);
        $this->artisan('app:production-check --allow-development')->assertSuccessful();
    }

    public function test_customer_submissions_are_marked_private_and_not_cacheable(): void
    {
        $this->postJson('/api/v1/enquiries', ['type' => 'contact', 'name' => 'Test Customer', 'mobile' => '9876543210', 'consent' => true])
            ->assertCreated()
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_whatsapp_redirect_requires_a_valid_signature_and_records_use(): void
    {
        config(['services.whatsapp.number' => '919876543210']);
        $order = Order::create($this->orderData());
        $order->items()->create(['name_snapshot' => 'Sample medicine', 'quantity' => 1, 'requires_prescription_snapshot' => false]);

        $this->get("/api/v1/orders/{$order->public_id}/whatsapp")->assertForbidden();
        $url = URL::temporarySignedRoute('orders.whatsapp', now()->addMinutes(5), ['publicId' => $order->public_id]);
        $this->get($url)->assertRedirectContains('https://wa.me/919876543210');
        $this->assertNotNull($order->fresh()->whatsapp_redirected_at);
    }

    public function test_authorised_reviewer_can_download_private_prescription_and_access_is_audited(): void
    {
        Storage::fake('prescriptions');
        Sanctum::actingAs(User::factory()->create(['role' => 'prescription_reviewer', 'is_active' => true]));
        $prescription = Prescription::create(['public_id' => (string) Str::ulid(), 'status' => 'pending']);
        Storage::disk('prescriptions')->put('review/file.pdf', 'private document');
        $file = PrescriptionFile::create(['prescription_id' => $prescription->id, 'disk' => 'prescriptions', 'path' => 'review/file.pdf', 'original_name' => 'prescription.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 16]);

        $this->get("/api/v1/admin/prescription-files/{$file->id}")
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertDatabaseHas('audit_logs', ['action' => 'prescription_file_accessed', 'auditable_id' => $file->id]);
    }

    public function test_expired_temporary_prescriptions_are_purged(): void
    {
        Storage::fake('prescriptions');
        Storage::disk('prescriptions')->put('temporary/expired.pdf', 'expired');
        TemporaryPrescriptionUpload::create(['token' => (string) Str::uuid(), 'disk' => 'prescriptions', 'path' => 'temporary/expired.pdf', 'original_name' => 'expired.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 7, 'checksum' => hash('sha256', 'expired'), 'expires_at' => now()->subMinute()]);

        $this->artisan('prescriptions:purge-temporary')->assertSuccessful();
        Storage::disk('prescriptions')->assertMissing('temporary/expired.pdf');
        $this->assertDatabaseCount('temporary_prescription_uploads', 0);
    }

    public function test_content_manager_can_upload_and_delete_media(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create(['role' => 'content_manager', 'is_active' => true]));
        $response = $this->postJson('/api/v1/admin/media', ['file' => UploadedFile::fake()->image('banner.jpg', 1200, 600), 'alt_text' => 'Pharmacist helping a customer'])->assertCreated();
        $media = Media::findOrFail($response->json('data.id'));
        Storage::disk('public')->assertExists($media->path);

        $this->deleteJson("/api/v1/admin/media/{$media->id}")->assertOk();
        Storage::disk('public')->assertMissing($media->path);
        $this->assertDatabaseHas('audit_logs', ['action' => 'media_deleted']);
    }

    public function test_robots_and_sitemap_are_machine_readable(): void
    {
        $this->get('/robots.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8')->assertSee('Disallow: /checkout')->assertSee('Sitemap:');
        $sitemap = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->assertSee('<urlset', false);
        $this->assertStringContainsString('public', $sitemap->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=3600', $sitemap->headers->get('Cache-Control'));
    }

    public function test_security_contact_is_published_only_when_configured(): void
    {
        config(['services.security.contact_email' => null]);
        $this->get('/.well-known/security.txt')->assertNotFound();
        config(['services.security.contact_email' => 'security@arkmed.example']);
        $this->get('/.well-known/security.txt')->assertOk()->assertSee('mailto:security@arkmed.example')->assertSee('Preferred-Languages: en');
    }

    private function orderData(): array
    {
        return ['public_id' => (string) Str::ulid(), 'reference' => 'ARK-SEC-001', 'status' => 'new', 'prescription_status' => 'not_required', 'customer_name' => 'Test Customer', 'mobile' => '9876543210', 'address_line_1' => 'Test address', 'city' => 'Hyderabad', 'state' => 'Telangana', 'pincode' => '500001', 'pricing_status' => 'confirm_on_whatsapp', 'source' => 'web'];
    }
}
