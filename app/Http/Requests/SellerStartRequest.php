<?php

namespace App\Http\Requests;

use App\Http\Requests\Traits\ReturnsJsonOnFail;
use Illuminate\Foundation\Http\FormRequest;

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
            'store_email'    => 'required|email|unique:users,email|unique:stores,store_email',
            'store_phone'    => 'required|string|max:20',
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
}
