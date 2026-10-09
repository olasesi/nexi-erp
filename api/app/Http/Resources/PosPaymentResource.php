<?php

namespace App\Http\Resources;

use App\Models\PosPayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PosPayment */
class PosPaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pos_order_id' => $this->pos_order_id,
            'method' => $this->method,
            'amount' => (float) $this->amount,
            'reference' => $this->reference,
            'paid_at' => $this->paid_at,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
        ];
    }
}
