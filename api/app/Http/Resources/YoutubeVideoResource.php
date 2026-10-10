<?php

namespace App\Http\Resources;

use App\Models\YoutubeVideo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin YoutubeVideo */
class YoutubeVideoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'title' => $this->title,
            'youtube_video_id' => $this->youtube_video_id,
            'description' => $this->description,
            'url' => $this->url,
            'youtube_playlist_id' => $this->youtube_playlist_id,
            'duration_seconds' => $this->duration_seconds,
            'views' => $this->views,
            'status' => $this->status,
            'published_at' => $this->published_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
