<?php

namespace App\Http\Requests\Buyer;

use App\Http\Requests\Traits\ReturnsJsonOnFail;
use Illuminate\Foundation\Http\FormRequest;

class UserAddressRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    use ReturnsJsonOnFail;
     public function authorize(): bool { return true; }

    public function rules(): array {
        return [
            'label'   => 'nullable|string|max:50',
            'phone'   => 'required|string|max:20',
            'line1'   => 'required|string|max:255',
            'line2'   => 'nullable|string|max:255',
            'city'    => 'required|string|max:100',
            'state'   => 'nullable|string|max:100',
            'country' => 'required|string|max:100',
            'zipcode' => 'nullable|string|max:20',
            'is_default' => 'boolean',
        ];
    }
}
