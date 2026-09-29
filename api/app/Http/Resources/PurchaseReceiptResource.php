<?php

namespace App\Http\Resources;

use App\Models\PurchaseReceipt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PurchaseReceipt */
class PurchaseReceiptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'purchase_order_id' => $this->purchase_order_id,
            'purchase_order' => new PurchaseOrderResource($this->whenLoaded('purchaseOrder')),
            'warehouse_id' => $this->warehouse_id,
            'receipt_number' => $this->receipt_number,
            'status' => $this->status,
            'received_date' => $this->received_date->toDateString(),
            'notes' => $this->notes,
            'items' => PurchaseReceiptItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
