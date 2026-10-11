<?php

namespace App\Http\Resources;

use App\Models\DebitNoteItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DebitNoteItem */
class DebitNoteItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'debit_note_id' => $this->debit_note_id,
            'product_id' => $this->product_id,
            'debit_note' => $this->whenLoaded('debitNote', fn () => new DebitNoteResource($this->debitNote)),
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'tax_rate' => $this->tax_rate,
            'total' => $this->total,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
