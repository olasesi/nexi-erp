<?php

namespace App\Http\Resources;

use App\Models\YoutubePlaylist;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin YoutubePlaylist */
class YoutubePlaylistResource extends JsonResource
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
            'youtube_playlist_id' => $this->youtube_playlist_id,
            'description' => $this->description,
            'visibility' => $this->visibility,
            'videos_count' => $this->videos_count,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
