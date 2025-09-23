<?php

namespace App\Http\Requests\Buyer;

use App\Http\Requests\Traits\ReturnsJsonOnFail;
use Illuminate\Foundation\Http\FormRequest;

class ReviewCreateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    use ReturnsJsonOnFail;
  public function authorize(): bool { return true; }
    public function rules(): array {
        return [
            'rating'  => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
            'images'  => 'nullable|array|max:5',
            'images.*'=> 'url'
        ];
    }
}
