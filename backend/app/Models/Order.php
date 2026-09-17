<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = ['public_id', 'reference', 'status', 'prescription_status', 'customer_name', 'mobile', 'email', 'address_line_1', 'address_line_2', 'landmark', 'city', 'state', 'pincode', 'customer_note', 'subtotal', 'pricing_status', 'source', 'whatsapp_redirected_at'];

    protected function casts(): array
    {
        return ['subtotal' => 'decimal:2', 'whatsapp_redirected_at' => 'datetime'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }
}
