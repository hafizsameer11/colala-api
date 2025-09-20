<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class BrandCreateUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
     public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'logo' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:2048',
            'status' => 'in:active,inactive'
        ];
    }
    public function failedValidation(\Illuminate\Contracts\Validation\Validator $validator){
        throw new HttpResponseException(
            response()->json(['success' => false, 'message' => 'Validation errors','data' => $validator->errors()], 422)
        );
    }
}
