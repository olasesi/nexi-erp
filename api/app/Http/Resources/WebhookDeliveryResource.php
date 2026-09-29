<?php

namespace App\Http\Resources;

use App\Models\WebhookDelivery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WebhookDelivery */
class WebhookDeliveryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'webhook_endpoint_id' => $this->webhook_endpoint_id,
            'event' => $this->event,
            'status' => $this->status,
            'attempts' => $this->attempts,
            'payload' => $this->payload,
            'response_status' => $this->response_status,
            'response_body' => $this->response_body,
            'last_error' => $this->last_error,
            'next_attempt_at' => $this->next_attempt_at?->toISOString(),
            'delivered_at' => $this->delivered_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
