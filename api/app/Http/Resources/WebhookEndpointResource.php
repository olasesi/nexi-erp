<?php

namespace App\Http\Resources;

use App\Models\WebhookEndpoint;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WebhookEndpoint */
class WebhookEndpointResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'name' => $this->name,
            'url' => $this->url,
            'secret' => $this->secret,
            'events' => $this->events,
            'is_active' => $this->is_active,
            'description' => $this->description,
            'last_triggered_at' => $this->last_triggered_at?->toISOString(),
            'deliveries_count' => $this->whenCounted('deliveries'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
