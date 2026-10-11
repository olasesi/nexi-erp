<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreAiImageRequest extends FormRequest
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
            'prompt' => 'required|string',
            'negative_prompt' => 'nullable|string',
            'style' => 'nullable|string|max:255',
            'size' => 'nullable|string|max:20',
            'meta' => 'nullable|array',
        ];
    }
}
