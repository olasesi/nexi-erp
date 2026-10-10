<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTicketReplyRequest extends FormRequest
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
            'ticket_id' => 'sometimes|exists:tickets,id',
            'body' => 'sometimes|string',
            'is_internal' => 'sometimes|boolean',
            'contact_id' => 'sometimes|nullable|exists:contacts,id',
        ];
    }
}
