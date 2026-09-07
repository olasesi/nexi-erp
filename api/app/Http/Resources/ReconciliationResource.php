<?php

namespace App\Http\Resources;

use App\Models\Reconciliation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Reconciliation */
class ReconciliationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'bank_account_id' => $this->bank_account_id,
            'bank_account' => new BankAccountResource($this->whenLoaded('bankAccount')),
            'period_start' => $this->period_start?->toDateString(),
            'period_end' => $this->period_end?->toDateString(),
            'opening_balance' => (float) $this->opening_balance,
            'closing_balance' => (float) $this->closing_balance,
            'reconciled_amount' => (float) $this->items->sum(fn ($item) => (float) $item->bankTransaction->amount),
            'status' => $this->status,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'items' => ReconciliationItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
