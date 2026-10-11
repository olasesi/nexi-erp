<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Announcement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAnnouncementRequest extends FormRequest
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
            'audience' => ['nullable', Rule::in(Announcement::AUDIENCES)],
            'audience_value' => 'nullable|string|max:255',
            'priority' => ['nullable', Rule::in(Announcement::PRIORITIES)],
            'status' => ['nullable', Rule::in(Announcement::STATUSES)],
            'publish_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:publish_at',
        ];
    }
}
