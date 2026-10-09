<?php

namespace App\Http\Requests\Api\V1;

use App\Models\HrmLeave;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHrmLeaveRequest extends FormRequest
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
            'employee_id' => 'sometimes|nullable|exists:hrm_employees,id',
            'leave_type' => ['sometimes', 'nullable', Rule::in(HrmLeave::TYPES)],
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'days' => 'sometimes|nullable|numeric|min:0.5|max:365',
            'status' => ['sometimes', 'nullable', Rule::in(HrmLeave::STATUSES)],
            'reason' => 'sometimes|nullable|string',
            'review_note' => 'sometimes|nullable|string',
        ];
    }
}
