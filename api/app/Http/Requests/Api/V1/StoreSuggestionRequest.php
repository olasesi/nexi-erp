<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Suggestion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSuggestionRequest extends FormRequest
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
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'category' => ['nullable', 'string', Rule::in(Suggestion::CATEGORIES)],
            'status' => ['sometimes', 'string', Rule::in(Suggestion::STATUSES)],
        ];
    }
}
