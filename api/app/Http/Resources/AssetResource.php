<?php

namespace App\Http\Resources;

use App\Models\Asset;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Asset */
class AssetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'asset_category_id' => $this->asset_category_id,
            'asset_tag' => $this->asset_tag,
            'name' => $this->name,
            'serial_number' => $this->serial_number,
            'purchase_date' => $this->purchase_date?->toDateString(),
            'purchase_cost' => $this->purchase_cost,
            'current_value' => $this->current_value,
            'condition' => $this->condition,
            'status' => $this->status,
            'location' => $this->location,
            'assigned_to' => $this->assigned_to,
            'notes' => $this->notes,
            'category' => new AssetCategoryResource($this->whenLoaded('category')),
            'assignments_count' => $this->whenCounted('assignments'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
