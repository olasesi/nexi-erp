<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Message;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMessageRequest extends FormRequest
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
            'conversation_id' => 'sometimes|exists:conversations,id',
            'body' => 'sometimes|string',
            'direction' => ['sometimes', Rule::in(Message::DIRECTIONS)],
            'user_id' => 'sometimes|nullable|exists:users,id',
            'contact_id' => 'sometimes|nullable|exists:contacts,id',
            'attachments' => 'sometimes|nullable|array',
        ];
    }
}
