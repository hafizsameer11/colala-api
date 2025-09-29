<?php

namespace App\Http\Requests;

use App\Http\Requests\Traits\ReturnsJsonOnFail;
use Illuminate\Foundation\Http\FormRequest;

class ApplyCouponRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    use ReturnsJsonOnFail;
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
            'product_id'=>'required|integer|exists:products,id',
            'coupon_code'=>'required|string|exists:coupons,code'
        ];
    }
}
