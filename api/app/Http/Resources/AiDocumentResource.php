<?php

namespace App\Http\Resources;

use App\Models\AiDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AiDocument */
class AiDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'user_id' => $this->user_id,
            'title' => $this->title,
            'prompt' => $this->prompt,
            'content' => $this->content,
            'template' => $this->template,
            'language' => $this->language,
            'provider' => $this->provider,
            'model' => $this->model,
            'tokens' => $this->tokens,
            'status' => $this->status,
            'generated_at' => $this->generated_at?->toDateTimeString(),
            'meta' => $this->meta,
            'user' => $this->whenLoaded('user', fn () => $this->user ? new UserResource($this->user) : null),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
