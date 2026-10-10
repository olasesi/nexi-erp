<?php

namespace App\Http\Resources;

use App\Models\Training;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Training */
class TrainingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'training_type_id' => $this->training_type_id,
            'type' => $this->whenLoaded('type', fn () => new TrainingTypeResource($this->type)),
            'title' => $this->title,
            'trainer' => $this->trainer,
            'branch' => $this->branch,
            'department' => $this->department,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'status' => $this->status,
            'description' => $this->description,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
