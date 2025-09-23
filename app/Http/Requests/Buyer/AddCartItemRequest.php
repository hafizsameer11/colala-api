<?php

namespace App\Http\Requests\Buyer;

use App\Http\Requests\Traits\ReturnsJsonOnFail;
use Illuminate\Foundation\Http\FormRequest;

class AddCartItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    use ReturnsJsonOnFail;
  public function authorize(): bool { return true; }
    public function rules(): array {
        return [
            'product_id' => 'required|exists:products,id',
            'variant_id' => 'nullable|exists:product_variants,id',
            'qty'        => 'required|integer|min:1|max:999'
        ];
    }
}
