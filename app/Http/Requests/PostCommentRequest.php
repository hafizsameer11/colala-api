<?php

namespace App\Http\Requests;

use App\Http\Requests\Traits\ReturnsJsonOnFail;
use Illuminate\Foundation\Http\FormRequest;

class PostCommentRequest extends FormRequest
{
    use ReturnsJsonOnFail;
    /**
     * Determine if the user is authorized to make this request.
     */
   public function authorize(): bool { return true; }
    public function rules(): array {
        return ['body' => 'required|string|max:2000'];
    }
}
