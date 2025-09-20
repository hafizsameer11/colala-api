<?php

namespace App\Http\Requests;

use App\Http\Requests\Traits\ReturnsJsonOnFail;
use Illuminate\Foundation\Http\FormRequest;

class PostCreateRequest extends FormRequest
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
            'media' => 'nullable|array|max:10',
            'media.*' => 'file|mimes:jpg,jpeg,png,webp,mp4,mov,avi|max:20480',
        ];
    }
}
