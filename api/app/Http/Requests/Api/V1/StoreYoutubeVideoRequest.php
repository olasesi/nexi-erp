<?php

namespace App\Http\Requests\Api\V1;

use App\Models\YoutubeVideo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreYoutubeVideoRequest extends FormRequest
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
            'company_id' => 'nullable|exists:companies,id',
            'title' => 'required|string|max:255',
            'youtube_video_id' => 'nullable|string',
            'description' => 'nullable|string',
            'url' => 'nullable|string',
            'youtube_playlist_id' => 'nullable|exists:youtube_playlists,id',
            'duration_seconds' => 'nullable|integer|min:0',
            'views' => 'nullable|integer|min:0',
            'status' => ['nullable', Rule::in(YoutubeVideo::STATUSES)],
            'published_at' => 'nullable|date',
        ];
    }
}
