<?php

namespace App\Http\Resources;

use App\Models\HrmDepartment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin HrmDepartment */
class HrmDepartmentResource extends JsonResource
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
            'code' => $this->code,
            'description' => $this->description,
            'manager_id' => $this->manager_id,
            'manager' => new UserResource($this->whenLoaded('manager')),
            'is_active' => $this->is_active,
            'employees_count' => $this->whenCounted('employees'),
            'designations_count' => $this->whenCounted('designations'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
