<?php

namespace App\Http\Resources;

use App\Models\Rota;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Rota */
class RotaResource extends JsonResource
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
            'period_start' => $this->period_start?->toDateString(),
            'period_end' => $this->period_end?->toDateString(),
            'status' => $this->status,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'entries' => RotaEntryResource::collection($this->whenLoaded('entries')),
            'entries_count' => $this->whenCounted('entries'),
            'creator' => $this->whenLoaded('creator', fn () => $this->creator ? new UserResource($this->creator) : null),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
