<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ProductCreateUpdateRequest extends FormRequest
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
            /* ---------- 🧩 PRODUCT MAIN FIELDS ---------- */
            'name'                     => 'required|string|max:255',
            'category_id'              => 'nullable|exists:categories,id',
            'brand'                    => 'nullable|string|max:255',
            'description'              => 'nullable|string',
            'price'                    => 'nullable|numeric|min:0',
            'discount_price'           => 'nullable|numeric|min:0',
            'has_variants'             => 'required|boolean',
            'status'                   => 'nullable|in:draft,active,inactive',
            'video'                    => 'nullable',
            'coupon_code'              => 'nullable|string|max:50',
            'discount'                 => 'nullable|string|max:50',
            'loyality_points_applicable' => 'boolean',
            'quantity'                 => 'nullable|integer|min:0',
            'referral_fee'             => 'nullable|numeric|min:0',
            'referral_person_limit'   => 'nullable|integer|min:1',
            'tag1'                     => 'nullable|string',
            'tag2'                     => 'nullable|string',
            'tag3'                     => 'nullable|string',

            /* ---------- 🖼️ PRODUCT IMAGES ---------- */
            'images'                   => 'array',
            'images.*'                 => 'nullable|file|mimes:jpg,jpeg,png,webp|max:4096',

            /* ---------- 🧬 VARIANTS (nested array) ---------- */
            'variants'                 => 'array',
            'variants.*.id'            => 'nullable|integer|exists:product_variants,id',
            'variants.*.sku'           => 'nullable|string|max:100',
            'variants.*.color'         => 'nullable|string|max:50',
            'variants.*.size'          => 'nullable|string|max:50',
            'variants.*.price'         => 'required_if:has_variants,1|numeric|min:0',
            'variants.*.discount_price'=> 'nullable|numeric|min:0',
            'variants.*.stock'         => 'required_if:has_variants,1|integer|min:0',

            /* ---------- 📷 VARIANT IMAGES ---------- */
            'variants.*.images'        => 'array',
            'variants.*.images.*'      => 'nullable|file|mimes:jpg,jpeg,png,webp|max:4096',
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
