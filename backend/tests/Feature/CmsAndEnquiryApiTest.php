<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CmsAndEnquiryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_published_active_page_is_public(): void
    {
        Page::create(['title' => 'Privacy Policy', 'slug' => 'privacy-policy', 'body' => 'Approved policy', 'is_active' => true, 'published_at' => now()]);
        Page::create(['title' => 'Draft', 'slug' => 'draft', 'body' => 'Not public', 'is_active' => false]);
        $this->getJson('/api/v1/pages/privacy-policy')->assertOk()->assertJsonPath('data.body', 'Approved policy');
        $this->getJson('/api/v1/pages/draft')->assertNotFound();
    }

    public function test_customer_can_create_valid_service_enquiry(): void
    {
        $this->postJson('/api/v1/enquiries', ['type' => 'doctor_consultation', 'name' => 'Test Customer', 'mobile' => '9876543210', 'message' => 'Please call me', 'consent' => true])->assertCreated()->assertJsonPath('data.status', 'new')->assertJsonPath('data.type', 'doctor_consultation');
        $this->assertDatabaseHas('enquiries', ['type' => 'doctor_consultation', 'mobile' => '9876543210']);
    }

    public function test_invalid_enquiry_is_rejected(): void
    {
        $this->postJson('/api/v1/enquiries', ['type' => 'diagnosis', 'name' => 'A', 'mobile' => '12'])->assertUnprocessable()->assertJsonValidationErrors(['type', 'name', 'mobile']);
    }

    public function test_enquiry_normalizes_customer_contact_details(): void
    {
        $this->postJson('/api/v1/enquiries', ['type' => 'contact', 'name' => '  Test Customer  ', 'mobile' => '(98765) 43210', 'email' => ' CUSTOMER@EXAMPLE.COM ', 'message' => '  Please call  ', 'consent' => 'on'])->assertCreated();
        $this->assertDatabaseHas('enquiries', ['name' => 'Test Customer', 'mobile' => '9876543210', 'email' => 'customer@example.com', 'message' => 'Please call']);
    }

    public function test_enquiry_requires_consent_and_rejects_honeypot_submission(): void
    {
        $payload = ['type' => 'contact', 'name' => 'Test Customer', 'mobile' => '9876543210'];
        $this->postJson('/api/v1/enquiries', $payload)->assertUnprocessable()->assertJsonValidationErrors('consent');
        $this->postJson('/api/v1/enquiries', array_merge($payload, ['consent' => true, 'website' => 'spam.example']))->assertUnprocessable()->assertJsonValidationErrors('website');
        $this->assertDatabaseCount('enquiries', 0);
    }

    public function test_content_manager_can_publish_page_but_not_access_settings(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'content_manager', 'is_active' => true]));
        $payload = ['title' => 'Terms', 'slug' => 'terms', 'body' => 'Approved terms', 'is_active' => true, 'published_at' => now()->toIso8601String()];
        $this->postJson('/api/v1/admin/pages', $payload)->assertCreated();
        $this->getJson('/api/v1/admin/settings')->assertForbidden();
        $this->assertDatabaseHas('audit_logs', ['action' => 'page_created']);
    }
}
