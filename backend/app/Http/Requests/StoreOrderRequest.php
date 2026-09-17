<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $customer = $this->input('customer', []);
        $address = $this->input('address', []);

        $this->merge([
            'customer' => array_merge($customer, [
                'name' => trim((string) ($customer['name'] ?? '')),
                'mobile' => preg_replace('/[\s()-]/', '', (string) ($customer['mobile'] ?? '')),
                'email' => filled($customer['email'] ?? null) ? strtolower(trim($customer['email'])) : null,
            ]),
            'address' => array_merge($address, collect($address)->map(fn ($value) => is_string($value) ? trim($value) : $value)->all()),
            'customer_note' => filled($this->input('customer_note')) ? trim($this->input('customer_note')) : null,
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['customer' => 'required|array', 'customer.name' => 'required|string|min:2|max:120', 'customer.mobile' => 'required|string|regex:/^\+?[0-9]{10,13}$/', 'customer.email' => 'nullable|email|max:255', 'address' => 'required|array', 'address.line1' => 'required|string|min:3|max:255', 'address.line2' => 'nullable|string|max:255', 'address.landmark' => 'nullable|string|max:150', 'address.city' => 'required|string|min:2|max:100', 'address.state' => 'required|string|min:2|max:100', 'address.pincode' => 'required|digits:6', 'items' => 'required|array|min:1|max:50', 'items.*.medicine_id' => 'required|string|distinct', 'items.*.quantity' => 'required|integer|min:1|max:100', 'customer_note' => 'nullable|string|max:1000', 'prescription_upload_tokens' => 'sometimes|array|max:5', 'prescription_upload_tokens.*' => 'string|max:100|distinct'];
    }
}
