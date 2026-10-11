<?php

namespace App\Http\Resources;

use App\Models\DealActivity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DealActivity */
class DealActivityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'deal_id' => $this->deal_id,
            'deal' => $this->whenLoaded('deal', fn () => new DealResource($this->deal)),
            'user_id' => $this->user_id,
            'type' => $this->type,
            'title' => $this->title,
            'description' => $this->description,
            'scheduled_at' => $this->scheduled_at?->toDateTimeString(),
            'completed_at' => $this->completed_at?->toDateTimeString(),
            'status' => $this->status,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
