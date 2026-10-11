<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAwardRequest extends FormRequest
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
            'company_id' => 'sometimes|exists:companies,id',
            'employee_id' => 'sometimes|nullable|exists:hrm_employees,id',
            'award_type' => 'sometimes|string|max:255',
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'awarded_date' => 'sometimes|nullable|date',
            'gift' => 'sometimes|nullable|string|max:255',
        ];
    }
}
