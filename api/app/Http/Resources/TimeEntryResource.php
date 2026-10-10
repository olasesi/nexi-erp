<?php

namespace App\Http\Resources;

use App\Models\TimeEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TimeEntry */
class TimeEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'employee_id' => $this->employee_id,
            'employee' => $this->whenLoaded('employee', fn () => new HrmEmployeeResource($this->employee)),
            'task' => $this->task,
            'description' => $this->description,
            'started_at' => $this->started_at?->toDateTimeString(),
            'ended_at' => $this->ended_at?->toDateTimeString(),
            'duration_minutes' => $this->duration_minutes,
            'is_billable' => $this->is_billable,
            'hourly_rate' => $this->hourly_rate,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
