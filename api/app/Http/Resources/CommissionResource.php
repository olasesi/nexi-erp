<?php

namespace App\Http\Resources;

use App\Models\Commission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Commission */
class CommissionResource extends JsonResource
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
            'reference' => $this->reference,
            'sale_amount' => $this->sale_amount,
            'rate' => $this->rate,
            'amount' => $this->amount,
            'status' => $this->status,
            'earned_on' => $this->earned_on?->toDateString(),
            'approved_at' => $this->approved_at,
            'paid_at' => $this->paid_at,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
