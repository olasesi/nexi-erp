<?php

namespace App\Http\Requests\Api\V1;

use App\Models\AiDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAiDocumentRequest extends FormRequest
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
            'title' => 'sometimes|nullable|string|max:255',
            'prompt' => 'sometimes|required|string',
            'content' => 'sometimes|nullable|string',
            'template' => 'sometimes|nullable|string|max:255',
            'language' => 'sometimes|nullable|string|max:10',
            'status' => ['sometimes', Rule::in(AiDocument::STATUSES)],
            'meta' => 'sometimes|nullable|array',
        ];
    }
}
