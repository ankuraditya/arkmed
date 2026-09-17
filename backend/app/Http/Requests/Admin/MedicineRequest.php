<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MedicineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('medicine')?->id;

        return ['name' => 'required|string|max:255', 'slug' => ['required', 'string', 'max:255', Rule::unique('medicines', 'slug')->ignore($id)], 'sku' => ['nullable', 'string', 'max:100', Rule::unique('medicines', 'sku')->ignore($id)], 'category_id' => 'required|exists:categories,id', 'brand' => 'nullable|string|max:255', 'manufacturer' => 'nullable|string|max:255', 'generic_name' => 'nullable|string|max:255', 'composition' => 'nullable|string|max:2000', 'strength' => 'nullable|string|max:100', 'dosage_form' => 'nullable|string|max:100', 'pack_size' => 'nullable|string|max:100', 'mrp' => 'nullable|numeric|min:0', 'selling_price' => 'nullable|numeric|min:0|lte:mrp', 'stock_qty' => 'nullable|integer|min:0', 'stock_status' => 'required|in:in_stock,low_stock,out_of_stock,on_request', 'requires_prescription' => 'required|boolean', 'allow_backorder' => 'required|boolean', 'max_order_quantity' => 'nullable|integer|min:1|max:100', 'description' => 'nullable|string|max:10000', 'image_path' => 'nullable|string|max:1000', 'is_featured' => 'required|boolean', 'is_active' => 'required|boolean'];
    }
}
