<?php

namespace App\Http\Requests;

use App\Http\Requests\Traits\ReturnsJsonOnFail;
use Illuminate\Foundation\Http\FormRequest;

class Level3AddAddressRequest extends FormRequest
{
    use ReturnsJsonOnFail;

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'state'            => 'required|string|max:100',
            'local_government' => 'required|string|max:100',
            'full_address'     => 'required|string|max:500',
            'is_main'          => 'boolean',
            'opening_hours'    => 'nullable|array',
            'opening_hours.*.day' => 'required_with:opening_hours|string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'opening_hours.*.open_time' => 'required_with:opening_hours|string|date_format:H:i',
            'opening_hours.*.close_time' => 'required_with:opening_hours|string|date_format:H:i|after:opening_hours.*.open_time',
            'opening_hours.*.is_closed' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'state.required' => 'State is required.',
            'state.max' => 'State name cannot exceed 100 characters.',
            'local_government.required' => 'Local government area is required.',
            'local_government.max' => 'Local government name cannot exceed 100 characters.',
            'full_address.required' => 'Full address is required.',
            'full_address.max' => 'Address cannot exceed 500 characters.',
            'opening_hours.*.day.required_with' => 'Day is required when opening hours are provided.',
            'opening_hours.*.day.in' => 'Day must be a valid day of the week.',
            'opening_hours.*.open_time.required_with' => 'Opening time is required when opening hours are provided.',
            'opening_hours.*.open_time.date_format' => 'Opening time must be in HH:MM format.',
            'opening_hours.*.close_time.required_with' => 'Closing time is required when opening hours are provided.',
            'opening_hours.*.close_time.date_format' => 'Closing time must be in HH:MM format.',
            'opening_hours.*.close_time.after' => 'Closing time must be after opening time.',
        ];
    }
}
