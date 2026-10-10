<?php

namespace App\Http\Resources;

use App\Models\YoutubeChannel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin YoutubeChannel */
class YoutubeChannelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'channel_id' => $this->channel_id,
            'title' => $this->title,
            'description' => $this->description,
            'subscriber_count' => $this->subscriber_count,
            'video_count' => $this->video_count,
            'is_connected' => $this->is_connected,
            'connected_at' => $this->connected_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
