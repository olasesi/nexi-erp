<?php

namespace App\Http\Resources;

use App\Models\GymPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GymPlan */
class GymPlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'name' => $this->name,
            'billing_period' => $this->billing_period,
            'price' => $this->price,
            'join_fee' => $this->join_fee,
            'max_monthly_visits' => $this->max_monthly_visits,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
