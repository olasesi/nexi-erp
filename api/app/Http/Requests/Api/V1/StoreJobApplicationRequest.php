<?php

namespace App\Http\Requests\Api\V1;

use App\Models\JobApplication;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJobApplicationRequest extends FormRequest
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
            'job_posting_id' => 'required|exists:job_postings,id',
            'name' => 'required|string',
            'email' => 'required|email',
            'phone' => 'nullable',
            'resume_url' => 'nullable|url',
            'cover_letter' => 'nullable|string',
            'status' => ['nullable', Rule::in(JobApplication::STATUSES)],
        ];
    }
}
