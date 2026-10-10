<?php

namespace App\Http\Requests\Api\V1;

use App\Models\JobPosting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateJobPostingRequest extends FormRequest
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
            'title' => 'sometimes|string|max:255',
            'department' => 'sometimes|nullable|string|max:255',
            'location' => 'sometimes|nullable|string|max:255',
            'employment_type' => ['sometimes', Rule::in(JobPosting::EMPLOYMENT_TYPES)],
            'description' => 'sometimes|nullable|string',
            'requirements' => 'sometimes|nullable|string',
            'salary_min' => 'sometimes|nullable|numeric',
            'salary_max' => 'sometimes|nullable|numeric|gte:salary_min',
            'openings' => 'sometimes|nullable|integer|min:1',
            'status' => ['sometimes', Rule::in(JobPosting::STATUSES)],
            'closing_date' => 'sometimes|nullable|date',
        ];
    }
}
