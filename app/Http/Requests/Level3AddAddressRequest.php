<?php

namespace App\Http\Requests;

use App\Http\Requests\Traits\ReturnsJsonOnFail;
use Illuminate\Foundation\Http\FormRequest;

class Level3AddAddressRequest extends FormRequest
{
    use ReturnsJsonOnFail;

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'state'            => 'required|string',
            'local_government' => 'required|string',
            'full_address'     => 'required|string',
            'is_main'          => 'boolean',
            'opening_hours'    => 'nullable|array',
        ];
    }
}
