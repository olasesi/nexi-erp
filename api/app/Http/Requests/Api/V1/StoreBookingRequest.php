<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Booking;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookingRequest extends FormRequest
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
            'company_id' => ['nullable', 'exists:companies,id'],
            'resource_id' => ['nullable', 'exists:booking_resources,id'],
            'title' => ['required', 'string'],
            'customer_name' => ['nullable', 'string'],
            'contact_id' => ['nullable', 'exists:contacts,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'status' => ['sometimes', 'string', Rule::in(Booking::STATUSES)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
