<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class BoostProductRequest extends FormRequest
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
            'product_id'     => ['required','exists:products,id'],
            'location'       => ['nullable','string','max:190'],
            'duration'       => ['required','integer','min:1','max:90'],
            'budget'         => ['required','integer','min:100'], // e.g. min ₦100 / Rs 100 per day
            'start_date'     => ['nullable','date','after_or_equal:today'],
            'payment_method' => ['nullable','in:wallet,card,bank'],
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
