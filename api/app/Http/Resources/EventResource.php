<?php

namespace App\Http\Resources;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Event */
class EventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type,
            'location' => $this->location,
            'starts_at' => $this->starts_at->toDateTimeString(),
            'ends_at' => $this->ends_at?->toDateTimeString(),
            'all_day' => $this->all_day,
            'status' => $this->status,
            'reminder_minutes' => $this->reminder_minutes,
            'created_by' => $this->created_by,
            'attendees_count' => $this->whenCounted('attendees'),
            'creator' => $this->whenLoaded('creator', fn () => $this->creator ? new UserResource($this->creator) : null),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
