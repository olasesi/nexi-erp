<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHrmDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|nullable|string|max:50',
            'description' => 'sometimes|nullable|string',
            'manager_id' => 'sometimes|nullable|exists:users,id',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
