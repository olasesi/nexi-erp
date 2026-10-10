<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDocumentFolderRequest extends FormRequest
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
            'parent_id' => 'sometimes|nullable|exists:document_folders,id',
            'name' => 'sometimes|required|string|max:255',
        ];
    }
}
