<?php

namespace App\Http\Resources;

use App\Models\PerformanceReview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PerformanceReview */
class PerformanceReviewResource extends JsonResource
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
            'reviewer_id' => $this->reviewer_id,
            'employee' => $this->whenLoaded('employee', fn () => new HrmEmployeeResource($this->employee)),
            'period_start' => $this->period_start?->toDateString(),
            'period_end' => $this->period_end?->toDateString(),
            'scores' => $this->scores,
            'overall_score' => $this->overall_score,
            'status' => $this->status,
            'comments' => $this->comments,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
