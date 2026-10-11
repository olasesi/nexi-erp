<?php

namespace App\Http\Resources;

use App\Models\Proposal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Proposal */
class ProposalResource extends JsonResource
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
            'proposal_number' => $this->proposal_number,
            'title' => $this->title,
            'status' => $this->status,
            'issue_date' => $this->issue_date?->toDateString(),
            'valid_until' => $this->valid_until?->toDateString(),
            'currency' => $this->currency,
            'subtotal' => $this->subtotal,
            'tax_amount' => $this->tax_amount,
            'discount_amount' => $this->discount_amount,
            'total' => $this->total,
            'notes' => $this->notes,
            'terms' => $this->terms,
            'invoice_id' => $this->invoice_id,
            'created_by' => $this->created_by,
            'contact' => new ContactResource($this->whenLoaded('contact')),
            'invoice' => new InvoiceResource($this->whenLoaded('invoice')),
            'items' => ProposalItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->whenCounted('items'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
