<?php

namespace App\Http\Requests;

use App\Http\Requests\Traits\ReturnsJsonOnFail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class SellerStartRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
        use ReturnsJsonOnFail;

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'store_name'     => 'required|string|max:255',
        'full_name'      => 'required|string|max:255',
            'store_email'    => 'required|email|unique:users,email|unique:stores,store_email',
            'store_phone'    => 'required|string|max:20|unique:users,phone|max:11',
            'password'       => 'required|string|min:6',
            'store_location' => 'nullable|string',
            'referral_code'  => 'nullable|string|max:50',

            'profile_image'  => 'nullable|image',
            'banner_image'   => 'nullable|image',

            'categories'           => 'nullable|array',
            'categories.*'         => 'exists:categories,id',

            'social_links'         => 'nullable|array',
            'social_links.*.type'  => 'in:whatsapp,instagram,facebook,twitter,tiktok,linkedin',
            'social_links.*.url'   => 'url',
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
