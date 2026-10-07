<?php

namespace App\Http\Resources;

use App\Models\Deal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Deal */
class DealResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'lead_id' => $this->lead_id,
            'lead' => new LeadResource($this->whenLoaded('lead')),
            'contact_id' => $this->contact_id,
            'contact' => new ContactResource($this->whenLoaded('contact')),
            'crm_stage_id' => $this->crm_stage_id,
            'stage' => new CrmStageResource($this->whenLoaded('stage')),
            'assigned_to' => $this->assigned_to,
            'assignedTo' => new UserResource($this->whenLoaded('assignedTo')),
            'title' => $this->title,
            'value' => (float) $this->value,
            'status' => $this->status,
            'close_date' => $this->close_date,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
