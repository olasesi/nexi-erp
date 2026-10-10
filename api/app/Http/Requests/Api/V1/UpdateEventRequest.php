<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventRequest extends FormRequest
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
            'description' => 'sometimes|nullable|string',
            'type' => ['sometimes', Rule::in(Event::TYPES)],
            'location' => 'sometimes|nullable|string',
            'starts_at' => 'sometimes|date',
            'ends_at' => 'sometimes|nullable|date|after_or_equal:starts_at',
            'all_day' => 'sometimes|nullable|boolean',
            'status' => ['sometimes', Rule::in(Event::STATUSES)],
            'reminder_minutes' => 'sometimes|nullable|integer|min:0',
        ];
    }
}
