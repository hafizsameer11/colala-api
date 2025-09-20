<?php

namespace App\Http\Requests;

use App\Http\Requests\Traits\ReturnsJsonOnFail;
use Illuminate\Foundation\Http\FormRequest;

class Level3AddDeliveryRequest extends FormRequest
{
    use ReturnsJsonOnFail;

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'state'            => 'required|string',
            'local_government' => 'required|string',
            'variant'          => 'required|in:light,medium,heavy',
            'price'            => 'nullable|numeric',
            'is_free'          => 'boolean',
        ];
    }
}
