<?php

namespace App\Http\Requests;

use App\Http\Requests\Traits\ReturnsJsonOnFail;
use Illuminate\Foundation\Http\FormRequest;

class Level3PhysicalStoreRequest extends FormRequest
{
     use ReturnsJsonOnFail;

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'has_physical_store' => 'nullable|boolean',
            'store_video'        => 'nullable|file|mimes:mp4,mov,avi|max:10240',
        ];
    }
}
