<?php

namespace App\Http\Resources;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Message */
class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'conversation_id' => $this->conversation_id,
            'user_id' => $this->user_id,
            'contact_id' => $this->contact_id,
            'direction' => $this->direction,
            'body' => $this->body,
            'attachments' => $this->attachments,
            'read_at' => $this->read_at?->toDateTimeString(),
            'user' => $this->whenLoaded('user', fn () => $this->user ? new UserResource($this->user) : null),
            'contact' => $this->whenLoaded('contact', fn () => $this->contact ? new ContactResource($this->contact) : null),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
