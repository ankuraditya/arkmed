<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('category')?->id;

        return ['name' => 'required|string|max:120', 'slug' => ['required', 'string', 'max:120', Rule::unique('categories', 'slug')->ignore($id)], 'description' => 'nullable|string|max:2000', 'sort_order' => 'required|integer|min:0', 'is_active' => 'required|boolean'];
    }
}
