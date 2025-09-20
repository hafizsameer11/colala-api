<?php

namespace App\Http\Requests;

use App\Http\Requests\Traits\ReturnsJsonOnFail;
use Illuminate\Foundation\Http\FormRequest;

class Level1ProfileMediaRequest extends FormRequest
{
     use ReturnsJsonOnFail;

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'profile_image' => 'nullable|image',
            'banner_image'  => 'nullable|image',
        ];
    }
}
