<?php

namespace App\Http\Resources;

use App\Models\RentalItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RentalItem */
class RentalItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'rental_agreement_id' => $this->rental_agreement_id,
            'product_id' => $this->product_id,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'billing_period' => $this->billing_period,
            'subtotal' => $this->subtotal(),
            'returned_at' => $this->returned_at,
            'return_condition' => $this->return_condition,
            'is_returned' => $this->isReturned(),
            'product' => new ProductResource($this->whenLoaded('product')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
