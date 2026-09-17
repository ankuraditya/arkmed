<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('temporary_prescription_uploads', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();
            $table->string('disk')->default('prescriptions');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum', 64);
            $table->timestamp('expires_at')->index();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('temporary_prescription_uploads');
    }
};
