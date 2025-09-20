<?php

namespace App\Http\Requests;

use App\Http\Requests\Traits\ReturnsJsonOnFail;
use Illuminate\Foundation\Http\FormRequest;

class Level2BusinessDetailsRequest extends FormRequest
{
    use ReturnsJsonOnFail;

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'registered_name' => 'nullable|string|max:255',
            'business_type'   => 'nullable|in:BN,LTD',
            'nin_number'      => 'nullable|string|max:100',
            'bn_number'       => 'nullable|string|max:100',
            'cac_number'      => 'nullable|string|max:100',
        ];
    }
}
