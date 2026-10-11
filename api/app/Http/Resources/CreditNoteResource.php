<?php

namespace App\Http\Resources;

use App\Models\CreditNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CreditNote */
class CreditNoteResource extends JsonResource
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
            'contact' => new ContactResource($this->whenLoaded('contact')),
            'sales_return_id' => $this->sales_return_id,
            'sales_return' => new SalesReturnResource($this->whenLoaded('salesReturn')),
            'credit_note_number' => $this->credit_note_number,
            'issue_date' => $this->issue_date?->toDateString(),
            'status' => $this->status,
            'subtotal' => (float) $this->subtotal,
            'tax_amount' => (float) $this->tax_amount,
            'total' => (float) $this->total,
            'applied_amount' => (float) $this->applied_amount,
            'balance' => (float) $this->balance,
            'reason' => $this->reason,
            'notes' => $this->notes,
            'approved_at' => $this->approved_at,
            'created_by' => $this->created_by,
            'items' => CreditNoteItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->whenCounted('items'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
