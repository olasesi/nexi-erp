<?php

namespace App\Http\Requests\Api\V1;

use App\Models\PerformanceReview;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePerformanceReviewRequest extends FormRequest
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
            'employee_id' => 'nullable|exists:hrm_employees,id',
            'reviewer_id' => 'nullable|exists:users,id',
            'period_start' => 'nullable|date',
            'period_end' => 'nullable|date|after_or_equal:period_start',
            'scores' => 'nullable|array',
            'scores.*' => 'nullable|numeric',
            'status' => ['nullable', Rule::in(PerformanceReview::STATUSES)],
            'comments' => 'nullable|string',
        ];
    }
}
