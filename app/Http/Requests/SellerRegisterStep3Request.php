<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class SellerRegisterStep3Request extends FormRequest
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
            'addresses'   => 'nullable|array',
            'addresses.*.state' => 'required_with:addresses|string',
            'addresses.*.local_government' => 'required_with:addresses|string',
            'addresses.*.full_address' => 'required_with:addresses|string',
            'addresses.*.is_main' => 'boolean',
            'addresses.*.opening_hours' => 'nullable|array',

            'delivery_pricing' => 'nullable|array',
            'delivery_pricing.*.state' => 'required_with:delivery_pricing|string',
            'delivery_pricing.*.local_government' => 'required_with:delivery_pricing|string',
            'delivery_pricing.*.variant' => 'required_with:delivery_pricing|in:light,medium,heavy',
            'delivery_pricing.*.price' => 'nullable|numeric',
            'delivery_pricing.*.is_free' => 'boolean',

            'theme_color' => 'nullable|string|max:20',
        ];
    }
    public function failedValidation(\Illuminate\Contracts\Validation\Validator $validator){
          throw new HttpResponseException(
            response()->json([
                'status' => 'error',
                'data' => $validator->errors(),
                'message' => $validator->errors()->first()
            ], 422)
        );
    }
}
