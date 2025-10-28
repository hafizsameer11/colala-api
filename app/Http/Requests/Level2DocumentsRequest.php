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
            'nin_document' => 'nullable|file|mimes:jpg,jpeg,png,pdf',
            'cac_document' => 'nullable|file|mimes:jpg,jpeg,png,pdf',
            'utility_bill' => 'nullable|file|mimes:jpg,jpeg,png,pdf',
            'store_video'  => 'nullable|file|mimes:mp4,mov,avi',
        ];
    }
}
