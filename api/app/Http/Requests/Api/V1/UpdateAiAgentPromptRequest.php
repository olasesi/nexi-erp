<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAiAgentPromptRequest extends FormRequest
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
            'label' => 'sometimes|required|string|max:255',
            'prompt' => 'sometimes|required|string',
            'category' => 'sometimes|nullable|string|max:255',
            'is_shared' => 'sometimes|nullable|boolean',
        ];
    }
}
