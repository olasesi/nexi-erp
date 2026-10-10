<?php

namespace App\Http\Resources;

use App\Models\BudgetLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BudgetLine */
class BudgetLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'budget_id' => $this->budget_id,
            'category' => $this->category,
            'type' => $this->type,
            'planned_amount' => $this->planned_amount,
            'actual_amount' => $this->actual_amount,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
