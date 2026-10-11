<?php

namespace App\Http\Resources;

use App\Models\DebitNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DebitNote */
class DebitNoteResource extends JsonResource
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
            'contact' => $this->whenLoaded('contact', fn () => new ContactResource($this->contact)),
            'purchase_order_id' => $this->purchase_order_id,
            'purchase_order' => $this->whenLoaded('purchaseOrder', fn () => new PurchaseOrderResource($this->purchaseOrder)),
            'debit_note_number' => $this->debit_note_number,
            'issue_date' => $this->issue_date?->toDateString(),
            'status' => $this->status,
            'subtotal' => $this->subtotal,
            'tax_amount' => $this->tax_amount,
            'total' => $this->total,
            'applied_amount' => $this->applied_amount,
            'balance' => $this->balance,
            'reason' => $this->reason,
            'notes' => $this->notes,
            'approved_at' => $this->approved_at,
            'items' => DebitNoteItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->whenCounted('items'),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
