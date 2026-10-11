<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Announcement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAnnouncementRequest extends FormRequest
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
            'company_id' => 'sometimes|exists:companies,id',
            'title' => 'sometimes|string|max:255',
            'body' => 'sometimes|nullable|string',
            'audience' => ['sometimes', 'nullable', Rule::in(Announcement::AUDIENCES)],
            'audience_value' => 'sometimes|nullable|string|max:255',
            'priority' => ['sometimes', 'nullable', Rule::in(Announcement::PRIORITIES)],
            'status' => ['sometimes', 'nullable', Rule::in(Announcement::STATUSES)],
            'publish_at' => 'sometimes|nullable|date',
            'expires_at' => 'sometimes|nullable|date|after_or_equal:publish_at',
        ];
    }
}
