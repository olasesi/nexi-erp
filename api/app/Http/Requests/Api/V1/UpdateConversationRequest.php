<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Conversation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateConversationRequest extends FormRequest
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
            'subject' => 'sometimes|nullable|string|max:255',
            'channel' => ['sometimes', Rule::in(Conversation::CHANNELS)],
            'contact_id' => 'sometimes|nullable|exists:contacts,id',
            'assigned_to' => 'sometimes|nullable|exists:users,id',
            'status' => ['sometimes', Rule::in(Conversation::STATUSES)],
            'is_group' => 'sometimes|nullable|boolean',
        ];
    }
}
