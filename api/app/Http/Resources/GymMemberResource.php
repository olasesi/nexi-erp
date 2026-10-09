<?php

namespace App\Http\Resources;

use App\Models\GymMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GymMember */
class GymMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'plan_id' => $this->plan_id,
            'contact_id' => $this->contact_id,
            'member_number' => $this->member_number,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'start_at' => $this->start_at->toDateString(),
            'renews_at' => $this->renews_at->toDateString(),
            'status' => $this->status,
            'effective_status' => $this->effectiveStatus(),
            'notes' => $this->notes,
            'plan' => new GymPlanResource($this->whenLoaded('plan')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
