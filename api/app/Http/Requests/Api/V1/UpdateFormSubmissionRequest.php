<?php

namespace App\Http\Requests\Api\V1;

use App\Models\FormSubmission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFormSubmissionRequest extends FormRequest
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
            'form_id' => 'sometimes|exists:forms,id',
            'status' => ['sometimes', Rule::in(FormSubmission::STATUSES)],
            'payload' => 'sometimes|array',
        ];
    }
}
