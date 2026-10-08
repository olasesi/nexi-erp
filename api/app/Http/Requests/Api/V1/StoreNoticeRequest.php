<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreNoticeRequest extends FormRequest
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
            'title' => 'required|string|max:255',
            'body' => 'nullable|string',
            'priority' => 'nullable|string|in:low,normal,high,urgent',
            'published_at' => 'nullable|date',
            'expires_at' => 'nullable|date',
        ];
    }
}
