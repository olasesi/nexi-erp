<?php

namespace App\Http\Requests\Api\V1;

use App\Models\YoutubeActivity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateYoutubeActivityRequest extends FormRequest
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
            'type' => ['sometimes', Rule::in(YoutubeActivity::TYPES)],
            'youtube_video_id' => 'sometimes|nullable|exists:youtube_videos,id',
            'description' => 'sometimes|nullable|string',
            'occurred_at' => 'sometimes|nullable|date',
            'meta' => 'sometimes|nullable|array',
        ];
    }
}
