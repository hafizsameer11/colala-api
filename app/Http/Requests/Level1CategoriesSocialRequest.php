<?php

namespace App\Http\Requests;

use App\Http\Requests\Traits\ReturnsJsonOnFail;
use Illuminate\Foundation\Http\FormRequest;

class Level1CategoriesSocialRequest extends FormRequest
{
    use ReturnsJsonOnFail;

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'categories'           => 'nullable|array',
            'categories.*'         => 'exists:categories,id',
            'social_links'         => 'nullable|array',
            'social_links.*.type'  => 'in:whatsapp,instagram,facebook,twitter,tiktok,linkedin',
            'social_links.*.url'   => 'string',
        ];
    }
}
