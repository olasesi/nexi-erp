<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Suggestion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSuggestionRequest extends FormRequest
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
            'title' => 'sometimes|string|max:255',
            'body' => 'sometimes|string',
            'category' => ['sometimes', 'string', Rule::in(Suggestion::CATEGORIES)],
            'status' => ['sometimes', 'string', Rule::in(Suggestion::STATUSES)],
        ];
    }
}
