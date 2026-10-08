<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNoticeRequest extends FormRequest
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
            'body' => 'sometimes|nullable|string',
            'priority' => 'sometimes|string|in:low,normal,high,urgent',
            'published_at' => 'sometimes|nullable|date',
            'expires_at' => 'sometimes|nullable|date',
        ];
    }
}
