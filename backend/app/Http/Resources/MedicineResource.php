<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MedicineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $mrp = $this->mrp !== null ? (float) $this->mrp : null;
        $selling = $this->selling_price !== null ? (float) $this->selling_price : null;

        return ['id' => $this->public_id, 'name' => $this->name, 'slug' => $this->slug, 'brand' => $this->brand, 'manufacturer' => $this->manufacturer, 'generic_name' => $this->generic_name, 'composition' => $this->composition, 'description' => $this->description, 'strength' => $this->strength, 'dosage_form' => $this->dosage_form, 'pack_size' => $this->pack_size, 'category' => ['name' => $this->category->name, 'slug' => $this->category->slug], 'price' => ['mrp' => $mrp, 'selling' => $selling, 'savings' => $mrp !== null && $selling !== null ? max(0, $mrp - $selling) : null, 'discount_percent' => $mrp > 0 && $selling !== null && $selling < $mrp ? (int) round((($mrp - $selling) / $mrp) * 100) : null, 'status' => $selling !== null ? 'priced' : 'confirm_on_whatsapp'], 'stock_status' => $this->stock_status, 'stock_qty' => $this->stock_qty, 'max_order_quantity' => $this->max_order_quantity ?? 10, 'allow_backorder' => $this->allow_backorder, 'requires_prescription' => $this->requires_prescription, 'image_url' => $this->image_path ? asset('storage/'.$this->image_path) : null];
    }
}
