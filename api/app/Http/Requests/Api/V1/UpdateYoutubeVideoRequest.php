<?php

namespace App\Http\Requests\Api\V1;

use App\Models\YoutubeVideo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateYoutubeVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => 'sometimes|exists:companies,id',
            'title' => 'sometimes|string|max:255',
            'youtube_video_id' => 'sometimes|nullable|string',
            'description' => 'sometimes|nullable|string',
            'url' => 'sometimes|nullable|string',
            'youtube_playlist_id' => 'sometimes|nullable|exists:youtube_playlists,id',
            'duration_seconds' => 'sometimes|nullable|integer|min:0',
            'views' => 'sometimes|nullable|integer|min:0',
            'status' => ['sometimes', Rule::in(YoutubeVideo::STATUSES)],
            'published_at' => 'sometimes|nullable|date',
        ];
    }
}
