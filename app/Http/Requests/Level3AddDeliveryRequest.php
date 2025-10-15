<?php

namespace App\Http\Requests;

use App\Http\Requests\Traits\ReturnsJsonOnFail;
use Illuminate\Foundation\Http\FormRequest;

class Level3AddDeliveryRequest extends FormRequest
{
    use ReturnsJsonOnFail;

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'state'            => 'required|string|max:100',
            'local_government' => 'required|string|max:100',
            'variant'          => 'required|in:light,medium,heavy',
            'price'            => 'nullable|numeric|min:0|max:999999.99',
            'is_free'          => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'state.required' => 'State is required.',
            'local_government.required' => 'Local government area is required.',
            'variant.required' => 'Delivery variant is required.',
            'variant.in' => 'Delivery variant must be light, medium, or heavy.',
            'price.numeric' => 'Price must be a valid number.',
            'price.min' => 'Price cannot be negative.',
            'price.max' => 'Price cannot exceed 999,999.99.',
        ];
    }
}
