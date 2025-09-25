<?php

namespace App\Http\Requests;

use App\Http\Requests\Traits\ReturnsJsonOnFail;
use Illuminate\Foundation\Http\FormRequest;

class SupportMessageRequest extends FormRequest
{
  use ReturnsJsonOnFail;

    public function authorize(): bool { return true; }

    public function rules(): array {
        return [
            'ticket_id' => 'required|exists:support_tickets,id',
            'message' => 'nullable|string',
            'attachment' => 'nullable|image|max:2048'
        ];
    }
}
