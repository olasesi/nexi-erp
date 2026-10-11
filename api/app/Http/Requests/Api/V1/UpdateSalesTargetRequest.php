<?php

namespace App\Http\Requests\Api\V1;

use App\Models\SalesTarget;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSalesTargetRequest extends FormRequest
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
            'title' => 'sometimes|string|max:255',
            'period_type' => ['sometimes', Rule::in(SalesTarget::PERIODS)],
            'period_start' => 'sometimes|date',
            'period_end' => 'sometimes|date|after_or_equal:period_start',
            'target_amount' => 'sometimes|numeric|min:0',
            'achieved_amount' => 'sometimes|nullable|numeric|min:0',
            'status' => ['sometimes', Rule::in(SalesTarget::STATUSES)],
            'notes' => 'sometimes|nullable|string',
        ];
    }
}
