<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDocumentRequest extends FormRequest
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
            'folder_id' => 'sometimes|nullable|exists:document_folders,id',
            'name' => 'sometimes|required|string|max:255',
            'type' => ['sometimes', Rule::in(Document::TYPES)],
            'mime_type' => 'sometimes|nullable|string',
            'size' => 'sometimes|integer|min:0',
            'url' => 'sometimes|nullable|string|max:2048',
            'content' => 'sometimes|nullable|string',
            'is_public' => 'sometimes|boolean',
            'shared_with' => 'sometimes|array',
        ];
    }
}
