<?php

namespace App\Http\Requests;

use App\Http\Requests\Traits\ReturnsJsonOnFail;
use Illuminate\Foundation\Http\FormRequest;

class ReferralWithdrawRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    use ReturnsJsonOnFail;
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount'         => 'required|numeric|min:1',
            'bank_name'      => 'required|string|max:255',
            'account_number' => 'required|string|max:50',
            'account_name'   => 'required|string|max:255',
        ];
    }

}
