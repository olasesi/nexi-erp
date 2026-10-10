<?php

namespace App\Http\Requests\Api\V1;

use App\Models\YoutubePlaylist;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreYoutubePlaylistRequest extends FormRequest
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
            'youtube_playlist_id' => 'nullable|string',
            'description' => 'nullable|string',
            'visibility' => ['nullable', Rule::in(YoutubePlaylist::VISIBILITIES)],
            'videos_count' => 'nullable|integer|min:0',
        ];
    }
}
