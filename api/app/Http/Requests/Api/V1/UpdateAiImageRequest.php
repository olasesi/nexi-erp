<?php

namespace App\Http\Requests\Api\V1;

use App\Models\AiImage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAiImageRequest extends FormRequest
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
            'prompt' => 'sometimes|required|string',
            'negative_prompt' => 'sometimes|nullable|string',
            'style' => 'sometimes|nullable|string|max:255',
            'size' => 'sometimes|nullable|string|max:20',
            'status' => ['sometimes', Rule::in(AiImage::STATUSES)],
            'meta' => 'sometimes|nullable|array',
        ];
    }
}
