<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketReplyRequest extends FormRequest
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
            'ticket_id' => 'required|exists:tickets,id',
            'body' => 'required|string',
            'is_internal' => 'nullable|boolean',
            'contact_id' => 'nullable|exists:contacts,id',
        ];
    }
}
