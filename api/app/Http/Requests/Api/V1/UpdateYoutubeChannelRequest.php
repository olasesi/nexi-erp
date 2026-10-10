<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateYoutubeChannelRequest extends FormRequest
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
            'channel_id' => 'sometimes|nullable|string',
            'title' => 'sometimes|nullable|string',
            'description' => 'sometimes|nullable|string',
            'subscriber_count' => 'sometimes|nullable|integer|min:0',
            'video_count' => 'sometimes|nullable|integer|min:0',
            'is_connected' => 'sometimes|boolean',
            'connected_at' => 'sometimes|nullable|date',
        ];
    }
}
