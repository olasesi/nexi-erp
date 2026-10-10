<?php

namespace App\Http\Resources;

use App\Models\GoalKeyResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GoalKeyResult */
class GoalKeyResultResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'goal_id' => $this->goal_id,
            'title' => $this->title,
            'target_value' => $this->target_value,
            'current_value' => $this->current_value,
            'unit' => $this->unit,
            'progress' => $this->progress,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
