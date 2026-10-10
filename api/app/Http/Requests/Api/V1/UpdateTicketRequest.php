<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Status is not editable here: resolution and closure run through their
     * own endpoints so their timestamps stay in step with the state.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => 'sometimes|exists:companies,id',
            'subject' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'priority' => ['sometimes', Rule::in(Ticket::PRIORITIES)],
            'category' => 'sometimes|nullable|string|max:255',
            'contact_id' => 'sometimes|nullable|exists:contacts,id',
            'assigned_to' => 'sometimes|nullable|exists:users,id',
        ];
    }
}
