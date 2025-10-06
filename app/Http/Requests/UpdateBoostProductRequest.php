<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateBoostProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id'     => ['sometimes', 'exists:products,id'],
            'location'       => ['sometimes', 'string', 'max:190'],
            'duration'       => ['sometimes', 'integer', 'min:1', 'max:90'],
            'budget'         => ['sometimes', 'integer', 'min:100'],
            'start_date'     => ['sometimes', 'date', 'after_or_equal:today'],
            'payment_method' => ['sometimes', 'in:wallet,card,bank'],
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'status'  => 'error',
                'data'    => $validator->errors(),
                'message' => $validator->errors()->first(),
            ], 422)
        );
    }
}
