<?php

namespace App\Http\Resources;

use App\Models\AssetAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AssetAssignment */
class AssetAssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'asset_id' => $this->asset_id,
            'user_id' => $this->user_id,
            'contact_id' => $this->contact_id,
            'assigned_at' => $this->assigned_at,
            'returned_at' => $this->returned_at,
            'condition_on_return' => $this->condition_on_return,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
