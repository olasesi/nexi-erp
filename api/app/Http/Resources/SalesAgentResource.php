<?php

namespace App\Http\Resources;

use App\Models\SalesAgent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SalesAgent */
class SalesAgentResource extends JsonResource
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
            'user_id' => $this->user_id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'commission_rate' => $this->commission_rate,
            'status' => $this->status,
            'requested_at' => $this->requested_at,
            'approved_at' => $this->approved_at,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'contact' => $this->whenLoaded('contact', fn () => new ContactResource($this->contact)),
            'user' => $this->whenLoaded('user', fn () => new UserResource($this->user)),
            'orders' => SalesAgentOrderResource::collection($this->whenLoaded('orders')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
