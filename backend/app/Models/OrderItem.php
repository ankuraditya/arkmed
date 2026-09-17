<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = ['order_id', 'medicine_id', 'sku_snapshot', 'name_snapshot', 'strength_snapshot', 'unit_price', 'quantity', 'line_total', 'requires_prescription_snapshot'];

    protected function casts(): array
    {
        return ['unit_price' => 'decimal:2', 'line_total' => 'decimal:2', 'requires_prescription_snapshot' => 'boolean'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
