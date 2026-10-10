<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Message;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMessageRequest extends FormRequest
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
            'conversation_id' => 'required|exists:conversations,id',
            'body' => 'required|string',
            'direction' => ['nullable', Rule::in(Message::DIRECTIONS)],
            'user_id' => 'nullable|exists:users,id',
            'contact_id' => 'nullable|exists:contacts,id',
            'attachments' => 'nullable|array',
        ];
    }
}
