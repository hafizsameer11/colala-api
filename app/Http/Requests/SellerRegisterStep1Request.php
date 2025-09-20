<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class SellerRegisterStep1Request extends FormRequest
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
            'store_name'   => 'required|string|max:255',
            'store_email'  => 'required|email|unique:users,email|unique:stores,store_email',
            'store_phone'  => 'required|string|max:20',
            'password'     => 'required|string|min:6',
            'store_location' => 'nullable|string',
            'referral_code'  => 'nullable|string|max:50',
            'profile_image'  => 'nullable|file|mimes:jpg,jpeg,png,webp',
            'banner_image'   => 'nullable|file|mimes:jpg,jpeg,png,webp',
            'social_links'   => 'nullable|array',
            'social_links.*.type' => 'in:whatsapp,instagram,facebook,twitter,tiktok,linkedin',
            'social_links.*.url'  => 'url',
               
        // ✅ New
        'categories'         => 'nullable|array',
        'categories.*'       => 'exists:categories,id',
        ];
    }

     protected function failedValidation(Validator $validator)
    {
        // Throw a JSON response when validation fails
        throw new HttpResponseException(
            response()->json([
                'status' => 'error',
                'data' => $validator->errors(),
                'message' => $validator->errors()->first()
            ], 422)
        );
    }
}
