<?php

namespace App\Http\Requests\Api\V1;

use App\Models\EventAttendee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventAttendeeRequest extends FormRequest
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
            'event_id' => 'sometimes|exists:events,id',
            'user_id' => 'sometimes|nullable|exists:users,id',
            'contact_id' => 'sometimes|nullable|exists:contacts,id',
            'email' => 'sometimes|nullable|email|max:255',
            'status' => ['sometimes', Rule::in(EventAttendee::STATUSES)],
        ];
    }
}
