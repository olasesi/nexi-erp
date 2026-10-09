<?php

namespace App\Http\Requests\Api\V1;

use App\Models\HrmLeave;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHrmLeaveRequest extends FormRequest
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
            'employee_id' => 'required|exists:hrm_employees,id',
            'leave_type' => ['nullable', Rule::in(HrmLeave::TYPES)],
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'days' => 'nullable|numeric|min:0.5|max:365',
            'status' => ['nullable', Rule::in(HrmLeave::STATUSES)],
            'reason' => 'nullable|string',
        ];
    }
}
