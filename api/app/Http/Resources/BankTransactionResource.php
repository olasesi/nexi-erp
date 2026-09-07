<?php

namespace App\Http\Resources;

use App\Models\BankTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BankTransaction */
class BankTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'bank_account_id' => $this->bank_account_id,
            'transaction_date' => $this->transaction_date?->toDateString(),
            'description' => $this->description,
            'reference' => $this->reference,
            'amount' => (float) $this->amount,
            'category' => $this->category,
            'status' => $this->status,
            'reconciliation_status' => $this->reconciliation_status,
            'matched_payment_id' => $this->matched_payment_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
