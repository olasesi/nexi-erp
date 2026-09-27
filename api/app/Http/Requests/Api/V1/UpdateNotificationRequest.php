<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationRequest extends FormRequest
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
            'body' => 'nullable|string',
            'payload' => 'nullable|array',
            'read_at' => 'nullable|date',
            'is_read' => 'sometimes|boolean',
        ];
    }
}
