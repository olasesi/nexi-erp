<?php

namespace App\Http\Resources;

use App\Models\LaundryItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LaundryItem */
class LaundryItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'laundry_order_id' => $this->laundry_order_id,
            'product_id' => $this->product_id,
            'description' => $this->description,
            'service' => $this->service,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'weight_kg' => $this->weight_kg,
            'subtotal' => $this->subtotal,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
