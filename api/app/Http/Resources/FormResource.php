<?php

namespace App\Http\Resources;

use App\Models\Form;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Form */
class FormResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'is_template' => $this->is_template,
            'settings' => $this->settings,
            'submissions_count' => $this->submissions_count,
            'created_by' => $this->created_by,
            'fields' => FormFieldResource::collection($this->whenLoaded('fields')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
