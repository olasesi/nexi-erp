<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Booking;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBookingRequest extends FormRequest
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
            'company_id' => ['sometimes', 'nullable', 'exists:companies,id'],
            'resource_id' => ['sometimes', 'nullable', 'exists:booking_resources,id'],
            'title' => ['sometimes', 'required', 'string'],
            'customer_name' => ['sometimes', 'nullable', 'string'],
            'contact_id' => ['sometimes', 'nullable', 'exists:contacts,id'],
            'starts_at' => ['sometimes', 'required', 'date'],
            'ends_at' => ['sometimes', 'required', 'date', 'after:starts_at'],
            'status' => ['sometimes', 'string', Rule::in(Booking::STATUSES)],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
