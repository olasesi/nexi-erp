<?php

namespace App\Http\Resources;

use App\Models\AiAgentPrompt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AiAgentPrompt */
class AiAgentPromptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'label' => $this->label,
            'prompt' => $this->prompt,
            'category' => $this->category,
            'is_shared' => $this->is_shared,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
