<?php

namespace App\Http\Requests;

use App\Http\Requests\Traits\ReturnsJsonOnFail;
use Illuminate\Foundation\Http\FormRequest;

class Level3UtilityBillRequest extends FormRequest
{
     use ReturnsJsonOnFail;

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'utility_bill' => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ];
    }
}
