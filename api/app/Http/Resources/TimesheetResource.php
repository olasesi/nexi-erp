<?php

namespace App\Http\Resources;

use App\Models\Timesheet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Timesheet */
class TimesheetResource extends JsonResource
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
            'title' => $this->title,
            'period_start' => $this->period_start?->toDateString(),
            'period_end' => $this->period_end?->toDateString(),
            'status' => $this->status,
            'total_hours' => $this->total_hours,
            'submitted_at' => $this->submitted_at,
            'approved_at' => $this->approved_at,
            'rejected_reason' => $this->rejected_reason,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'entries' => TimesheetEntryResource::collection($this->whenLoaded('entries')),
            'entries_count' => $this->whenCounted('entries'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
