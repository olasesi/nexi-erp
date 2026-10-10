<?php

namespace App\Http\Requests\Api\V1;

use App\Models\JobCandidate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateJobCandidateRequest extends FormRequest
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
            'job_posting_id' => 'sometimes|nullable|exists:job_postings,id',
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255',
            'phone' => 'sometimes|nullable|string|max:255',
            'resume_url' => 'sometimes|nullable|string|max:255',
            'source' => 'sometimes|nullable|string|max:255',
            'stage' => ['sometimes', Rule::in(JobCandidate::STAGES)],
            'expected_salary' => 'sometimes|nullable|numeric',
            'notes' => 'sometimes|nullable|string',
        ];
    }
}
