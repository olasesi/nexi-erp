<?php

namespace App\Http\Requests\Api\V1;

use App\Models\JobCandidate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJobCandidateRequest extends FormRequest
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
            'job_posting_id' => 'nullable|exists:job_postings,id',
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:255',
            'resume_url' => 'nullable|string|max:255',
            'source' => 'nullable|string|max:255',
            'stage' => ['nullable', Rule::in(JobCandidate::STAGES)],
            'expected_salary' => 'nullable|numeric',
            'notes' => 'nullable|string',
        ];
    }
}
