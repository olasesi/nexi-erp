<?php

namespace App\Http\Resources;

use App\Models\HrmAttendance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin HrmAttendance */
class HrmAttendanceResource extends JsonResource
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
            'employee' => new HrmEmployeeResource($this->whenLoaded('employee')),
            'attendance_date' => $this->attendance_date?->toDateString(),
            'status' => $this->status,
            'check_in' => $this->check_in,
            'check_out' => $this->check_out,
            'work_hours' => $this->work_hours === null ? null : (float) $this->work_hours,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
