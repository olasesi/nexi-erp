<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'company_id' => 'nullable|exists:companies,id',
            'username' => ['nullable', 'string', 'max:255', Rule::unique('users', 'username')->ignore($user)],
            'name' => 'nullable|string|max:255',
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'phone' => 'nullable|string|max:50',
            'password' => 'nullable|string|min:8|confirmed',
            'is_active' => 'boolean',
            'roles' => 'nullable|array',
            'roles.*' => ['string', Rule::exists('roles', 'name')],
        ];
    }
}
