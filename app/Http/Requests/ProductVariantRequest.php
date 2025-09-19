<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductVariantRequest extends FormRequest
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
            'sku' => 'nullable|string|max:100',
            'color' => 'nullable|string|max:50',
            'size' => 'nullable|string|max:50',
            'price' => 'required|numeric',
            'discount_price' => 'nullable|numeric',
            'stock' => 'required|integer|min:0',

            // variant images
            'images.*' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:2048'
        ];
    }
}
