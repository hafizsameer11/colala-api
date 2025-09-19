<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class SellerRegisterStep2Request extends FormRequest
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
            'registered_name' => 'nullable|string|max:255',
            'business_type'   => 'nullable|in:BN,LTD',
            'nin_number'      => 'nullable|string|max:100',
            'bn_number'       => 'nullable|string|max:100',
            'cac_number'      => 'nullable|string|max:100',
            'nin_document'    => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'cac_document'    => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'utility_bill'    => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'store_video'     => 'nullable|file|mimes:mp4,mov,avi|max:10240',
            'has_physical_store' => 'nullable|boolean',
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
