<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductCreateUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
     public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'brand' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'price' => 'nullable|numeric',
            'discount_price' => 'nullable|numeric',
            'has_variants' => 'boolean',
            'status' => 'in:draft,active,inactive',
            'video' => 'nullable|string',

            // product images
            'images.*' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:2048',

            // delivery options
            'delivery_option_ids' => 'nullable|array',
            'delivery_option_ids.*' => 'exists:store_delivery_pricing,id'
        ];
    }
}
