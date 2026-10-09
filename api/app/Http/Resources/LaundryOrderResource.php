<?php

namespace App\Http\Resources;

use App\Models\LaundryOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LaundryOrder */
class LaundryOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'order_number' => $this->order_number,
            'contact_id' => $this->contact_id,
            'status' => $this->status,
            'next_status' => $this->nextStatus(),
            'requested_at' => $this->requested_at?->toDateString(),
            'completed_at' => $this->completed_at?->toDateTimeString(),
            'discount_amount' => $this->discount_amount,
            'tax_rate' => $this->tax_rate,
            'subtotal' => $this->subtotal,
            'tax_amount' => $this->tax_amount,
            'total' => $this->total,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'items' => $this->whenLoaded('items', fn () => LaundryItemResource::collection($this->items)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
