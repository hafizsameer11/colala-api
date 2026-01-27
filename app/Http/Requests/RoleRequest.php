<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only super admins can manage roles
        return $this->user() && $this->user()->hasRole('super_admin');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $roleId = $this->route('id');
        
        return [
            'name' => ['required', 'string', 'max:255', 'unique:roles,name,' . $roleId],
            'slug' => ['required', 'string', 'max:255', 'unique:roles,slug,' . $roleId, 'regex:/^[a-z0-9_-]+$/'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
