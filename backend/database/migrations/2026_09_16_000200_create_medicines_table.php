<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('sku')->nullable()->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('brand')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('generic_name')->nullable();
            $table->text('composition')->nullable();
            $table->string('strength')->nullable();
            $table->string('dosage_form')->nullable();
            $table->string('pack_size')->nullable();
            $table->decimal('mrp', 10, 2)->nullable();
            $table->decimal('selling_price', 10, 2)->nullable();
            $table->unsignedInteger('stock_qty')->nullable();
            $table->string('stock_status')->default('on_request');
            $table->boolean('requires_prescription')->default(false);
            $table->boolean('allow_backorder')->default(false);
            $table->unsignedInteger('max_order_quantity')->nullable();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['is_active', 'category_id']);
            $table->index('stock_status');
            $table->index('requires_prescription');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medicines');
    }
};
