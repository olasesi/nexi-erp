<?php

namespace App\Http\Resources;

use App\Models\SalesAgentOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SalesAgentOrder */
class SalesAgentOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'sales_agent_id' => $this->sales_agent_id,
            'sales_order_id' => $this->sales_order_id,
            'order_number' => $this->order_number,
            'customer_name' => $this->customer_name,
            'amount' => $this->amount,
            'commission_amount' => $this->commission_amount,
            'status' => $this->status,
            'notes' => $this->notes,
            'salesAgent' => $this->whenLoaded('salesAgent', fn () => new SalesAgentResource($this->salesAgent)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
