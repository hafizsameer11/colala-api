<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PermissionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only super admins can manage permissions
        return $this->user() && $this->user()->hasRole('super_admin');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $permissionId = $this->route('id');
        
        return [
            'name' => ['required', 'string', 'max:255', 'unique:permissions,name,' . $permissionId],
            'slug' => ['required', 'string', 'max:255', 'unique:permissions,slug,' . $permissionId, 'regex:/^[a-z0-9._-]+$/'],
            'module' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
