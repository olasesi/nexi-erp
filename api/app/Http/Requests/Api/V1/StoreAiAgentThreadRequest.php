<?php

namespace App\Http\Requests\Api\V1;

use App\Models\AiAgentThread;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAiAgentThreadRequest extends FormRequest
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
            'title' => 'nullable|string|max:255',
            'context' => 'nullable|string',
            'status' => ['nullable', Rule::in(AiAgentThread::STATUSES)],
        ];
    }
}
