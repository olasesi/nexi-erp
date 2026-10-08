<?php

namespace App\Http\Resources;

use App\Models\BusinessHealthSnapshot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BusinessHealthSnapshot */
class BusinessHealthSnapshotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'score_date' => $this->score_date->toDateString(),
            'overall_score' => $this->overall_score,
            'financial_score' => $this->financial_score,
            'sales_score' => $this->sales_score,
            'operations_score' => $this->operations_score,
            'team_score' => $this->team_score,
            'projects_score' => $this->projects_score,
            'signals' => $this->signals,
            'alerts' => $this->alerts,
            'insights' => $this->insights,
            'recommendations' => $this->recommendations,
            'generated_by' => $this->generated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
