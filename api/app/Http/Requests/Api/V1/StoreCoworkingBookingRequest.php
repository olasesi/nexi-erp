<?php

namespace App\Http\Requests\Api\V1;

use App\Models\CoworkingBooking;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCoworkingBookingRequest extends FormRequest
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
            'unit_id' => 'required|exists:coworking_units,id',
            'contact_id' => 'nullable|exists:contacts,id',
            'start_at' => 'required|date',
            'end_at' => 'required|date|after:start_at',
            'seats' => 'nullable|integer|min:1',
            'status' => ['sometimes', Rule::in(CoworkingBooking::STATUSES)],
            'notes' => 'nullable|string',
        ];
    }
}
