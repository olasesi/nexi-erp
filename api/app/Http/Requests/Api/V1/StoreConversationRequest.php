<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Conversation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConversationRequest extends FormRequest
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
            'subject' => 'nullable|string|max:255',
            'channel' => ['nullable', Rule::in(Conversation::CHANNELS)],
            'contact_id' => 'nullable|exists:contacts,id',
            'assigned_to' => 'nullable|exists:users,id',
            'status' => ['nullable', Rule::in(Conversation::STATUSES)],
            'is_group' => 'nullable|boolean',
        ];
    }
}
