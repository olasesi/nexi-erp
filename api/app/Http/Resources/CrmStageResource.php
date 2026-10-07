<?php

namespace App\Http\Resources;

use App\Models\CrmStage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CrmStage */
class CrmStageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'name' => $this->name,
            'position' => (int) $this->position,
            'deals_count' => $this->whenCounted('deals'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
