<?php

namespace App\Http\Requests\Api\V1;

use App\Models\FormSubmission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFormSubmissionRequest extends FormRequest
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
            'form_id' => 'required|exists:forms,id',
            'status' => ['nullable', Rule::in(FormSubmission::STATUSES)],
            'payload' => 'required|array',
        ];
    }
}
