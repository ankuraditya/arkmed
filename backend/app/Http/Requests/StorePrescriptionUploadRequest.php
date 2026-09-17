<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePrescriptionUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['files' => 'required|array|min:1|max:5', 'files.*' => 'required|file|mimes:jpg,jpeg,png,pdf|max:8192'];
    }
}
