<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
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
            'subject' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => ['nullable', Rule::in(Ticket::STATUSES)],
            'priority' => ['nullable', Rule::in(Ticket::PRIORITIES)],
            'category' => 'nullable|string|max:255',
            'contact_id' => 'nullable|exists:contacts,id',
            'assigned_to' => 'nullable|exists:users,id',
            'created_by' => 'nullable|exists:users,id',
        ];
    }
}
