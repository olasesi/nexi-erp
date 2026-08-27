<?php

namespace App\Http\Resources;

use App\Models\SalesOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SalesOrder */
class SalesOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'contact_id' => $this->contact_id,
            'contact' => new ContactResource($this->whenLoaded('contact')),
            'warehouse_id' => $this->warehouse_id,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'subtotal' => (float) $this->subtotal,
            'tax_amount' => (float) $this->tax_amount,
            'discount_amount' => (float) $this->discount_amount,
            'total' => (float) $this->total,
            'paid_amount' => (float) $this->paid_amount,
            'balance_due' => (float) $this->balance_due,
            'currency' => $this->currency,
            'notes' => $this->notes,
            'terms' => $this->terms,
            'order_date' => $this->order_date,
            'delivery_date' => $this->delivery_date,
            'items' => SalesOrderItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
