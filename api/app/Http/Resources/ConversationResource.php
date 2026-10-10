<?php

namespace App\Http\Resources;

use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Conversation */
class ConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'subject' => $this->subject,
            'channel' => $this->channel,
            'contact_id' => $this->contact_id,
            'created_by' => $this->created_by,
            'assigned_to' => $this->assigned_to,
            'status' => $this->status,
            'is_group' => $this->is_group,
            'last_message_at' => $this->last_message_at?->toDateTimeString(),
            'messages_count' => $this->whenCounted('messages'),
            'contact' => $this->whenLoaded('contact', fn () => $this->contact ? new ContactResource($this->contact) : null),
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee ? new UserResource($this->assignee) : null),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
