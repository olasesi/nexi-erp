<?php

namespace App\Http\Resources;

use App\Models\TimesheetEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TimesheetEntry */
class TimesheetEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'timesheet_id' => $this->timesheet_id,
            'work_date' => $this->work_date?->toDateString(),
            'hours' => $this->hours,
            'project' => $this->project,
            'task' => $this->task,
            'notes' => $this->notes,
            'timesheet' => $this->whenLoaded('timesheet', fn () => $this->timesheet ? new TimesheetResource($this->timesheet) : null),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
