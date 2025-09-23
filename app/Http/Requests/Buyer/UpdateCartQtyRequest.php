<?php

namespace App\Http\Requests\Buyer;

use App\Http\Requests\Traits\ReturnsJsonOnFail;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCartQtyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    use ReturnsJsonOnFail;
   public function authorize(): bool { return true; }
    public function rules(): array { return ['qty'=>'required|integer|min:1|max:999']; }
}
