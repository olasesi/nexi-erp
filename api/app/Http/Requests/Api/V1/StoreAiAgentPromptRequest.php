<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreAiAgentPromptRequest extends FormRequest
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
            'label' => 'required|string|max:255',
            'prompt' => 'required|string',
            'category' => 'nullable|string|max:255',
            'is_shared' => 'nullable|boolean',
        ];
    }
}
