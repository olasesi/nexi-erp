<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreHrmDesignationRequest extends FormRequest
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
            'company_id' => 'nullable|exists:companies,id',
            'department_id' => 'nullable|exists:hrm_departments,id',
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'level' => 'nullable|integer|min:1|max:100',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ];
    }
}
