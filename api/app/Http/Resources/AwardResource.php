<?php

namespace App\Http\Resources;

use App\Models\Award;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Award */
class AwardResource extends JsonResource
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
            'employee' => $this->whenLoaded('employee', fn () => $this->employee
                ? new HrmEmployeeResource($this->employee)
                : null),
            'award_type' => $this->award_type,
            'title' => $this->title,
            'description' => $this->description,
            'awarded_date' => $this->awarded_date?->toDateString(),
            'gift' => $this->gift,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
