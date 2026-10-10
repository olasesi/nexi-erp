<?php

namespace App\Http\Requests\Api\V1;

use App\Models\AiAgentThread;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAiAgentThreadRequest extends FormRequest
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
            'title' => 'sometimes|nullable|string|max:255',
            'context' => 'sometimes|nullable|string',
            'status' => ['sometimes', Rule::in(AiAgentThread::STATUSES)],
        ];
    }
}
