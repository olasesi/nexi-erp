<?php

namespace App\Http\Resources;

use App\Models\YoutubeActivity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin YoutubeActivity */
class YoutubeActivityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'type' => $this->type,
            'youtube_video_id' => $this->youtube_video_id,
            'description' => $this->description,
            'occurred_at' => $this->occurred_at,
            'meta' => $this->meta,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
