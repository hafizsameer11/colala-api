<?php

namespace App\Http\Requests;

use App\Http\Requests\Traits\ReturnsJsonOnFail;
use Illuminate\Foundation\Http\FormRequest;

class SupportTicketRequest extends FormRequest
{
    use ReturnsJsonOnFail;

    public function authorize(): bool { return true; }

    public function rules(): array {
        return [
            'category' => 'required|string',
            'subject' => 'required|string|max:255',
            'description' => 'nullable|string',
            'order_id' => 'nullable|exists:orders,id',
            'store_order_id' => 'nullable|exists:store_orders,id',
        ];
    }
}
