<?php

namespace App\Http\Requests\Api\V1;

use App\Models\JobPosting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJobPostingRequest extends FormRequest
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
            'title' => 'required|string|max:255',
            'department' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'employment_type' => ['nullable', Rule::in(JobPosting::EMPLOYMENT_TYPES)],
            'description' => 'nullable|string',
            'requirements' => 'nullable|string',
            'salary_min' => 'nullable|numeric',
            'salary_max' => 'nullable|numeric|gte:salary_min',
            'openings' => 'nullable|integer|min:1',
            'status' => ['nullable', Rule::in(JobPosting::STATUSES)],
            'closing_date' => 'nullable|date',
        ];
    }
}
