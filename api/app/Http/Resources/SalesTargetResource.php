<?php

namespace App\Http\Resources;

use App\Models\SalesTarget;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SalesTarget */
class SalesTargetResource extends JsonResource
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
            'title' => $this->title,
            'period_type' => $this->period_type,
            'period_start' => $this->period_start?->toDateString(),
            'period_end' => $this->period_end?->toDateString(),
            'target_amount' => (float) $this->target_amount,
            'achieved_amount' => (float) $this->achieved_amount,
            'progress_percentage' => $this->progressPercentage(),
            'status' => $this->status,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
