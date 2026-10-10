<?php

namespace App\Http\Requests\Api\V1;

use App\Models\YoutubeActivity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreYoutubeActivityRequest extends FormRequest
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
            'type' => ['nullable', Rule::in(YoutubeActivity::TYPES)],
            'youtube_video_id' => 'nullable|exists:youtube_videos,id',
            'description' => 'nullable|string',
            'occurred_at' => 'nullable|date',
            'meta' => 'nullable|array',
        ];
    }
}
