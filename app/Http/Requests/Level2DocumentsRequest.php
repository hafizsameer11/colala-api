<?php

namespace App\Http\Requests;

use App\Http\Requests\Traits\ReturnsJsonOnFail;
use Illuminate\Foundation\Http\FormRequest;

class Level2DocumentsRequest extends FormRequest
{
   use ReturnsJsonOnFail;

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'nin_document' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'cac_document' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'utility_bill' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'store_video'  => 'nullable|file|mimes:mp4,mov,avi|max:10240',
        ];
    }
}
