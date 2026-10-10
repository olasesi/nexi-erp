<?php

namespace App\Http\Resources;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Document */
class DocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'folder_id' => $this->folder_id,
            'name' => $this->name,
            'type' => $this->type,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'url' => $this->url,
            'content' => $this->content,
            'version' => $this->version,
            'is_public' => $this->is_public,
            'shared_with' => $this->shared_with,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
