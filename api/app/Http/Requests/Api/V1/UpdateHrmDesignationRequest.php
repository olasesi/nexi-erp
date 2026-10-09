<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHrmDesignationRequest extends FormRequest
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
            'department_id' => 'sometimes|nullable|exists:hrm_departments,id',
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|nullable|string|max:50',
            'level' => 'sometimes|nullable|integer|min:1|max:100',
            'description' => 'sometimes|nullable|string',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
