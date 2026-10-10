<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTimeEntryRequest extends FormRequest
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
            'task' => 'sometimes|nullable|string',
            'description' => 'sometimes|nullable|string',
            'started_at' => 'sometimes|nullable|date',
            'ended_at' => 'sometimes|nullable|date|after_or_equal:started_at',
            'is_billable' => 'sometimes|nullable|boolean',
            'hourly_rate' => 'sometimes|nullable|numeric|min:0',
        ];
    }
}
