<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreYoutubeChannelRequest extends FormRequest
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
            'channel_id' => 'nullable|string',
            'title' => 'nullable|string',
            'description' => 'nullable|string',
            'subscriber_count' => 'nullable|integer|min:0',
            'video_count' => 'nullable|integer|min:0',
            'is_connected' => 'nullable|boolean',
            'connected_at' => 'nullable|date',
        ];
    }
}
