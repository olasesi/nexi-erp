<?php

namespace App\Http\Resources;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Project */
class ProjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status->value,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'budget' => (float) $this->budget,
            'owner_id' => $this->owner_id,
            'owner' => new UserResource($this->whenLoaded('owner')),
            'created_by' => $this->created_by,
            'createdBy' => new UserResource($this->whenLoaded('createdBy')),
            'tasks_count' => $this->whenCounted('tasks'),
            'milestones_count' => $this->whenCounted('milestones'),
            'tasks' => ProjectTaskResource::collection($this->whenLoaded('tasks')),
            'milestones' => ProjectMilestoneResource::collection($this->whenLoaded('milestones')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
