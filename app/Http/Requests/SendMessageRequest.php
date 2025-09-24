<?php

namespace App\Http\Requests;

use App\Http\Requests\Traits\ReturnsJsonOnFail;
use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest {
    use ReturnsJsonOnFail;

    public function authorize(): bool { return true; }

    public function rules(): array {
        return [
            'message' => 'nullable|string',
            'image'   => 'nullable|image|max:2048'
        ];
    }
}
