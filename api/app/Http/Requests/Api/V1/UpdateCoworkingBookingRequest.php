<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCoworkingBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Status is deliberately excluded here: a booking advances through its
     * lifecycle via the confirm/check-in/check-out/cancel actions, so a plain
     * update can never skip a transition.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'unit_id' => 'sometimes|exists:coworking_units,id',
            'contact_id' => 'sometimes|nullable|exists:contacts,id',
            'start_at' => 'sometimes|date',
            'end_at' => 'sometimes|date|after:start_at',
            'seats' => 'sometimes|integer|min:1',
            'notes' => 'sometimes|nullable|string',
        ];
    }
}
