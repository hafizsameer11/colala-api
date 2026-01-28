<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateDisputeRequest extends FormRequest
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
            'store_order_id' => 'required|exists:store_orders,id',
            'category'       => 'required|string|max:255',
            'details'        => 'nullable|string',
            'images.*'       => 'nullable|file|mimes:jpg,jpeg,png|max:5120',
        ];
    }

    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'status' => 'error',
                'data'   => $validator->errors(),
                'message'=> $validator->errors()->first()
            ], 422)
        );
    }
}
