<?php

namespace App\Http\Resources;

use App\Models\SalesReturn;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SalesReturn */
class SalesReturnResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'contact_id' => $this->contact_id,
            'invoice_id' => $this->invoice_id,
            'return_number' => $this->return_number,
            'return_date' => $this->return_date?->toDateString(),
            'status' => $this->status,
            'reason' => $this->reason,
            'subtotal' => $this->subtotal,
            'tax_amount' => $this->tax_amount,
            'total' => $this->total,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'contact' => new ContactResource($this->whenLoaded('contact')),
            'invoice' => new InvoiceResource($this->whenLoaded('invoice')),
            'items' => SalesReturnItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->whenCounted('items'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
