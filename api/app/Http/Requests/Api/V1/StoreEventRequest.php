<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventRequest extends FormRequest
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
            'description' => 'nullable|string',
            'type' => ['nullable', Rule::in(Event::TYPES)],
            'location' => 'nullable|string',
            'starts_at' => 'required|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'all_day' => 'nullable|boolean',
            'status' => ['nullable', Rule::in(Event::STATUSES)],
            'reminder_minutes' => 'nullable|integer|min:0',
        ];
    }
}
