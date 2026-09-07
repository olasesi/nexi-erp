<?php

namespace App\Http\Resources;

use App\Models\StockTransfer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StockTransfer */
class StockTransferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'from_warehouse_id' => $this->from_warehouse_id,
            'from_warehouse' => new WarehouseResource($this->whenLoaded('from_warehouse')),
            'to_warehouse_id' => $this->to_warehouse_id,
            'to_warehouse' => new WarehouseResource($this->whenLoaded('to_warehouse')),
            'reference_no' => $this->reference_no,
            'status' => $this->status,
            'notes' => $this->notes,
            'transferred_by' => $this->transferred_by,
            'items' => StockTransferItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
