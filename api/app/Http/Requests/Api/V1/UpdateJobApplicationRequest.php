<?php

namespace App\Http\Requests\Api\V1;

use App\Models\JobApplication;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateJobApplicationRequest extends FormRequest
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
            'company_id' => 'sometimes|nullable|exists:companies,id',
            'job_posting_id' => 'sometimes|required|exists:job_postings,id',
            'name' => 'sometimes|required|string',
            'email' => 'sometimes|required|email',
            'phone' => 'sometimes|nullable',
            'resume_url' => 'sometimes|nullable|url',
            'cover_letter' => 'sometimes|nullable|string',
            'status' => ['sometimes', Rule::in(JobApplication::STATUSES)],
        ];
    }
}
