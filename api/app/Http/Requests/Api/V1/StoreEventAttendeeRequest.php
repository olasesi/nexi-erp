<?php

namespace App\Http\Requests\Api\V1;

use App\Models\EventAttendee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventAttendeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * An attendee must name a user, a contact, or at least carry an email.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => 'nullable|exists:companies,id',
            'event_id' => 'required|exists:events,id',
            'user_id' => ['nullable', 'integer', 'required_without_all:contact_id,email', 'exists:users,id'],
            'contact_id' => ['nullable', 'integer', 'required_without_all:user_id,email', 'exists:contacts,id'],
            'email' => ['nullable', 'email', 'max:255', 'required_without_all:user_id,contact_id'],
            'status' => ['nullable', Rule::in(EventAttendee::STATUSES)],
        ];
    }
}
