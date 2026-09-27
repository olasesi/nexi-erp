<?php

namespace App\Http\Resources;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Notification */
class NotificationResource extends JsonResource
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
            'type' => $this->type,
            'title' => $this->title,
            'body' => $this->body,
            'payload' => $this->data,
            'read_at' => $this->read_at,
            'is_read' => $this->isRead(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
