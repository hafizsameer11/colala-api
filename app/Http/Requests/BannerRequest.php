<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class BannerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
   public function authorize(): bool { return true; }
    public function rules(): array {
        return [
            'image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'link'  => 'nullable',
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
