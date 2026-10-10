<?php

namespace App\Http\Resources;

use App\Models\AiAgentThread;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AiAgentThread */
class AiAgentThreadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'user_id' => $this->user_id,
            'title' => $this->title,
            'context' => $this->context,
            'status' => $this->status,
            'last_message_at' => $this->last_message_at?->toDateTimeString(),
            'messages_count' => $this->whenCounted('messages'),
            'user' => $this->whenLoaded('user', fn () => $this->user ? new UserResource($this->user) : null),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
