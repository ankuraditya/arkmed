<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('reference')->unique();
            $table->string('status')->default('new');
            $table->string('prescription_status')->default('not_required');
            $table->string('customer_name', 120);
            $table->string('mobile', 20)->index();
            $table->string('email')->nullable();
            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();
            $table->string('landmark', 150)->nullable();
            $table->string('city', 100);
            $table->string('state', 100);
            $table->string('pincode', 6);
            $table->text('customer_note')->nullable();
            $table->decimal('subtotal', 10, 2)->nullable();
            $table->string('pricing_status')->default('confirm_on_whatsapp');
            $table->string('source')->default('web');
            $table->timestamp('whatsapp_redirected_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sku_snapshot')->nullable();
            $table->string('name_snapshot');
            $table->string('strength_snapshot')->nullable();
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('line_total', 10, 2)->nullable();
            $table->boolean('requires_prescription_snapshot');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
