<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ProductBulkPriceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
   public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'prices' => 'required|array',
            'prices.*.min_quantity' => 'required|integer|min:1',
            'prices.*.amount' => 'required|numeric|min:0',
            'prices.*.discount_percent' => 'nullable|numeric|min:0|max:100',
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
