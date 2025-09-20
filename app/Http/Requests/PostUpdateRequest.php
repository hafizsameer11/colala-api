<?php

namespace App\Http\Requests;

use App\Http\Requests\Traits\ReturnsJsonOnFail;
use Illuminate\Foundation\Http\FormRequest;

class PostUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    use ReturnsJsonOnFail;
  public function authorize(): bool { return true; }
    public function rules(): array {
        return [
            'body' => 'nullable|string|max:5000',
            'visibility' => 'nullable|in:public,followers',
        ];
    }
}
