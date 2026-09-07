<?php

namespace App\Http\Resources;

use App\Models\ReconciliationItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ReconciliationItem */
class ReconciliationItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reconciliation_id' => $this->reconciliation_id,
            'bank_transaction_id' => $this->bank_transaction_id,
            'bank_transaction' => new BankTransactionResource($this->whenLoaded('bankTransaction')),
            'status' => $this->status,
        ];
    }
}
