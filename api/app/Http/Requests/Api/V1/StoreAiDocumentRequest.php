<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreAiDocumentRequest extends FormRequest
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
            'title' => 'nullable|string|max:255',
            'prompt' => 'required|string',
            'content' => 'nullable|string',
            'template' => 'nullable|string|max:255',
            'language' => 'nullable|string|max:10',
            'meta' => 'nullable|array',
        ];
    }
}
