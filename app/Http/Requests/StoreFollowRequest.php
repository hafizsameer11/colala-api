<?php

namespace App\Http\Requests;

use App\Http\Requests\Traits\ReturnsJsonOnFail;
use Illuminate\Foundation\Http\FormRequest;

class StoreFollowRequest extends FormRequest
{
    use ReturnsJsonOnFail;

    public function authorize(): bool { return true; }

    public function rules(): array {
        return [
            'store_id' => 'required|exists:stores,id'
        ];
    }
}
