<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
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
            'folder_id' => 'nullable|exists:document_folders,id',
            'name' => 'required|string|max:255',
            'type' => ['nullable', Rule::in(Document::TYPES)],
            'mime_type' => 'nullable|string',
            'size' => 'nullable|integer|min:0',
            'url' => 'nullable|string|max:2048',
            'content' => 'nullable|string',
            'is_public' => 'nullable|boolean',
            'shared_with' => 'nullable|array',
        ];
    }
}
