<?php

namespace App\Http\Requests\Buyer;

use App\Http\Requests\Traits\ReturnsJsonOnFail;
use Illuminate\Foundation\Http\FormRequest;

class CheckoutPreviewRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    use ReturnsJsonOnFail;
     public function authorize(): bool { return true; }
     public function rules(): array {
        return [
            'delivery_address_id'   => 'required|exists:user_addresses,id',
            'delivery_pricing_ids'  => 'required|array',
            'delivery_pricing_ids.*'=> 'integer|exists:store_delivery_pricings,id',
            'payment_method'        => 'required|in:wallet,flutterwave'
        ];
    }
}
