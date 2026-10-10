<?php

namespace App\Http\Resources;

use App\Models\TicketReply;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TicketReply */
class TicketReplyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'ticket_id' => $this->ticket_id,
            'user_id' => $this->user_id,
            'contact_id' => $this->contact_id,
            'body' => $this->body,
            'is_internal' => $this->is_internal,
            'user' => $this->whenLoaded('user', fn () => $this->user ? new UserResource($this->user) : null),
            'contact' => $this->whenLoaded('contact', fn () => $this->contact ? new ContactResource($this->contact) : null),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
