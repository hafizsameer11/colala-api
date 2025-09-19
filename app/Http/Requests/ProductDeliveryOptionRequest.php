<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductDeliveryOptionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
     public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'delivery_option_ids' => 'required|array',
            'delivery_option_ids.*' => 'exists:store_delivery_pricing,id',
        ];
    }
}
