<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreNotificationRequest extends FormRequest
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
            'company_id' => 'nullable|exists:companies,id',
            'user_id' => 'nullable|exists:users,id',
            'type' => 'nullable|string|max:50',
            'title' => 'required|string|max:255',
            'body' => 'nullable|string',
            'payload' => 'nullable|array',
            'read_at' => 'nullable|date',
        ];
    }
}
