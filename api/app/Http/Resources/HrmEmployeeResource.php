<?php

namespace App\Http\Resources;

use App\Models\HrmEmployee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin HrmEmployee */
class HrmEmployeeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'employee_code' => $this->employee_code,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'gender' => $this->gender,
            'user_id' => $this->user_id,
            'department_id' => $this->department_id,
            'department' => new HrmDepartmentResource($this->whenLoaded('department')),
            'designation_id' => $this->designation_id,
            'designation' => new HrmDesignationResource($this->whenLoaded('designation')),
            'manager_id' => $this->manager_id,
            'manager' => new self($this->whenLoaded('manager')),
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'date_of_joining' => $this->date_of_joining?->toDateString(),
            'employment_status' => $this->employment_status,
            'salary' => $this->salary === null ? null : (float) $this->salary,
            'address' => $this->address,
            'notes' => $this->notes,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
