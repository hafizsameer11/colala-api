<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class SubscriptionRequest extends FormRequest
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
  public function rules(): array {
        $rules = [
            'plan_id'        => 'required|exists:subscription_plans,id',
            'payment_method' => 'required|in:wallet,flutterwave,paystack,apple_iap',
        ];

        // If payment_method is apple_iap, require additional fields
        if ($this->input('payment_method') === 'apple_iap') {
            $rules['receipt_data'] = 'required|string';
            $rules['transaction_id'] = 'required|string';
            $rules['original_transaction_id'] = 'required|string';
            $rules['product_id'] = 'required|string';
            $rules['billing_period'] = 'nullable|in:monthly,annual';
        }

        return $rules;
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
