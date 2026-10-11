<?php

namespace App\Http\Resources;

use App\Models\CompanyPolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CompanyPolicy */
class CompanyPolicyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'title' => $this->title,
            'content' => $this->content,
            'version' => $this->version,
            'effective_date' => $this->effective_date?->toDateString(),
            'status' => $this->status,
            'is_published' => $this->isPublished(),
            'published_at' => $this->published_at?->toIso8601String(),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
