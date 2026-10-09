<?php

namespace App\Http\Resources;

use App\Enums\PosOrderStatus;
use App\Models\PosOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PosOrder */
class PosOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->status;

        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'register_id' => $this->register_id,
            'register' => new PosRegisterResource($this->whenLoaded('register')),
            'contact_id' => $this->contact_id,
            'contact' => new ContactResource($this->whenLoaded('contact')),
            'cashier_id' => $this->cashier_id,
            'cashier' => new UserResource($this->whenLoaded('cashier')),
            'order_number' => $this->order_number,
            'status' => $status instanceof PosOrderStatus ? $status->value : $status,
            'payment_status' => $this->payment_status,
            'subtotal' => (float) $this->subtotal,
            'tax_amount' => (float) $this->tax_amount,
            'discount_amount' => (float) $this->discount_amount,
            'discount_rate' => (float) $this->discount_rate,
            'total' => (float) $this->total,
            'paid_amount' => (float) $this->paid_amount,
            'balance_due' => (float) $this->balance_due,
            'currency' => $this->currency,
            'notes' => $this->notes,
            'order_date' => $this->order_date?->toDateString(),
            'items' => PosOrderItemResource::collection($this->whenLoaded('items')),
            'payments' => PosPaymentResource::collection($this->whenLoaded('payments')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
