<?php

namespace App\Http\Resources;

use App\Models\GymVisit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GymVisit */
class GymVisitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'gym_member_id' => $this->gym_member_id,
            'checked_in_at' => $this->checked_in_at,
            'checked_out_at' => $this->checked_out_at,
            'checked_in_by' => $this->checked_in_by,
            'duration_minutes' => $this->durationMinutes(),
            'member' => new GymMemberResource($this->whenLoaded('member')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
