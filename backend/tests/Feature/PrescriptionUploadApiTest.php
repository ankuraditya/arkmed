<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Medicine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PrescriptionUploadApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_prescription_is_stored_privately_and_claimed_by_order(): void
    {
        Storage::fake('prescriptions');
        $upload = $this->postJson('/api/v1/prescription-uploads', ['files' => [UploadedFile::fake()->image('prescription.jpg')]])
            ->assertCreated()
            ->json('data.0');

        $category = Category::create(['name' => 'Inhalers', 'slug' => 'inhalers', 'is_active' => true]);
        $medicine = Medicine::create(['public_id' => (string) Str::ulid(), 'name' => 'Rx Sample', 'slug' => 'rx-upload-sample', 'category_id' => $category->id, 'stock_status' => 'in_stock', 'requires_prescription' => true, 'is_active' => true]);
        $payload = ['customer' => ['name' => 'Test Customer', 'mobile' => '9876543210'], 'address' => ['line1' => 'Test address', 'city' => 'Hyderabad', 'state' => 'Telangana', 'pincode' => '500001'], 'items' => [['medicine_id' => $medicine->public_id, 'quantity' => 1]], 'prescription_upload_tokens' => [$upload['token']]];

        $this->postJson('/api/v1/orders', $payload)->assertCreated()->assertJsonPath('data.prescription_status', 'pending');
        $this->assertDatabaseHas('temporary_prescription_uploads', ['token' => $upload['token']]);
        $this->assertDatabaseCount('prescriptions', 1);
        $this->assertDatabaseCount('prescription_files', 1);
    }

    public function test_invalid_file_type_is_rejected(): void
    {
        Storage::fake('prescriptions');
        $this->postJson('/api/v1/prescription-uploads', ['files' => [UploadedFile::fake()->create('script.exe', 20, 'application/octet-stream')]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('files.0');
    }
}
