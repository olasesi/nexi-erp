<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $role = $this->route('role');

        return [
            'name' => ['nullable', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($role)],
            'label' => 'nullable|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => ['string', Rule::exists('permissions', 'name')],
        ];
    }
}
