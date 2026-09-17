<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Medicine extends Model
{
    use SoftDeletes;

    protected $fillable = ['public_id', 'sku', 'name', 'slug', 'category_id', 'brand', 'manufacturer', 'generic_name', 'composition', 'strength', 'dosage_form', 'pack_size', 'mrp', 'selling_price', 'stock_qty', 'stock_status', 'requires_prescription', 'allow_backorder', 'max_order_quantity', 'description', 'image_path', 'is_featured', 'is_active'];

    protected function casts(): array
    {
        return ['mrp' => 'decimal:2', 'selling_price' => 'decimal:2', 'requires_prescription' => 'boolean', 'allow_backorder' => 'boolean', 'is_featured' => 'boolean', 'is_active' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
