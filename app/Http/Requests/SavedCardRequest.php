<?php
// app/Http/Requests/SavedCardRequest.php
namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class SavedCardRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array {
        return [
            'card_number'  => 'required|digits_between:13,19',
            'card_holder'  => 'required|string|max:100',
            'expiry_month' => 'required|digits:2',
            'expiry_year'  => 'required|digits:4',
            'cvv'          => 'required|digits_between:3,4',
        ];
    }
     protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'status'  => 'error',
                'data'    => $validator->errors(),
                'message' => $validator->errors()->first(),
            ], 422)
        );
    }
}
