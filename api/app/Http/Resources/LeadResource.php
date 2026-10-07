<?php

namespace App\Http\Resources;

use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Lead */
class LeadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'contact_id' => $this->contact_id,
            'contact' => new ContactResource($this->whenLoaded('contact')),
            'assigned_to' => $this->assigned_to,
            'assignedTo' => new UserResource($this->whenLoaded('assignedTo')),
            'name' => $this->name,
            'organization' => $this->organization,
            'email' => $this->email,
            'phone' => $this->phone,
            'source' => $this->source,
            'status' => $this->status,
            'value' => (float) $this->value,
            'expected_close_date' => $this->expected_close_date,
            'next_follow_up_at' => $this->next_follow_up_at,
            'notes' => $this->notes,
            'converted_at' => $this->converted_at,
            'deals' => DealResource::collection($this->whenLoaded('deals')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
