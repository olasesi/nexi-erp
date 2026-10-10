<?php

namespace App\Http\Resources;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Ticket */
class TicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'ticket_number' => $this->ticket_number,
            'subject' => $this->subject,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'category' => $this->category,
            'contact_id' => $this->contact_id,
            'assigned_to' => $this->assigned_to,
            'created_by' => $this->created_by,
            'resolved_at' => $this->resolved_at,
            'closed_at' => $this->closed_at,
            'replies_count' => $this->whenCounted('replies'),
            'contact' => $this->whenLoaded('contact', fn () => $this->contact ? new ContactResource($this->contact) : null),
            'assignedTo' => $this->whenLoaded('assignedTo', fn () => $this->assignedTo ? new UserResource($this->assignedTo) : null),
            'creator' => $this->whenLoaded('creator', fn () => $this->creator ? new UserResource($this->creator) : null),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
