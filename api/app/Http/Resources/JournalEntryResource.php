<?php

namespace App\Http\Resources;

use App\Models\JournalEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin JournalEntry */
class JournalEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'entry_number' => $this->entry_number,
            'description' => $this->description,
            'entry_date' => $this->entry_date?->toDateString(),
            'status' => $this->status,
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'created_by' => $this->created_by,
            'posted_at' => $this->posted_at?->toIso8601String(),
            'total_debit' => (float) $this->lines->sum('debit'),
            'total_credit' => (float) $this->lines->sum('credit'),
            'lines' => JournalEntryLineResource::collection($this->whenLoaded('lines')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
