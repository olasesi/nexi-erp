<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:roles,name',
            'label' => 'nullable|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => ['string', Rule::exists('permissions', 'name')],
        ];
    }
}
