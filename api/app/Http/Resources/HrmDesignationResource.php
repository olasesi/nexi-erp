<?php

namespace App\Http\Resources;

use App\Models\HrmDesignation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin HrmDesignation */
class HrmDesignationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'department_id' => $this->department_id,
            'department' => new HrmDepartmentResource($this->whenLoaded('department')),
            'name' => $this->name,
            'code' => $this->code,
            'level' => $this->level,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'employees_count' => $this->whenCounted('employees'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
