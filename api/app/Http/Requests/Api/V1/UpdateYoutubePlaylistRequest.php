<?php

namespace App\Http\Requests\Api\V1;

use App\Models\YoutubePlaylist;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateYoutubePlaylistRequest extends FormRequest
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
            'youtube_playlist_id' => 'sometimes|nullable|string',
            'description' => 'sometimes|nullable|string',
            'visibility' => ['sometimes', Rule::in(YoutubePlaylist::VISIBILITIES)],
            'videos_count' => 'sometimes|nullable|integer|min:0',
        ];
    }
}
