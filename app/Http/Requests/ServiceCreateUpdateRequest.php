<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ServiceCreateUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'store_id' => 'required|exists:stores,id',
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'short_description' => 'nullable|string|max:255',
            'full_description' => 'nullable|string',
            'price_from' => 'nullable|numeric',
            'price_to' => 'nullable|numeric',
            'discount_price' => 'nullable|numeric',
            'media.*' => 'nullable|file|mimes:jpg,jpeg,png,mp4,mov|max:5120',
            'sub_services' => 'nullable|array',
            'sub_services.*.name' => 'required_with:sub_services|string',
            'sub_services.*.price_from' => 'nullable|numeric',
            'sub_services.*.price_to' => 'nullable|numeric',
        ];
    }
}
