<?php

namespace App\Http\Requests\Api\V1;

use App\Models\KbArticle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateKbArticleRequest extends FormRequest
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
            'kb_category_id' => ['nullable', 'exists:kb_categories,id'],
            'title' => ['required', 'string'],
            'slug' => ['nullable', 'string'],
            'body' => ['nullable', 'string'],
            'status' => ['nullable', 'string', Rule::in(KbArticle::STATUSES)],
        ];
    }
}
