<?php

namespace App\Http\Resources;

use App\Models\DocumentFolder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DocumentFolder */
class DocumentFolderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
            'children_count' => $this->whenCounted('children'),
            'documents_count' => $this->whenCounted('documents'),
        ];
    }
}
